<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');
IncludeModuleLangFile(__FILE__);

CModule::IncludeModule('vettich.devform');
CModule::IncludeModule('vettich.sp3');
use vettich\sp3\Module;

$validateToken = Module::isAuth(true);
if ($validateToken['response'] == true) {
	header("Location: /bitrix/admin/vettich.sp3.user.php");
	exit;
}

\CJSCore::Init(['vettich_sp3_script']);

$userEmail = $USER->GetEmail();

\vettich\devform\Module::pushMessPrefix('VETTICH_SP3_');
(new \vettich\devform\AdminForm('devform', [
	'pageTitle' => '#.START_USE#',
	'tabs' => [
		[
			'name' => '#.AUTH#',
			'title' => '#.AUTH_TITLE#',
			'params' => [
				'lusername' => 'text:#.USERNAME#:'.$userEmail,
				'lpassword' => 'password:#.PASSWORD#',
				'login_btn' => 'divbutton::#.LOGIN_BTN#:onclick=VettichSP3.login()',
				'lresult' => 'html::',
			],
		],
		[
			'name' => '#.REG#',
			'title' => '#.REG_TITLE#',
			'params' => [
				'rusername' => 'text:#.USERNAME#:'.$userEmail,
				'rpassword' => 'password:#.PASSWORD#',
				'rpassword2' => 'password:#.PASSWORD_CONFIRM#',
				'reg_btn' => 'divbutton::#.REG_BTN#:onclick=VettichSP3.signup()',
				'rresult' => 'html::',
			],
		],
	],
	'data' => null,
]))->render();
\vettich\devform\Module::popMessPrefix();
?>

<div class="adm-info-message" style="display:block">
	<pre style="white-space: pre-wrap;"><?=Module::m('START_HELP')?></pre>
</div>
<?php
require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
?>
