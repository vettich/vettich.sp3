<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
IncludeModuleLangFile(__FILE__);

CModule::IncludeModule('vettich.sp3');
use vettich\sp3\Module;

$APPLICATION->SetTitle(Module::m('HELP_PAGE_TITLE'));

\CJSCore::Init(['vettich_sp3_script']);
echo Module::m('HELP_PAGE_CONTENT');
?>
<style>
	h4 {
		color: #25282C;
	}
	.vettich-sp3-content {
		font-size: 120%;
	}
	.vettich-sp3-img {
		max-width: 100%;
		box-shadow: 0px 0px 15px 0px gray;
	}
</style>
<?php

require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
