<?php

namespace vettich\sp3;

/**
 * Статические настройки модуля (дефолты в коде, переопределение списка доменов через define VETTICH_SP3_DOMAINS — CSV URL).
 */
class Config
{
	private const OPTION_TOKEN = 'api_token';
	private const OPTION_MENU_SHOW_CHANGEFEED = 'menu_show_changefeed';

	/** Дефолтные endpoint'ы ParrotPoster (если не задан VETTICH_SP3_DOMAINS). */
	private const DEFAULT_DOMAINS = [
		'https://parrotposter.com',
		'https://mirror-pl.parrotposter.com',
	];

	private const DEFAULT_AVAILABLE_CHECK_URI = '/api/v1/ping';
	private const DEFAULT_API_URI             = '/api/v1';
	private const DEFAULT_GRAPHQL_API_URI     = '/api/graphql';
	private const DEFAULT_FRONT_BASE_URI      = '/plugin/bx';
	private const DEFAULT_LOG                 = false;
	private const DEFAULT_REMOTE_LOG          = true;

	private static $tokenMigrated = false;

	/**
	 * @return string[]
	 */
	public static function domains(): array
	{
		if (defined('VETTICH_SP3_DOMAINS') && is_string(VETTICH_SP3_DOMAINS) && VETTICH_SP3_DOMAINS !== '') {
			$parts = preg_split('/\s*,\s*/', VETTICH_SP3_DOMAINS, -1, PREG_SPLIT_NO_EMPTY);

			return $parts ? array_values($parts) : self::DEFAULT_DOMAINS;
		}

		return self::DEFAULT_DOMAINS;
	}

	public static function availableCheckUri(): string
	{
		return self::DEFAULT_AVAILABLE_CHECK_URI;
	}

	public static function apiUri(): string
	{
		return self::DEFAULT_API_URI;
	}

	public static function graphqlApiUri(): string
	{
		return self::DEFAULT_GRAPHQL_API_URI;
	}

	public static function frontBaseUri(): string
	{
		return self::DEFAULT_FRONT_BASE_URI;
	}

	public static function logEnabled(): bool
	{
		return (bool)self::DEFAULT_LOG;
	}

	public static function remoteLogEnabled(): bool
	{
		return (bool)self::DEFAULT_REMOTE_LOG;
	}

	/**
	 * Удаляет ключ token из legacy JSON (config.local.json / config.json), не трогая остальные поля.
	 */
	private static function stripTokenFromLegacyConfigFile(string $path): void
	{
		if (!is_file($path) || !is_readable($path)) {
			return;
		}
		$j = json_decode((string)file_get_contents($path), true);
		if (!is_array($j) || !array_key_exists('token', $j)) {
			return;
		}
		unset($j['token']);
		$encoded = json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if ($encoded === false) {
			return;
		}
		@file_put_contents($path, $encoded."\n", LOCK_EX);
	}

	/**
	 * Очистка токена в COption и в legacy-файлах, чтобы не сработал migrateLegacyTokenIfNeeded с тем же невалидным токеном.
	 */
	public static function purgeTokenFromAllStorages(): void
	{
		self::setToken('');
		self::stripTokenFromLegacyConfigFile(VETTICH_SP3_DIR.'/config.local.json');
		self::stripTokenFromLegacyConfigFile(VETTICH_SP3_DIR.'/config.json');
	}

	private static function migrateLegacyTokenIfNeeded(): void
	{
		if (self::$tokenMigrated) {
			return;
		}
		self::$tokenMigrated = true;

		if (\COption::GetOptionString(Module::MID, self::OPTION_TOKEN, '') !== '') {
			return;
		}

		$paths = [VETTICH_SP3_DIR.'/config.local.json', VETTICH_SP3_DIR.'/config.json'];
		foreach ($paths as $p) {
			if (!is_file($p)) {
				continue;
			}
			$j = json_decode((string)file_get_contents($p), true);
			if (!is_array($j) || empty($j['token']) || !is_string($j['token'])) {
				continue;
			}
			\COption::SetOptionString(Module::MID, self::OPTION_TOKEN, $j['token']);
			break;
		}
	}

	public static function getToken(): string
	{
		self::migrateLegacyTokenIfNeeded();
		return (string)\COption::GetOptionString(Module::MID, self::OPTION_TOKEN, '');
	}

	public static function setToken(string $token): void
	{
		\COption::SetOptionString(Module::MID, self::OPTION_TOKEN, $token);
	}

	public static function showChangefeedMenu(): bool
	{
		$v = (string)\COption::GetOptionString(Module::MID, self::OPTION_MENU_SHOW_CHANGEFEED, 'Y');
		return $v !== 'N';
	}

	public static function setShowChangefeedMenu(bool $enabled): void
	{
		\COption::SetOptionString(
			Module::MID,
			self::OPTION_MENU_SHOW_CHANGEFEED,
			$enabled ? 'Y' : 'N'
		);
	}

	/**
	 * Сводка настроек (для отладки)
	 *
	 * @return array<string, mixed>
	 */
	public static function getConfig(): array
	{
		return [
			'domains'              => self::domains(),
			'available_check_uri'  => self::availableCheckUri(),
			'api_uri'              => self::apiUri(),
			'front_base_uri'       => self::frontBaseUri(),
			'log'                  => self::logEnabled(),
			'remote_log'           => self::remoteLogEnabled(),
			'menu_show_changefeed' => self::showChangefeedMenu(),
			// 'proxy'                => self::proxy(),
			'token_set'            => self::getToken() !== '',
		];
	}
}
