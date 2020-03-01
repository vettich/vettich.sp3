<?php
IncludeModuleLangFile(__FILE__);

define('VETTICH_SP3_DIR', __DIR__);

\CJSCore::RegisterExt('vettich_sp3_script', [
	'js' => '/bitrix/js/vettich.sp3/script.js',
	'lang' => str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__).'/lang/'.LANGUAGE_ID.'/script.js.php',
	'rel' => ['popup'],
]);
