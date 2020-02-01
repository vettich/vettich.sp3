<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

CModule::IncludeModule('vettich.devform');
CModule::IncludeModule('vettich.sp3');
use vettich\sp3\Module;

$validateToken = Module::isAuth(true);
if ($validateToken['response'] == false) {
	header("Location: /bitrix/admin/vettich.sp3.start_use.php");
	exit;
}

if ($prolog_admin_after !== false) {
	require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
}

\CJSCore::Init(['vettich_sp3_script']);

\vettich\devform\Module::pushMessPrefix('VETTICH_SP3_');
