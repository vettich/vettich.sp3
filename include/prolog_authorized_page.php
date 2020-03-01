<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

CModule::IncludeModule('vettich.sp3');
use vettich\sp3\Module;
use vettich\sp3\Api;

if (!CModule::IncludeModule('vettich.devform')) {
	require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php"); ?>
	<div class="adm-info-message" style="display:block">
		<?=Module::m('DEVFORM_NOT_INSTALLED')?>
	</div>
	<?php
	require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
	exit;
}

$validateTokenRes = Module::isAuth(true);
if ($validateTokenRes['error'] &&
	$validateTokenRes['error']['code'] == Api::SERVER_UNAVAILABLE) {
	require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php"); ?>
	<div class="adm-info-message" style="display:block">
		<?=Module::m('SERVER_UNAVAILABLE')?>
	</div>
	<?php
	require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
}
if ($validateTokenRes['response'] == false) {
	header("Location: /bitrix/admin/vettich.sp3.start_use.php");
	exit;
}

/* if ($prolog_admin_after !== false) { */
	require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
/* } */

$res = Api::me();
$user = $res['response'] ?: [];
$userTariffExpired = (strtotime($user['tariff']['expiry_at']) - strtotime('now')) < 0;
if ($userTariffExpired) {
	?><div class="adm-info-message" style="display:block">
		<?=Module::m('TARIFF_EXPIRED')?>
	</div><?php
}

\CJSCore::Init(['vettich_sp3_script']);

\vettich\devform\Module::pushMessPrefix('VETTICH_SP3_');
