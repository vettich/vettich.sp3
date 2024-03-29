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

require(__DIR__.'/../include/check_curl.php');

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
	header("Location: /bitrix/admin/vettich.sp3.user.php");
	exit;
}

\CJSCore::Init(['vettich_sp3_script']);

$userEmail = $USER->GetEmail();

devform\Module::pushMessPrefix('VETTICH_SP3_');
(new devform\AdminForm('devform', [
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
				'passgen_btn' => 'divbutton::#.PASSGEN_BTN#:onclick=VettichSP3.passGen()',
				'rpassword' => 'password:#.PASSWORD#',
				'rpassword2' => 'password:#.PASSWORD_CONFIRM#',
				'politika' => 'checkbox:#.POLITIKA_CONFIRM#',
				'reg_btn' => 'divbutton::#.REG_BTN#:onclick=VettichSP3.signup()',
				'rresult' => 'html::',
			],
		],
		[
			'name' => '#.FORGOT_PASSWORD#',
			'title' => '#.FORGOT_PASSWORD_TITLE#',
			'params' => [
				'fusername' => 'text:#.USERNAME#:'.$userEmail,
				'forgot_btn' => 'divbutton::#.FORGOT_BTN#:onclick=VettichSP3.forgotPassword()',
				'fresult' => 'html::',
			],
		],
	],
	'data' => null,
]))->render();
devform\Module::popMessPrefix();
?>

<div class="adm-info-message" style="display:block">
	<pre style="white-space: pre-wrap;"><?=Module::m('START_HELP')?></pre>
</div>
<?php
require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
?>
