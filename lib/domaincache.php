<?php

namespace vettich\sp3;

/**
 * Один JSON-файл в bitrix/cache: результаты ping доменов и учёт ошибок запросов.
 */
class DomainCache
{
	private const CACHE_SUBDIR = '/bitrix/cache/vettich.sp3';

	private const CACHE_FILENAME = 'domains.json';

	public static function cacheDir(): string
	{
		$root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
		if ($root === '' && defined('VETTICH_SP3_DIR')) {
			$root = rtrim(dirname(VETTICH_SP3_DIR, 3), '/');
		}
		if ($root === '' || $root === '.') {
			$cwd = rtrim((string)getcwd(), '/');
			if ($cwd !== '' && $cwd !== '.') {
				$root = $cwd;
			}
		}

		return $root.self::CACHE_SUBDIR;
	}

	public static function filePath(): string
	{
		return self::cacheDir().'/'.self::CACHE_FILENAME;
	}

	public static function deleteFile() {
		$path = self::filePath();
		if (file_exists($path)) {
			@unlink($path);
		}
	}

	/**
	 * @return array{available_domains: array, last_check_domains: int, errors: array<string, array<int, int>>, pp_down_until: int}
	 */
	public static function emptyState(): array
	{
		return [
			'available_domains'  => [],
			'last_check_domains' => 0,
			'errors'             => [],
			'pp_down_until'      => 0,
		];
	}

	/**
	 * @param array $raw
	 *
	 * @return array{available_domains: array, last_check_domains: int, errors: array}
	 */
	public static function normalizeState(array $raw): array
	{
		$s = self::emptyState();
		if (isset($raw['available_domains']) && is_array($raw['available_domains'])) {
			$s['available_domains'] = $raw['available_domains'];
		}
		if (isset($raw['last_check_domains']) && is_numeric($raw['last_check_domains'])) {
			$s['last_check_domains'] = (int)$raw['last_check_domains'];
		}
		if (isset($raw['errors']) && is_array($raw['errors'])) {
			$s['errors'] = $raw['errors'];
		}
		if (isset($raw['pp_down_until']) && is_numeric($raw['pp_down_until'])) {
			$s['pp_down_until'] = (int)$raw['pp_down_until'];
		}

		return $s;
	}

	/**
	 * @return array{available_domains: array, last_check_domains: int, errors: array}
	 */
	public static function load(): array
	{
		$path = self::filePath();
		if (is_file($path) && filesize($path) > 0) {
			$json = @file_get_contents($path);
			$d    = json_decode((string)$json, true);

			return is_array($d) ? self::normalizeState($d) : self::emptyState();
		}

		return self::emptyState();
	}

	/**
	 * @param array{available_domains?: array, last_check_domains?: int, errors?: array} $state
	 */
	public static function save(array $state): void
	{
		$state = self::normalizeState($state);
		$dir   = self::cacheDir();
		if (function_exists('CheckDirPath')) {
			CheckDirPath($dir.'/');
		} elseif (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}

		$encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if ($encoded === false) {
			return;
		}

		$path = self::filePath();
		$tmp  = $path.'.tmp';
		if (file_put_contents($tmp, $encoded) === false) {
			return;
		}
		@rename($tmp, $path);
	}

	/**
	 * @param callable(array): array $fn принимает состояние, возвращает новое
	 */
	public static function withLock(callable $fn): void
	{
		$dir = self::cacheDir();
		if (function_exists('CheckDirPath')) {
			CheckDirPath($dir.'/');
		} elseif (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}

		$path = self::filePath();
		$fp   = @fopen($path, 'c+b');
		if (!$fp) {
			return;
		}
		if (!flock($fp, LOCK_EX)) {
			fclose($fp);

			return;
		}

		$json  = stream_get_contents($fp);
		$d     = json_decode((string)$json, true);
		$state = is_array($d) ? self::normalizeState($d) : self::emptyState();
		$newState = $fn($state);
		if (!is_array($newState)) {
			flock($fp, LOCK_UN);
			fclose($fp);

			return;
		}

		$newState = self::normalizeState($newState);
		$encoded  = json_encode($newState, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if ($encoded === false) {
			flock($fp, LOCK_UN);
			fclose($fp);

			return;
		}

		ftruncate($fp, 0);
		rewind($fp);
		fwrite($fp, $encoded);
		fflush($fp);
		flock($fp, LOCK_UN);
		fclose($fp);
	}
}
