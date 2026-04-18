<?php
namespace vettich\sp3\db;

use Bitrix\Main\Application;
use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

/**
 * Локальная очередь отложенных update/delete для ParrotPoster (без синхронного HTTP в событии ИБ).
 */
class LocalQueueTable extends OrmBase
{
	/**
	 * Имя таблицы в БД с обратными кавычками (с префиксом Bitrix, если задан).
	 *
	 * @return string
	 */
	private static function getQuotedTableName()
	{
		$name = static::getEntity()->getDBTableName();
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
			return '`vettich_sp3_local_queue`';
		}

		return '`'.$name.'`';
	}

	/**
	 * Сброс зависших processing (истёк lease или NULL).
	 *
	 * @param string $statusPending
	 * @param string $statusProcessing
	 */
	public static function recoverStaleProcessingRows($statusPending, $statusProcessing)
	{
		$connection = Application::getConnection();
		$table = self::getQuotedTableName();
		$h = $connection->getSqlHelper();
		$connection->queryExecute(
			'UPDATE '.$table.'
			SET `STATUS` = \''.$h->forSql((string) $statusPending).'\', `LOCKED_UNTIL` = NULL
			WHERE `STATUS` = \''.$h->forSql((string) $statusProcessing).'\'
				AND (`LOCKED_UNTIL` IS NULL OR `LOCKED_UNTIL` < NOW())'
		);
	}

	/**
	 * Атомарно резервирует пачку pending-строк (SELECT … FOR UPDATE + UPDATE).
	 *
	 * @param string $statusPending
	 * @param string $statusProcessing
	 * @param int    $limit
	 * @param int    $leaseSeconds
	 * @return int[]
	 */
	public static function claimPendingBatchIds($statusPending, $statusProcessing, $limit, $leaseSeconds)
	{
		$ids = [];
		$connection = Application::getConnection();
		$table = self::getQuotedTableName();
		$h = $connection->getSqlHelper();
		$limit = (int) $limit;
		$leaseSeconds = (int) $leaseSeconds;

		$connection->startTransaction();
		try {
			$res = $connection->query(
				'SELECT `ID` FROM '.$table.'
				WHERE `STATUS` = \''.$h->forSql((string) $statusPending).'\'
					AND `NEXT_ATTEMPT_AT` <= NOW()
				ORDER BY `ID` ASC
				LIMIT '.$limit.'
				FOR UPDATE'
			);
			while ($row = $res->fetch()) {
				$ids[] = (int) $row['ID'];
			}
			if (!empty($ids)) {
				$in = implode(',', $ids);
				$connection->queryExecute(
					'UPDATE '.$table.'
					SET `STATUS` = \''.$h->forSql((string) $statusProcessing).'\',
						`LOCKED_UNTIL` = DATE_ADD(NOW(), INTERVAL '.$leaseSeconds.' SECOND)
					WHERE `ID` IN ('.$in.')'
				);
			}
			$connection->commitTransaction();
		} catch (\Exception $e) {
			$connection->rollbackTransaction();
			throw $e;
		}

		return $ids;
	}

	/**
	 * INSERT pending или ON DUPLICATE KEY UPDATE (UNIQUE ELEM_ID, IBLOCK_ID, OPERATION).
	 *
	 * @param int $elemId
	 * @param int $iblockId
	 * @param string $operation
	 * @param string $payload
	 * @param string $statusPending
	 * @param string $statusFailed
	 * @param string $statusProcessing
	 * @param bool   $updatePayloadOnDup
	 */
	public static function enqueuePendingUpsertRow(
		$elemId,
		$iblockId,
		$operation,
		$payload,
		$statusPending,
		$statusFailed,
		$statusProcessing,
		$updatePayloadOnDup
	) {
		$connection = Application::getConnection();
		$helper = $connection->getSqlHelper();
		$table = self::getQuotedTableName();

		$now = DateTime::createFromPhp(new \DateTime());
		$nowStr = $now->format('Y-m-d H:i:s');

		$sql = 'INSERT INTO '.$table.' (
			`IBLOCK_ID`, `ELEM_ID`, `OPERATION`, `PAYLOAD`, `STATUS`, `ATTEMPTS`, `NEXT_ATTEMPT_AT`, `CREATED_AT`, `LOCKED_UNTIL`
		) VALUES (
			'.(int) $iblockId.',
			'.(int) $elemId.',
			\''.$helper->forSql((string) $operation).'\',
			\''.$helper->forSql((string) $payload).'\',
			\''.$helper->forSql((string) $statusPending).'\',
			0,
			\''.$helper->forSql($nowStr).'\',
			\''.$helper->forSql($nowStr).'\',
			NULL
		) ON DUPLICATE KEY UPDATE
			`NEXT_ATTEMPT_AT` = VALUES(`NEXT_ATTEMPT_AT`)';

		if ($updatePayloadOnDup) {
			$sql .= ', `PAYLOAD` = VALUES(`PAYLOAD`)';
		}

		// Порядок важен: сначала выражения, читающие старый `STATUS`, затем присвоение `STATUS`.
		$sql .= ',
			`LOCKED_UNTIL` = IF(`STATUS` = \''.$helper->forSql((string) $statusProcessing).'\', `LOCKED_UNTIL`, NULL),
			`ATTEMPTS` = IF(`STATUS` = \''.$helper->forSql((string) $statusFailed).'\', 0, `ATTEMPTS`),
			`STATUS` = IF(`STATUS` = \''.$helper->forSql((string) $statusFailed).'\', \''.$helper->forSql((string) $statusPending).'\', `STATUS`)';

		$connection->queryExecute($sql);
	}

	public static function getTableName()
	{
		return 'vettich_sp3_local_queue';
	}

	public static function getMap()
	{
		return [
			new Entity\IntegerField('ID', [
				'primary'      => true,
				'autocomplete' => true,
			]),
			new Entity\IntegerField('IBLOCK_ID', [
				'required'      => true,
				'default_value' => 0,
			]),
			new Entity\IntegerField('ELEM_ID', [
				'required'      => true,
				'default_value' => 0,
			]),
			new Entity\StringField('OPERATION', [
				'required'      => true,
				'default_value' => '',
			]),
			new Entity\TextField('PAYLOAD', [
				'default_value' => '',
			]),
			new Entity\StringField('STATUS', [
				'required'      => true,
				'default_value' => 'pending',
			]),
			new Entity\IntegerField('ATTEMPTS', [
				'default_value' => 0,
			]),
			new Entity\DatetimeField('NEXT_ATTEMPT_AT', [
				'required' => true,
			]),
			new Entity\DatetimeField('CREATED_AT', [
				'required'      => true,
				'default_value' => DateTime::createFromPhp(new \DateTime()),
			]),
			new Entity\DatetimeField('LOCKED_UNTIL', [
				'required'  => false,
				'nullable'  => true,
			]),
		];
	}
}
