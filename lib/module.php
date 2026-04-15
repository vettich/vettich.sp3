<?php
namespace vettich\sp3;

/**
* main class of module
*/
class Module
{
	const MID                = 'vettich.sp3';
	const LOG_FILE           = 'log.txt';
	private static $_version = null;

	/**
	* функция возвращает языковой текст
	* по ключу VETTICH_SP3_<ключ>
	*/
	public static function m($key, $replaces=[])
	{
		$m = GetMessage('VETTICH_SP3_' . $key, $replaces);
		return $m ?: $key;
	}

	public static function version()
	{
		if (self::$_version === null) {
			$arModuleVersion = [];
			include VETTICH_SP3_DIR . '/install/version.php';
			if (empty($arModuleVersion['VERSION'])) {
				self::$_version = '3.0.0';
			} else {
				self::$_version = $arModuleVersion['VERSION'];
			}
		}
		return self::$_version;
	}

	public static function log($data, $options=[])
	{
		Log::debug($data, $options);
	}

	public static function isAuth($withValidate=false)
	{
		if ($withValidate) {
			$res = Api::validateToken();
			if (is_array($res) && array_key_exists('response', $res) && $res['response'] === false) {
				Config::purgeTokenFromAllStorages();
			}
			return $res;
		}
		$token = Api::token();
		return !empty($token);
	}

	public static function convertToSiteCharset($data)
	{
		global $APPLICATION;
		return $APPLICATION->ConvertCharsetArray($data, 'UTF-8', SITE_CHARSET);
	}

	public static function convertToUtf8($data)
	{
		global $APPLICATION;
		return $APPLICATION->ConvertCharsetArray($data, SITE_CHARSET, 'UTF-8');
	}

	public static function cleanConditions($data)
	{
		$arResult = [];
		foreach ((array)$data as $key => $value) {
			if (!empty($value['field']) && $value['field'] != 'none') {
				$arResult[] = $value;
			}
		}
		return $arResult;
	}

	private static function hasGroupRight($level)
	{
		global $APPLICATION, $USER;
		if ($USER->IsAdmin()) return true;
		return $APPLICATION->GetGroupRight(self::MID) >= $level;
	}

	public static function hasGroupRead()
	{
		return self::hasGroupRight('R');
	}

	public static function hasGroupWrite()
	{
		return self::hasGroupRight('W');
	}

	/**
	 * Callback OAuth ParrotPoster: GET ?code=… → GraphQL exchange_code → сохранение токена, cron.
	 * Вызывать только из vettich.sp3.start_use.php, до проверки PP-токена.
	 *
	 * @return string|null null — параметра code нет; иначе строка ошибки для вывода. Успех: редирект на user.php и exit.
	 */
	public static function tryExchangeParrotPosterOAuthCode()
	{
		if (empty($_GET['code']) || !is_string($_GET['code'])) {
			return null;
		}
		global $USER;
		if (!$USER->IsAuthorized() || !self::hasGroupWrite()) {
			return self::m('PP_OAUTH_EXCHANGE_ACCESS_DENIED');
		}

		$res = Api::exchangeAuthCode($_GET['code']);
		if (!empty($res['error'])) {
			Log::debug(['tryExchangeParrotPosterOAuthCode' => $res]);
			return (string)($res['error']['msg'] ?? 'exchange failed');
		}

		Config::setToken($res['token']);
		Api::createCron();
		LocalRedirect('/bitrix/admin/vettich.sp3.user.php');
		exit;
	}

	/**
	 * При удалении модуля: снять cron и отозвать токен на стороне ParrotPoster, если был сохранён токен.
	 * Вызывается из install/index.php до UnInstallDB (пока опции модуля ещё в БД).
	 */
	public static function tryLogoutOnUninstall(): void
	{
		if (!self::isAuth()) {
			return;
		}
		Api::deleteCron();
		Api::logout();
		DomainCache::deleteFile();
	}
}
