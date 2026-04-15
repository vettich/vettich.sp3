<?php

namespace vettich\sp3;

class DomainSelector {
	private const CACHE_TTL = 3600; // 1 час в секундах
	private const HTTP_TIMEOUT = 2; // общий timeout в секундах
	private const CONNECT_TIMEOUT = 1; // timeout соединения в секундах
	private const UNAVAILABLE_PING = -1; // значение ping для недоступных доменов
	private const ERROR_CACHE_TTL = 300; // окно учёта ошибок (сек)
	private const ERROR_THRESHOLD = 2; // сколько ошибок за окно считаем проблемой

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
	 * Получить домен с автоматическим переключением при ошибках
	 */
	public static function getReliableDomain() {
		$domain = static::getBestDomain();
		if (!$domain) {
			return false;
		}

		if (static::hasRecentErrors($domain)) {
			static::forceRefresh();
			$domain = static::getBestDomain();
		}

		return $domain;
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

		// Сортируем по ping (по возрастанию)
		usort($availableDomains, function ($a, $b) {
			return ($a['ping'] ?? PHP_INT_MAX) <=> ($b['ping'] ?? PHP_INT_MAX);
		});

		// Возвращаем домен с наименьшим ping
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
			return isset($domain['ping'], $domain['domain'])
				&& is_numeric($domain['ping'])
				&& (int)$domain['ping'] > 0
				&& !self::hasRecentErrors($domain['domain']);
		});

		if (empty($availableDomains)) {
			return [];
		}

		usort($availableDomains, function ($a, $b) {
			return ((int)$a['ping']) <=> ((int)$b['ping']);
		});

		return array_values(array_map(static fn($d) => $d['domain'], $availableDomains));
	}

	/**
   * Получить все доступные домены
   *
   * @return array|false Массив объектов {domain, ping, available}
   */
	private static function getAvailableDomains() {
		$availableDomains = self::state()['available_domains'];

		if (empty($availableDomains)) {
			return false;
		}

		// Фильтруем доступные домены (ping > 0)
		$availableDomains = array_filter($availableDomains, function ($domain) {
			return isset($domain['ping']) && $domain['ping'] > 0 && isset($domain['domain']);
		});

		return $availableDomains;
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

	private static function updateDomainsIfNeed() {
		// Проверяем, не устарели ли кэшированные данные
		$lastCheck = (int)(self::state()['last_check_domains'] ?? 0);
		$currentTime = time();

		// Если данные устарели или их нет, выполняем проверку
		if (!$lastCheck || ($currentTime - $lastCheck) > self::CACHE_TTL) {
			static::checkAndUpdateDomains();

			return;
		}

		// Если недоступен ни один домен - принудительно обновляем
		$availableDomains = static::getAvailableDomains();
		if (empty($availableDomains)) {
			static::checkAndUpdateDomains();
		}
	}

	/**
	 * Проверяет доступность доменов и обновляет кэш
	 */
	private static function checkAndUpdateDomains() {
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

		$results = [];

		// Создаем multi curl для параллельной проверки
		$mh = curl_multi_init();
		$handles = [];

		foreach ($domains as $domain) {
			if (!is_string($domain) || empty(trim($domain))) {
				continue;
			}

			$url = rtrim($domain, '/').'/'.ltrim($checkUri, '/');
			$ch = static::createCurlHandle($url);
			$handles[] = ['handle' => $ch, 'domain' => $domain];
			curl_multi_add_handle($mh, $ch);
		}

		if (empty($handles)) {
			DomainCache::withLock(function (array $state) {
				$state['available_domains'] = [];
				$state['last_check_domains'] = time();

				return $state;
			});
			curl_multi_close($mh);

			return;
		}

		// Выполняем все запросы параллельно
		$running = null;
		do {
			curl_multi_exec($mh, $running);
			if ($running > 0) {
				curl_multi_select($mh, 1.0);
			}
		} while ($running > 0);

		// Обрабатываем результаты
		foreach ($handles as $item) {
			$ch = $item['handle'];
			$domain = $item['domain'];

			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$ping = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000; // конвертируем в миллисекунды
			$result = [];
			$result['domain'] = $domain;

			if ($httpCode === 200) {
				$result['ping'] = (int)round($ping);
				$result['available'] = true;
			}
			else {
				$result['ping'] = self::UNAVAILABLE_PING;
				$result['available'] = false;
			}

			$results[] = $result;

			curl_multi_remove_handle($mh, $ch);
			curl_close($ch);
		}

		curl_multi_close($mh);

		DomainCache::withLock(function (array $state) use ($results) {
			$state['available_domains'] = $results;
			$state['last_check_domains'] = time();

			return $state;
		});
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
			CURLOPT_NOBODY => true, // Используем HEAD запрос
			CURLOPT_FOLLOWLOCATION => false, // Не следовать редиректам для скорости
			CURLOPT_SSL_VERIFYPEER => false, // Не проверять SSL для скорости
			CURLOPT_SSL_VERIFYHOST => false, // Не проверять SSL host для скорости
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
	 * Полезно для ручного обновления при изменении списка доменов
	 */
	private static function forceRefresh() {
		static::checkAndUpdateDomains();
	}
}
