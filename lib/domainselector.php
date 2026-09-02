<?php

namespace vettich\sp3;

class DomainSelector {
	private const HEALTHY_RECHECK_TTL = 3600; // полный пересчёт, когда все домены живы
	private const DEGRADED_RECHECK_TTL = 45; // пересчёт, если хотя бы один домен недоступен
	private const MIN_PROBE_INTERVAL_SEC = 10; // не запускать пробу чаще, чем раз в ~10 с
	private const HTTP_TIMEOUT = 4; // общий timeout пробы в секундах
	private const CONNECT_TIMEOUT = 2; // timeout соединения пробы в секундах
	private const PROBE_RETRY_DELAY_US = 400000; // пауза перед повторной пробой (~400 мс)
	private const UNAVAILABLE_PING = -1; // ping, если успешных измерений ещё не было
	private const ERROR_CACHE_TTL = 300; // окно учёта ошибок боевых запросов (сек)
	private const ERROR_THRESHOLD = 2; // сколько ошибок за окно считаем проблемой
	private const FAIL_STREAK_THRESHOLD = 2; // с какого fail_streak домен считается недоступным
	private const FAIL_STREAK_SOFT = 1; // прирост streak на timeout
	private const FAIL_STREAK_HARD = 2; // прирост streak на жёсткий отказ (сразу до порога)
	private const DEGRADED_SORT_PENALTY_MS = 5000; // штраф в сортировке при fail_streak > 0
	private const AGENT_INTERVAL_SEC = 60;

	public const AGENT_NAME = '\vettich\sp3\DomainSelector::agentRefreshDomains();';

	private static function state(): array
	{
		return DomainCache::load();
	}

	private static function hasRecentErrors($domain) {
		$hash = md5($domain);
		$state = self::state();
		$errors = isset($state['errors'][$hash]) && is_array($state['errors'][$hash]) ? $state['errors'][$hash] : [];
		$cutoff = time() - self::ERROR_CACHE_TTL;
		$recentErrors = array_filter($errors, function ($time) use ($cutoff) {
			return $time > $cutoff;
		});

		return count($recentErrors) >= self::ERROR_THRESHOLD;
	}

	/**
	 * @param array $raw
	 *
	 * @return array{domain: string, ping: int, available: bool, fail_streak: int, last_ok: int, last_error_kind: string|null}
	 */
	private static function normalizeDomainEntry(array $raw, string $domain = ''): array
	{
		$name = isset($raw['domain']) && is_string($raw['domain']) && $raw['domain'] !== ''
			? $raw['domain']
			: $domain;

		$ping = isset($raw['ping']) && is_numeric($raw['ping']) ? (int)$raw['ping'] : self::UNAVAILABLE_PING;
		$kind = $raw['last_error_kind'] ?? null;
		if ($kind !== 'timeout' && $kind !== 'hard') {
			$kind = null;
		}

		if (isset($raw['fail_streak']) && is_numeric($raw['fail_streak'])) {
			$failStreak = max(0, (int)$raw['fail_streak']);
			$available = $failStreak < self::FAIL_STREAK_THRESHOLD;
		} else {
			// Старый кэш без fail_streak: available/ping — источник истины до следующей пробы.
			$available = !empty($raw['available']) && $ping > 0;
			$failStreak = $available ? 0 : self::FAIL_STREAK_THRESHOLD;
		}

		return [
			'domain'           => $name,
			'ping'             => $ping,
			'available'        => $available,
			'fail_streak'      => $failStreak,
			'last_ok'          => isset($raw['last_ok']) && is_numeric($raw['last_ok']) ? (int)$raw['last_ok'] : 0,
			'last_error_kind'  => $kind,
		];
	}

	private static function emptyDomainEntry(string $domain): array
	{
		return self::normalizeDomainEntry([
			'domain'      => $domain,
			'fail_streak' => 0,
			'ping'        => self::UNAVAILABLE_PING,
		]);
	}

	private static function isConfiguredDomain(string $domain): bool
	{
		$domains = Config::domains();
		if (!is_array($domains)) {
			return false;
		}

		return in_array($domain, $domains, true);
	}

	private static function sortPing($ping): int
	{
		$ping = (int)$ping;

		return $ping > 0 ? $ping : PHP_INT_MAX;
	}

	/**
	 * @param array{ping?: int, fail_streak?: int} $entry
	 */
	private static function sortKey(array $entry): int
	{
		$key = self::sortPing($entry['ping'] ?? 0);
		if (($entry['fail_streak'] ?? 0) > 0) {
			$key += self::DEGRADED_SORT_PENALTY_MS;
		}

		return $key;
	}

	/**
	 * Получить наиболее подходящий домен (с наименьшим ping)
	 *
	 * @return string|false Возвращает URL домена или false если нет доступных
	 */
	public static function getBestDomain() {
		static::updateDomainsIfNeed();

		$availableDomains = static::getAvailableDomains();
		if (empty($availableDomains)) {
			return false;
		}

		usort($availableDomains, function ($a, $b) {
			return self::sortKey($a) <=> self::sortKey($b);
		});

		return $availableDomains[0]['domain'];
	}

	/**
	 * Получить домены по приоритету (быстрее -> медленнее), исключая проблемные.
	 *
	 * @param bool $forceRefresh Принудительно пересчитать доступность доменов
	 * @return string[] список доменов
	 */
	public static function getPriorityDomains(bool $forceRefresh = false): array
	{
		if ($forceRefresh) {
			static::forceRefresh();
		} else {
			static::updateDomainsIfNeed();
		}

		$availableDomains = self::state()['available_domains'] ?: [];
		if (empty($availableDomains) || !is_array($availableDomains)) {
			return [];
		}

		$availableDomains = array_filter($availableDomains, function ($domain) {
			$entry = self::normalizeDomainEntry(is_array($domain) ? $domain : []);

			return $entry['domain'] !== ''
				&& $entry['available']
				&& !self::hasRecentErrors($entry['domain']);
		});

		if (empty($availableDomains)) {
			return [];
		}

		$availableDomains = array_map(static function ($domain) {
			return self::normalizeDomainEntry(is_array($domain) ? $domain : []);
		}, $availableDomains);

		usort($availableDomains, function ($a, $b) {
			return self::sortKey($a) <=> self::sortKey($b);
		});

		return array_values(array_map(static fn($d) => $d['domain'], $availableDomains));
	}

	/**
	 * Получить все доступные домены
	 *
	 * @return array|false Массив объектов {domain, ping, available, ...}
	 */
	private static function getAvailableDomains() {
		$availableDomains = self::state()['available_domains'];

		if (empty($availableDomains) || !is_array($availableDomains)) {
			return false;
		}

		$availableDomains = array_filter($availableDomains, function ($domain) {
			$entry = self::normalizeDomainEntry(is_array($domain) ? $domain : []);

			return $entry['domain'] !== '' && $entry['available'];
		});

		if (empty($availableDomains)) {
			return false;
		}

		return array_map(static function ($domain) {
			return self::normalizeDomainEntry(is_array($domain) ? $domain : []);
		}, $availableDomains);
	}

	private static function hasDegradedDomain(array $state): bool
	{
		$domains = $state['available_domains'] ?? [];
		if (!is_array($domains) || $domains === []) {
			return false;
		}

		foreach ($domains as $domain) {
			$entry = self::normalizeDomainEntry(is_array($domain) ? $domain : []);
			if ($entry['domain'] === '') {
				continue;
			}
			// fail_streak>0 ещё available: нужна короткая TTL, иначе порог из двух циклов не наступит час.
			if (!$entry['available'] || $entry['fail_streak'] > 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Отметить ошибку на домене (вызывать при неудачном запросе)
	 *
	 * @return void
	 */
	public static function markDomainError($domain) {
		$hash = md5($domain);

		DomainCache::withLock(function (array $state) use ($hash) {
			$errors = isset($state['errors'][$hash]) && is_array($state['errors'][$hash]) ? $state['errors'][$hash] : [];
			$errors[] = time();
			$cutoff = time() - self::ERROR_CACHE_TTL;
			$errors = array_values(array_filter($errors, function ($time) use ($cutoff) {
				return $time > $cutoff;
			}));

			if ($errors === []) {
				unset($state['errors'][$hash]);
			} else {
				$state['errors'][$hash] = $errors;
			}

			return $state;
		});

		$state = self::state();
		$errors = isset($state['errors'][$hash]) && is_array($state['errors'][$hash]) ? $state['errors'][$hash] : [];
		$cutoff = time() - self::ERROR_CACHE_TTL;
		$recentErrors = array_filter($errors, function ($time) use ($cutoff) {
			return $time > $cutoff;
		});
		if (count($recentErrors) >= self::ERROR_THRESHOLD) {
			self::forceRefresh();
		}
	}

	/**
	 * Подтвердить, что домен жив (успешный боевой запрос) — снимает fail_streak, обновляет last_ok, чистит errors[].
	 */
	public static function markDomainSuccess($domain): void
	{
		if (!is_string($domain) || $domain === '') {
			return;
		}

		$now = time();
		$hash = md5($domain);
		$state = self::state();
		$entries = isset($state['available_domains']) && is_array($state['available_domains'])
			? $state['available_domains']
			: [];
		$hasErrors = !empty($state['errors'][$hash]);
		foreach ($entries as $raw) {
			$entry = self::normalizeDomainEntry(is_array($raw) ? $raw : []);
			if ($entry['domain'] === $domain
				&& $entry['available']
				&& $entry['fail_streak'] === 0
				&& ($now - $entry['last_ok']) < self::MIN_PROBE_INTERVAL_SEC
				&& !$hasErrors) {
				return;
			}
		}

		DomainCache::withLock(function (array $state) use ($domain, $hash) {
			$entries = isset($state['available_domains']) && is_array($state['available_domains'])
				? $state['available_domains']
				: [];
			$found = false;
			foreach ($entries as $i => $raw) {
				$entry = self::normalizeDomainEntry(is_array($raw) ? $raw : []);
				if ($entry['domain'] !== $domain) {
					continue;
				}
				$entry['available'] = true;
				$entry['fail_streak'] = 0;
				$entry['last_ok'] = time();
				$entry['last_error_kind'] = null;
				$entries[$i] = $entry;
				$found = true;
				break;
			}

			if (!$found) {
				if (!self::isConfiguredDomain($domain)) {
					return $state;
				}
				$entry = self::emptyDomainEntry($domain);
				$entry['available'] = true;
				$entry['fail_streak'] = 0;
				$entry['last_ok'] = time();
				$entries[] = $entry;
			}

			$state['available_domains'] = array_values($entries);
			unset($state['errors'][$hash]);

			return $state;
		});
	}

	/**
	 * CAgent: фоновый пересчёт ping (TTL 3600 / 45). Не блокирует пользовательский запрос.
	 *
	 * @return string
	 */
	public static function agentRefreshDomains(): string
	{
		if (!\CModule::IncludeModule('vettich.sp3')) {
			return self::AGENT_NAME;
		}

		static::refreshIfDue();

		return self::AGENT_NAME;
	}

	private static function ensureAgent(): void
	{
		if (!class_exists('\CAgent')) {
			return;
		}

		static $ensured = false;
		if ($ensured) {
			return;
		}
		$ensured = true;

		if (\CAgent::GetList([], ['MODULE_ID' => Module::MID, 'NAME' => self::AGENT_NAME])->Fetch()) {
			return;
		}

		\CAgent::AddAgent(self::AGENT_NAME, Module::MID, 'N', self::AGENT_INTERVAL_SEC);
	}

	/**
	 * Горячий путь: синхронная проба только при пустом кэше (cold start).
	 */
	private static function updateDomainsIfNeed() {
		self::ensureAgent();

		$state = self::state();
		$lastCheck = (int)($state['last_check_domains'] ?? 0);
		$available = $state['available_domains'] ?? [];
		$cold = $lastCheck === 0 && (empty($available) || !is_array($available));
		if ($cold) {
			static::checkAndUpdateDomains();
		}
	}

	/**
	 * Периодический пересчёт: длинный TTL когда все живы, короткий при деградации.
	 */
	private static function refreshIfDue() {
		$state = self::state();
		$lastCheck = (int)($state['last_check_domains'] ?? 0);
		$currentTime = time();
		$ttl = self::hasDegradedDomain($state) ? self::DEGRADED_RECHECK_TTL : self::HEALTHY_RECHECK_TTL;

		if (!$lastCheck || ($currentTime - $lastCheck) > $ttl) {
			static::checkAndUpdateDomains();

			return;
		}

		$availableDomains = static::getAvailableDomains();
		if (empty($availableDomains)) {
			static::checkAndUpdateDomains();
		}
	}

	/**
	 * Проверяет доступность доменов и обновляет кэш
	 */
	private static function checkAndUpdateDomains() {
		$claimed = false;
		DomainCache::withLock(function (array $state) use (&$claimed) {
			$lastCheck = (int)($state['last_check_domains'] ?? 0);
			if ($lastCheck && (time() - $lastCheck) < self::MIN_PROBE_INTERVAL_SEC) {
				return $state;
			}
			$state['last_check_domains'] = time();
			$claimed = true;

			return $state;
		});
		if (!$claimed) {
			return;
		}

		$domains = Config::domains();
		$checkUri = Config::availableCheckUri();

		if (empty($domains) || !is_array($domains)) {
			DomainCache::withLock(function (array $state) {
				$state['available_domains'] = [];
				$state['last_check_domains'] = time();

				return $state;
			});

			return;
		}

		$toProbe = [];
		foreach ($domains as $domain) {
			if (!is_string($domain) || empty(trim($domain))) {
				continue;
			}
			$toProbe[] = $domain;
		}

		if ($toProbe === []) {
			DomainCache::withLock(function (array $state) {
				$state['available_domains'] = [];
				$state['last_check_domains'] = time();

				return $state;
			});

			return;
		}

		$probeStartedAt = time();
		$probes = self::probeDomains($toProbe, $checkUri);

		$failed = [];
		foreach ($probes as $domain => $probe) {
			if (empty($probe['ok'])) {
				$failed[] = $domain;
			}
		}

		if ($failed !== []) {
			usleep(self::PROBE_RETRY_DELAY_US);
			$retry = self::probeDomains($failed, $checkUri);
			foreach ($retry as $domain => $probe) {
				$probes[$domain] = $probe;
			}
		}

		DomainCache::withLock(function (array $state) use ($probes, $probeStartedAt) {
			$prevByDomain = [];
			$prevList = isset($state['available_domains']) && is_array($state['available_domains'])
				? $state['available_domains']
				: [];
			foreach ($prevList as $raw) {
				$entry = self::normalizeDomainEntry(is_array($raw) ? $raw : []);
				if ($entry['domain'] !== '') {
					$prevByDomain[$entry['domain']] = $entry;
				}
			}

			$results = [];
			foreach ($probes as $domain => $probe) {
				$prev = $prevByDomain[$domain] ?? self::emptyDomainEntry($domain);
				$results[] = self::applyProbeToEntry($prev, $probe, $probeStartedAt);
			}

			$state['available_domains'] = $results;
			$state['last_check_domains'] = time();

			return $state;
		});
	}

	/**
	 * @param array $prev нормализованная запись
	 * @param array{ok: bool, ping: int, kind: string|null} $probe
	 *
	 * @return array{domain: string, ping: int, available: bool, fail_streak: int, last_ok: int, last_error_kind: string|null}
	 */
	private static function applyProbeToEntry(array $prev, array $probe, int $probeStartedAt): array
	{
		$entry = self::normalizeDomainEntry($prev, (string)($probe['domain'] ?? $prev['domain'] ?? ''));

		if (!empty($probe['ok'])) {
			if ((int)$probe['ping'] > 0) {
				$entry['ping'] = (int)$probe['ping'];
			}
			$entry['available'] = true;
			$entry['fail_streak'] = 0;
			$entry['last_ok'] = time();
			$entry['last_error_kind'] = null;

			return $entry;
		}

		if ($entry['last_ok'] >= $probeStartedAt) {
			return $entry;
		}

		$kind = $probe['kind'] === 'timeout' ? 'timeout' : 'hard';
		$inc = $kind === 'hard' ? self::FAIL_STREAK_HARD : self::FAIL_STREAK_SOFT;
		$entry['fail_streak'] = (int)$entry['fail_streak'] + $inc;
		$entry['last_error_kind'] = $kind;
		$entry['available'] = $entry['fail_streak'] < self::FAIL_STREAK_THRESHOLD;

		return $entry;
	}

	/**
	 * @param string[] $domains
	 *
	 * @return array<string, array{domain: string, ok: bool, ping: int, errno: int, http_code: int, kind: string|null}>
	 */
	private static function probeDomains(array $domains, string $checkUri): array
	{
		$mh = curl_multi_init();
		$handles = [];

		foreach ($domains as $domain) {
			$url = rtrim($domain, '/').'/'.ltrim($checkUri, '/');
			$ch = static::createCurlHandle($url);
			$handles[] = ['handle' => $ch, 'domain' => $domain];
			curl_multi_add_handle($mh, $ch);
		}

		$running = null;
		do {
			curl_multi_exec($mh, $running);
			if ($running > 0) {
				curl_multi_select($mh, 1.0);
			}
		} while ($running > 0);

		$results = [];
		foreach ($handles as $item) {
			$ch = $item['handle'];
			$domain = $item['domain'];
			$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$errno = (int)curl_errno($ch);
			$pingMs = (int)round(((float)curl_getinfo($ch, CURLINFO_TOTAL_TIME)) * 1000);
			$ok = $errno === 0 && $httpCode === 200;
			$kind = $ok ? null : self::classifyProbeFailure($errno);

			$results[$domain] = [
				'domain'    => $domain,
				'ok'        => $ok,
				'ping'      => $ok ? $pingMs : self::UNAVAILABLE_PING,
				'errno'     => $errno,
				'http_code' => $httpCode,
				'kind'      => $kind,
			];

			curl_multi_remove_handle($mh, $ch);
			curl_close($ch);
		}

		curl_multi_close($mh);

		return $results;
	}

	private static function classifyProbeFailure(int $errno): string
	{
		if ($errno === CURLE_OPERATION_TIMEDOUT) {
			return 'timeout';
		}
		if ($errno !== 0) {
			return 'hard';
		}

		// Соединение есть, но /ping не 200 — не сеть, и не доказательство жизни.
		return 'timeout';
	}

	/**
	 * Создает curl handle для проверки домена
	 *
	 * @param string $url Полный URL для проверки
	 * @return resource curl handle
	 */
	private static function createCurlHandle($url) {
		$ch = curl_init($url);

		curl_setopt_array($ch, [
			CURLOPT_NOBODY => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
			CURLOPT_TIMEOUT => self::HTTP_TIMEOUT,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_USERAGENT => 'DomainSelector/1.0',
		]);

		return $ch;
	}

	/**
	 * Принудительно обновить данные о доменах (игнорируя кэш)
	 */
	private static function forceRefresh() {
		static::checkAndUpdateDomains();
	}
}
