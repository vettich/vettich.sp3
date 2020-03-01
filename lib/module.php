<?php
namespace vettich\sp3;

use \vettich\devform\Module as DevFormModule;

/**
 * main class of module
 */
class Module
{
	const MID = 'vettich.sp3';
	const LOG_FILE = 'log.txt';
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
		if (Config::get('log') != true) {
			return;
		}
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
			return Api::validateToken();
		}
		$token = Api::token();
		return !empty($token);
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
