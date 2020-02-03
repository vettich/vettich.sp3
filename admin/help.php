<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
IncludeModuleLangFile(__FILE__);

CModule::IncludeModule('vettich.sp3');
use vettich\sp3\Module;

$APPLICATION->SetTitle(Module::m('PAGE_TITLE'));

?>

	<h2><?=Module::m('ABOUT_MODULE_TITLE')?></h2>
	<div class="vsp3-content"><?=Module::m('ABOUT_MODULE')?></div>

	<h2><?=Module::m('USER_TITLE')?></h2>
	<div class="vsp3-content"><?=Module::m('USER')?></div>

	<h3><?=Module::m('USER_BALANCE_TITLE')?></h3>
	<div class="vsp3-content"><?=Module::m('USER_BALANCE')?></div>

<?php
require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
