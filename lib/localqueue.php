<?php
namespace vettich\sp3;

use Bitrix\Main\Type\DateTime;
use vettich\sp3\db\LocalQueueTable;

/**
 * Локальная очередь: отложенные create / update / delete.
 * Обработка — по HTTP-callback от PP (post-queue), повторная регистрация URL при сбое — через CAgent.
 */
class LocalQueue
{
	const STATUS_PENDING    = 'pending';
	const STATUS_PROCESSING = 'processing';
	const STATUS_FAILED     = 'failed';

	/** Срок lease для строки в processing (сброс зависших — recoverStaleProcessing). */
	const LOCK_LEASE_SECONDS = 120;

	const OP_CREATE = 'create';
	const OP_UPDATE = 'update';
	const OP_DELETE = 'delete';

	const MAX_ATTEMPTS = 4;

	const BATCH_LIMIT = 10;

	/** Пакетное удаление устаревших failed (за один проход агента). */
	const FAILED_PURGE_BATCH = 200;

	/** CAgent: только повтор post-queue при недоступности PP / неудачном wake. */
	const AGENT_RETRY_NAME = '\vettich\sp3\LocalQueue::retryPpWakeForLocalQueue();';

	const OPTION_WAKE_PENDING = 'local_queue_wake_pending';

	private static $ppWakeShutdownRegistered = false;

	/**
	 * Постановка update в очередь (дедупликация по pending + той же паре elem/iblock/op).
	 */
	public static function enqueueUpdate($elemId, $iblockId, $wasActive)
	{
		$elemId   = (int)$elemId;
		$iblockId = (int)$iblockId;
		if ($elemId <= 0 || $iblockId <= 0) {
			return;
		}

		Log::debug(["enqueueUpdate", '$elemId' => $elemId, '$iblockId' => $iblockId, 'was_active' => $wasActive]);

		if (self::hasPendingCreate($elemId, $iblockId)) {
			return;
		}

		self::removePendingOpposite(self::OP_DELETE, $elemId, $iblockId);

		$payload = json_encode(['was_active' => (string)$wasActive], JSON_UNESCAPED_UNICODE);
		if ($payload === false) {
			$payload = '{}';
		}

		self::enqueuePendingUpsert($elemId, $iblockId, self::OP_UPDATE, $payload, true);

		self::schedulePpWakeOnShutdown();
	}

	/**
	 * Постановка delete в очередь.
	 */
	public static function enqueueDelete($elemId, $iblockId)
	{
		$elemId   = (int)$elemId;
		$iblockId = (int)$iblockId;
		if ($elemId <= 0 || $iblockId <= 0) {
			return;
		}

		Log::debug(["enqueueDelete", '$elemId' => $elemId, '$iblockId' => $iblockId]);

		self::removePendingOpposite(self::OP_UPDATE, $elemId, $iblockId);
		self::removePendingOpposite(self::OP_CREATE, $elemId, $iblockId);

		self::enqueuePendingUpsert($elemId, $iblockId, self::OP_DELETE, '{}', false);

		self::schedulePpWakeOnShutdown();
	}

	/**
	 * Постановка первичной публикации (аналог Events::ADD без addPostToQueue в хите; обработка — publish с Events::QUEUE).
	 */
	public static function enqueueCreate($elemId, $iblockId)
	{
		$elemId   = (int)$elemId;
		$iblockId = (int)$iblockId;
		if ($elemId <= 0 || $iblockId <= 0) {
			return;
		}

		Log::debug(['enqueueCreate', 'elemId' => $elemId, 'iblockId' => $iblockId]);

		self::removePendingOpposite(self::OP_DELETE, $elemId, $iblockId);

		self::enqueuePendingUpsert($elemId, $iblockId, self::OP_CREATE, '{}', false);

		self::schedulePpWakeOnShutdown();
	}

	private static function hasPendingCreate($elemId, $iblockId)
	{
		$row = LocalQueueTable::getList([
			'filter' => [
				'=ELEM_ID'     => $elemId,
				'=IBLOCK_ID'   => (int) $iblockId,
				'=OPERATION'   => self::OP_CREATE,
				'=STATUS'      => self::STATUS_PENDING,
			],
			'limit'  => 1,
			'select' => ['ID'],
		])->fetch();

		return (bool)$row;
	}

	private static function removePendingOpposite($op, $elemId, $iblockId)
	{
		$rs = LocalQueueTable::getList([
			'filter' => [
				'=ELEM_ID'     => $elemId,
				'=IBLOCK_ID'   => (int) $iblockId,
				'=OPERATION'   => $op,
				'=STATUS'      => self::STATUS_PENDING,
			],
		]);
		while ($r = $rs->fetch()) {
			LocalQueueTable::delete($r['ID']);
		}
	}

	/**
	 * Сброс зависших processing (краш PHP / таймаут lease).
	 */
	private static function recoverStaleProcessing()
	{
		try {
			LocalQueueTable::recoverStaleProcessingRows(self::STATUS_PENDING, self::STATUS_PROCESSING);
		} catch (\Exception $e) {
			Log::debug(['local_queue_recover' => $e->getMessage()]);
		}
	}

	/**
	 * Атомарно резервирует пачку pending-строк (SELECT … FOR UPDATE + UPDATE).
	 *
	 * @return int[]
	 */
	private static function claimPendingBatchIds()
	{
		try {
			return LocalQueueTable::claimPendingBatchIds(
				self::STATUS_PENDING,
				self::STATUS_PROCESSING,
				self::BATCH_LIMIT,
				self::LOCK_LEASE_SECONDS
			);
		} catch (\Exception $e) {
			Log::debug(['local_queue_claim' => $e->getMessage()]);

			return [];
		}
	}

	/**
	 * Вставка pending или атомарное обновление существующей строки (UNIQUE ELEM_ID+IBLOCK_ID+OPERATION).
	 *
	 * @param int    $elemId
	 * @param int    $iblockId
	 * @param string $operation
	 * @param string $payload
	 * @param bool   $updatePayloadOnDup обновлять PAYLOAD при конфликте (update)
	 */
	private static function enqueuePendingUpsert($elemId, $iblockId, $operation, $payload, $updatePayloadOnDup)
	{
		try {
			LocalQueueTable::enqueuePendingUpsertRow(
				$elemId,
				(int) $iblockId,
				$operation,
				$payload,
				self::STATUS_PENDING,
				self::STATUS_FAILED,
				self::STATUS_PROCESSING,
				$updatePayloadOnDup
			);
		} catch (\Exception $e) {
			Log::debug(['local_queue_enqueue_upsert' => $e->getMessage()]);
		}
	}

	/**
	 * Один вызов post-queue после ответа клиенту (не блокирует сохранение ИБ).
	 */
	private static function schedulePpWakeOnShutdown()
	{
		if (self::$ppWakeShutdownRegistered) {
			return;
		}
		self::$ppWakeShutdownRegistered = true;
		register_shutdown_function([__CLASS__, 'runPpWakeOnShutdown']);
	}

	public static function runPpWakeOnShutdown()
	{
		if (!\CModule::IncludeModule('vettich.sp3')) {
			return;
		}
		$ok = Api::requestLocalQueueWake();
		Log::debug(["runPpWakeOnShutdown", 'ok' => $ok]);
		if ($ok) {
			self::setWakePending(false);
		} else {
			self::setWakePending(true);
		}
	}

	private static function setWakePending($on)
	{
		\COption::SetOptionString('vettich.sp3', self::OPTION_WAKE_PENDING, $on ? 'Y' : 'N');
	}

	private static function isWakePending()
	{
		return \COption::GetOptionString('vettich.sp3', self::OPTION_WAKE_PENDING, 'N') === 'Y';
	}

	/**
	 * HTTP handler (PP → ajax processLocalQueue): пачка задач + при необходимости повторный post-queue.
	 *
	 * @return array<string, mixed>
	 */
	public static function handleHttpProcess()
	{
		if (!\CModule::IncludeModule('vettich.sp3') || !\CModule::IncludeModule('iblock')) {
			return ['error' => ['msg' => 'module', 'code' => 'MODULE']];
		}

		$processed = self::processPendingBatch();
		$hasMore   = self::hasPendingReady();

		if ($processed > 0) {
			self::setWakePending(false);
		}

		if ($hasMore && !empty(Api::token())) {
			$chainOk = Api::requestLocalQueueWake();
			if (!$chainOk) {
				self::setWakePending(true);
			}
		}

		Log::debug(['handleHttpProcess','processed' => $processed, 'has_more' => $hasMore]);

		return [
			'ok'        => true,
			'processed' => $processed,
			'has_more'  => $hasMore,
		];
	}

	private static function hasPendingReady()
	{
		$now = DateTime::createFromPhp(new \DateTime());
		$row = LocalQueueTable::getList([
			'filter' => [
				'=STATUS'           => self::STATUS_PENDING,
				'<=NEXT_ATTEMPT_AT' => $now,
			],
			'order'  => ['ID' => 'ASC'],
			'limit'  => 1,
			'select' => ['ID'],
		])->fetch();

		return (bool)$row;
	}

	/**
	 * @return int число обработанных строк
	 */
	private static function processPendingBatch()
	{
		self::recoverStaleProcessing();

		$ids = self::claimPendingBatchIds();
		if (empty($ids)) {
			return 0;
		}

		$rs = LocalQueueTable::getList([
			'filter' => [
				'ID' => $ids,
			],
			'order'  => ['ID' => 'ASC'],
		]);

		$processed = 0;
		while ($row = $rs->fetch()) {
			try {
				if ($row['OPERATION'] === self::OP_UPDATE) {
					$arFields = [
						'ID'        => (int) $row['ELEM_ID'],
						'IBLOCK_ID' => (int) $row['IBLOCK_ID'],
					];
					$payload = [];
					if (!empty($row['PAYLOAD'])) {
						$decoded = json_decode($row['PAYLOAD'], true);
						if (is_array($decoded)) {
							$payload = $decoded;
						}
					}
					$params = ['event' => Events::UPDATE];
					if (array_key_exists('was_active', $payload)) {
						$params['queued_was_active'] = (string) $payload['was_active'];
					}
					TemplateHelpers::runUpdate($arFields, $params);
				} elseif ($row['OPERATION'] === self::OP_DELETE) {
					$arFields = [
						'ID'        => (int) $row['ELEM_ID'],
						'IBLOCK_ID' => (int) $row['IBLOCK_ID'],
					];
					TemplateHelpers::runDelete($arFields, ['event' => Events::DELETE]);
				} elseif ($row['OPERATION'] === self::OP_CREATE) {
					$arFields = [
						'ID'        => (int) $row['ELEM_ID'],
						'IBLOCK_ID' => (int) $row['IBLOCK_ID'],
					];
					TemplateHelpers::publish($arFields, ['event' => Events::QUEUE]);
				} else {
					LocalQueueTable::update($row['ID'], [
						'STATUS'        => self::STATUS_FAILED,
						'LOCKED_UNTIL'  => null,
					]);
					continue;
				}
				LocalQueueTable::delete($row['ID']);
				++$processed;
			} catch (\Exception $e) {
				Log::debug(['local_queue' => $row['ID'], 'err' => $e->getMessage()]);
				$attempts = (int) $row['ATTEMPTS'] + 1;
				if ($attempts >= self::MAX_ATTEMPTS) {
					LocalQueueTable::update($row['ID'], [
						'STATUS'        => self::STATUS_FAILED,
						'ATTEMPTS'      => $attempts,
						'LOCKED_UNTIL'  => null,
					]);
				} else {
					$delaySec = min(3600, (int) pow(2, $attempts) * 60);
					$next = DateTime::createFromTimestamp(time() + $delaySec);
					LocalQueueTable::update($row['ID'], [
						'STATUS'          => self::STATUS_PENDING,
						'ATTEMPTS'        => $attempts,
						'NEXT_ATTEMPT_AT' => $next,
						'LOCKED_UNTIL'    => null,
					]);
				}
			}
		}

		return $processed;
	}

	/**
	 * Удаление failed-записей старше одного месяца (по CREATED_AT), пачками.
	 */
	private static function purgeOldFailedRecords()
	{
		$phpCutoff = new \DateTime();
		$phpCutoff->modify('-1 month');
		$cutoff = DateTime::createFromPhp($phpCutoff);

		for ($i = 0; $i < 50; $i++) {
			$rs = LocalQueueTable::getList([
				'filter' => [
					'=STATUS'      => self::STATUS_FAILED,
					'<CREATED_AT' => $cutoff,
				],
				'select' => ['ID'],
				'order'  => ['ID' => 'ASC'],
				'limit'  => self::FAILED_PURGE_BATCH,
			]);

			$deleted = 0;
			while ($row = $rs->fetch()) {
				LocalQueueTable::delete($row['ID']);
				$deleted++;
			}
			if ($deleted < self::FAILED_PURGE_BATCH) {
				break;
			}
		}
	}

	/**
	 * CAgent: повторная регистрация post-queue, если прошлый wake не удался, либо есть готовые pending.
	 *
	 * @return string
	 */
	public static function retryPpWakeForLocalQueue()
	{
		if (!\CModule::IncludeModule('vettich.sp3')) {
			return self::AGENT_RETRY_NAME;
		}

		self::purgeOldFailedRecords();

		$needWake = self::isWakePending() || self::hasPendingReady();
		if (!$needWake) {
			return self::AGENT_RETRY_NAME;
		}

		if (empty(Api::token())) {
			return self::AGENT_RETRY_NAME;
		}

		$ok = Api::requestLocalQueueWake();
		if ($ok) {
			self::setWakePending(false);
		} else {
			self::setWakePending(true);
		}

		return self::AGENT_RETRY_NAME;
	}

	/**
	 * Число задач в локальной очереди со статусом «ожидает обработки».
	 */
	public static function getPendingCount(): int
	{
		return (int) LocalQueueTable::getCount([
			'=STATUS' => self::STATUS_PENDING,
		]);
	}
}
