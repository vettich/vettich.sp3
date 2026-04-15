<?php

/**
 * Проверки окружения без синтаксиса PHP 7+.
 * Подключается при установке (install/index.php), обновлении (updater.php)
 * и в админке (include/check_curl.php — страницы через prolog_* и admin/*).
 *
 * Минимальная версия PHP должна совпадать с возможностями кода в /lib (см. модуль).
 */
if (!defined('VETTICH_SP3_MIN_PHP')) {
	define('VETTICH_SP3_MIN_PHP', '7.4.0');
}

/**
 * @return string пустая строка если всё ок, иначе текст ошибки для пользователя
 */
function vettich_sp3_requirements_error_message()
{
	if (function_exists('IncludeModuleLangFile')) {
		IncludeModuleLangFile(__DIR__.'/index.php');
	}
	if (version_compare(PHP_VERSION, VETTICH_SP3_MIN_PHP, '<')) {
		$mess = GetMessage('VETTICH_SP3_REQ_PHP');
		if ($mess != '' && $mess !== null) {
			return str_replace(array('#MIN#', '#CUR#'), array(VETTICH_SP3_MIN_PHP, PHP_VERSION), $mess);
		}
		return 'Модуль vettich.sp3: требуется PHP '.VETTICH_SP3_MIN_PHP.' или новее. Сейчас: '.PHP_VERSION.'. Обновите PHP и повторите операцию.';
	}
	if (!function_exists('extension_loaded') || !extension_loaded('curl')) {
		$mess = GetMessage('VETTICH_SP3_REQ_CURL');
		return ($mess != '' && $mess !== null) ? $mess : 'Модуль vettich.sp3: необходимо расширение PHP curl.';
	}
	if (!function_exists('extension_loaded') || !extension_loaded('json')) {
		$mess = GetMessage('VETTICH_SP3_REQ_JSON');
		return ($mess != '' && $mess !== null) ? $mess : 'Модуль vettich.sp3: необходимо расширение PHP json.';
	}
	return '';
}
