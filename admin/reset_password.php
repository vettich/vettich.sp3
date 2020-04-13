<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');
IncludeModuleLangFile(__FILE__);

// disable all errors or warnings
ini_set('display_errors', false);

CModule::IncludeModule('vettich.sp3');
use vettich\sp3\Module;
use vettich\sp3\Api;
use vettich\sp3\devform;

$pingRes = Api::ping();
Module::log($pingRes);
if ($pingRes['response'] != 'pong' ||
	($pingRes['error'] &&
		$pingRes['error']['code'] == Api::SERVER_UNAVAILABLE)) {
	?>
	<div class="adm-info-message" style="display:block">
		<?=Module::m('SERVER_UNAVAILABLE')?>
	</div>
	<?php
	require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
}

$validateTokenRes = Module::isAuth(true);
if ($validateTokenRes['response'] == true) {
	?>
	<div class="adm-info-message" style="display:block">
		<?=Module::m('USER_IS_AUTH')?>
	</div>
	<?php
}

if (empty($_GET['token'])) {
	?>
	<div class="adm-info-message" style="display:block">
		<?=Module::m('TOKEN_EMPTY')?>
	</div>
	<?php
	require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
}

\CJSCore::Init(['vettich_sp3_script']);

$userEmail = $USER->GetEmail();

devform\Module::pushMessPrefix('VETTICH_SP3_');
(new devform\AdminForm('devform', [
	'pageTitle' => '#.RESET_PASSWORD_PAGE_TITLE#',
	'tabs' => [
		[
			'name' => '#.RESET_PASSWORD#',
			'title' => '#.RESET_PASSWORD_TITLE#',
			'params' => [
				'token' => 'hidden::'.$_GET['token'],
				'passgen_btn' => 'divbutton::#.PASSGEN_BTN#:onclick=VettichSP3.passGen()',
				'rpassword' => 'password:#.PASSWORD#',
				'rpassword2' => 'password:#.PASSWORD_CONFIRM#',
				'reg_btn' => 'divbutton::#.RESET_BTN#:onclick=VettichSP3.resetPassword()',
				'rresult' => 'html::',
			],
		],
	],
	'data' => null,
]))->render();
devform\Module::popMessPrefix();

require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
