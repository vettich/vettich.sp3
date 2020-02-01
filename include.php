<?php
namespace vettich\sp3;

use \vettich\devform\Module as DevFormModule;

IncludeModuleLangFile(__FILE__);

define('VETTICH_SP3_DIR', __DIR__);

\CJSCore::RegisterExt('vettich_sp3_script', [
	'js' => '/bitrix/js/vettich.sp3/script.js',
	'rel' => ['popup'],
]);

/**
 * main class of module
 */
class Module
{
	const MID = 'vettich.sp3';
	const LOG_FILE = 'log.txt';
	private static $_apiInstance = null;
	private static $_version = null;

	/**
	 * функция возвращает языковой текст
	 * по ключу VETTICH_SP3_<ключ>
	 */
	public static function m($key, $replaces=[])
	{
		$m = GetMessage('VETTICH_SP3_' . $key, $replaces);
		if (empty($m)) {
			return $key;
		}
		return $m;
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

	public static function log($data, $params=[])
	{
		/* ini_set('xdebug.var_display_max_children', $params['max_children'] ?: 5); */
		/* ini_set('xdebug.var_display_max_data', $params['max_data'] ?: 24); */
		/* ini_set('xdebug.var_display_max_depth', $params['max_depth'] ?: 4); */
		/* ini_set('xdebug.cli_color', 0); */
		/* ob_start(); */
		/* var_dump($data); */
		/* $text = ob_get_contents(); */
		/* ob_end_clean(); */
		$text = var_export($data, true);
		$date = date('Y/m/d H:i:s');
		$tracen = $params['trace_n'] ?: 2;
		$trace = debug_backtrace(2, $tracen);
		$filename = $trace[0]['file'];
		$filename = str_replace(VETTICH_SP3_DIR, '', $filename);
		$filename = str_replace($_SERVER['DOCUMENT_ROOT'], '', $filename);
		$line = $trace[$tracen-2]['line'];
		$funcname = $trace[$tracen-1]['function'];
		$text = "[$date] $filename($line) in $funcname:\n$text\n";
		error_log($text, 3, VETTICH_SP3_DIR.'/'.self::LOG_FILE);
	}

	public static function isAuth($withValidate=false)
	{
		if ($withValidate) {
			return self::api()->validateToken();
		}
		$token = self::api()->token();
		return !empty($token);
	}

	public static function api(): Api
	{
		if (self::$_apiInstance == null) {
			self::$_apiInstance = new Api(VETTICH_SP3_DIR.'/config.json');
		}
		return self::$_apiInstance;
	}

	public function convertToSiteCharset($data)
	{
		global $APPLICATION;
		return $APPLICATION->ConvertCharsetArray($data, 'UTF-8', SITE_CHARSET);
	}

	public function convertToUtf8($data)
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
}
