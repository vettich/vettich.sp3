<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once(__DIR__.'/../include/check_module_admin_read.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');
IncludeModuleLangFile(__FILE__);

// disable all errors or warnings
ini_set('display_errors', false);

CModule::IncludeModule('vettich.sp3');
use vettich\sp3\Module;
use vettich\sp3\Api;
use vettich\sp3\devform;
use vettich\sp3\View;

require(__DIR__.'/../include/check_curl.php');

$ppOAuthExchangeErr = Module::tryExchangeParrotPosterOAuthCode();
if ($ppOAuthExchangeErr !== null && $ppOAuthExchangeErr !== '') {
	$ppOAuthExchangeErr = Module::convertToSiteCharset($ppOAuthExchangeErr);
	echo '<div class="adm-info-message" style="display:block">'.htmlspecialcharsbx($ppOAuthExchangeErr).'</div>';
}

$validateTokenRes = Module::isAuth(true);
if (($validateTokenRes['response'] ?? false) === true) {
	header("Location: /bitrix/admin/vettich.sp3.user.php");
	exit;
}

$APPLICATION->SetTitle(Module::m('START_USE'));
View::embed_front('auth');
