<?php

/**
 * CLI-стабы ядра Битрикса для smoke-тестов (`php bin/test-bx*.php`).
 *
 * Не подключает include.php и не грузит ядро. CAgent намеренно не объявляется:
 * DomainSelector::ensureAgent() тогда no-op.
 *
 * Изолирует DOCUMENT_ROOT во временный каталог, чтобы DomainCache не писал
 * в реальный сайт / случайный cwd.
 *
 * Usage:
 *   require_once __DIR__.'/test-bootstrap.php';
 *   require_once dirname(__DIR__).'/lib/domaincache.php';
 */

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "test-bootstrap.php is CLI-only\n");
	exit(1);
}

if (!defined('VETTICH_SP3_DIR')) {
	define('VETTICH_SP3_DIR', dirname(__DIR__));
}

if (!defined('VETTICH_SP3_DOMAINS')) {
	define('VETTICH_SP3_DOMAINS', 'https://a.example.test,https://b.example.test');
}

if (!defined('LANGUAGE_ID')) {
	define('LANGUAGE_ID', 'ru');
}

if (!defined('BX_ROOT')) {
	define('BX_ROOT', '/bitrix');
}

if (!function_exists('IncludeModuleLangFile')) {
	function IncludeModuleLangFile($file, $lang = false)
	{
	}
}

if (!function_exists('GetMessage')) {
	function GetMessage($name, $aReplace = false)
	{
		$text = (string)$name;
		if (is_array($aReplace)) {
			foreach ($aReplace as $k => $v) {
				$text = str_replace((string)$k, (string)$v, $text);
			}
		}

		return $text;
	}
}

if (!class_exists('CModule', false)) {
	class CModule
	{
		public static function IncludeModule($moduleName)
		{
			return true;
		}
	}
}

if (!class_exists('COption', false)) {
	class COption
	{
		/** @var array<string, array<string, string>> */
		public static $store = [];

		public static function GetOptionString($module, $name, $default = '')
		{
			$module = (string)$module;
			$name   = (string)$name;
			if (isset(self::$store[$module][$name])) {
				return self::$store[$module][$name];
			}

			return (string)$default;
		}

		public static function SetOptionString($module, $name, $value)
		{
			self::$store[(string)$module][(string)$name] = (string)$value;
		}

		public static function RemoveOption($module, $name = '')
		{
			$module = (string)$module;
			if ($name === '' || $name === null) {
				unset(self::$store[$module]);

				return;
			}
			unset(self::$store[$module][(string)$name]);
		}
	}
}

if (!class_exists('CJSCore', false)) {
	class CJSCore
	{
		public static function RegisterExt($name, $arExt = [])
		{
		}

		public static function Init($arExt = [])
		{
		}
	}
}

/**
 * Сбрасывает in-memory COption между кейсами.
 */
function pp_test_reset_options()
{
	COption::$store = [];
}

/**
 * Корень изолированного DOCUMENT_ROOT (пустая строка, если задан снаружи).
 */
function pp_test_document_root()
{
	return isset($GLOBALS['pp_test_document_root'])
		? (string)$GLOBALS['pp_test_document_root']
		: '';
}

if (!function_exists('assert_true')) {
	function assert_true($name, $condition)
	{
		if (!$condition) {
			fwrite(STDERR, "FAIL: {$name}\n");
			exit(1);
		}
		echo "OK: {$name}\n";
	}
}

if (!function_exists('assert_eq')) {
	function assert_eq($name, $expected, $actual)
	{
		if ($expected !== $actual) {
			fwrite(STDERR, "FAIL: {$name}\n");
			fwrite(STDERR, '  expected: '.var_export($expected, true)."\n");
			fwrite(STDERR, '  actual:   '.var_export($actual, true)."\n");
			exit(1);
		}
		echo "OK: {$name}\n";
	}
}

/**
 * Вызов private/protected static метода (как в plugin-wordpress/bin/test-wp09).
 *
 * @param class-string $class
 * @param mixed        ...$args
 *
 * @return mixed
 */
function pp_test_call_private($class, $method, ...$args)
{
	$r = new ReflectionMethod($class, $method);
	$r->setAccessible(true);

	return $r->invokeArgs(null, $args);
}

/**
 * @param string $dir
 */
function pp_test_rmdir_recursive($dir)
{
	if ($dir === '' || !is_dir($dir)) {
		return;
	}
	$tmp = realpath(sys_get_temp_dir());
	$real = realpath($dir);
	if ($tmp === false || $real === false) {
		return;
	}
	$tmpPrefix = rtrim($tmp, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
	if (strpos($real.DIRECTORY_SEPARATOR, $tmpPrefix) !== 0) {
		return;
	}

	$it = new RecursiveDirectoryIterator($real, RecursiveDirectoryIterator::SKIP_DOTS);
	$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($files as $file) {
		$path = $file->getPathname();
		if ($file->isDir()) {
			@rmdir($path);
		} else {
			@unlink($path);
		}
	}
	@rmdir($real);
}

$ppTestOwnDocRoot = empty($_SERVER['DOCUMENT_ROOT']);
if ($ppTestOwnDocRoot) {
	$ppTestRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
		.DIRECTORY_SEPARATOR
		.'vettich-sp3-test-'
		.bin2hex(random_bytes(8));
	@mkdir($ppTestRoot.'/bitrix/cache/vettich.sp3', 0777, true);
	$_SERVER['DOCUMENT_ROOT'] = $ppTestRoot;
	$GLOBALS['pp_test_document_root'] = $ppTestRoot;
	register_shutdown_function(static function () use ($ppTestRoot) {
		pp_test_rmdir_recursive($ppTestRoot);
	});
} else {
	$GLOBALS['pp_test_document_root'] = '';
}
