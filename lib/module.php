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
}
