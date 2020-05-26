<?php
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Module;

if ($userTariffExpired) {
	$APPLICATION->SetTitle(Module::m('ACCOUNTS_ADD_PAGE')); ?>
	<div class="adm-info-message" style="display:block">
		<?=Module::m('ACC_ADD_TARIFF_EXPIRED')?>
	</div><?php
	require(__DIR__.'/../include/epilog_authorized_page.php');
	exit;
}
if ($user['tariff_limits']['accounts_current_cnt'] >= $user['tariff_limits']['accounts_cnt']) {
	$APPLICATION->SetTitle(Module::m('ACCOUNTS_ADD_PAGE')); ?>
	<div class="adm-info-message" style="display:block">
		<?=Module::m('ACC_ADD_LIMITED')?>
	</div><?php
	require(__DIR__.'/../include/epilog_authorized_page.php');
	exit;
}

\CJSCore::Init(['vettich_sp3_script']);

(new \vettich\sp3\devform\AdminForm('devform', [
	'pageTitle' => '#.ACCOUNTS_ADD_PAGE#',
	'tabs' => [
		[
			'name' => '#.VK#',
			'title' => '#.VK_ADD_TITLE#',
			'params' => [
				'vk_login' => 'divbutton::#.VK_LOGIN_BTN#:onclick=VettichSP3.connectAccount("vk")',
				'vk_login_res' => 'html::',
			],
		],
		[
			'name' => '#.OK#',
			'title' => '#.OK_ADD_TITLE#',
			'params' => [
				'ok_login' => 'divbutton::#.OK_LOGIN_BTN#:onclick=VettichSP3.connectAccount("ok")',
				'ok_login_res' => 'html::',
			],
		],
		[
			'name' => '#.FB#',
			'title' => '#.FB_ADD_TITLE#',
			'params' => [
				'fb_login' => 'divbutton::#.FB_LOGIN_BTN#:onclick=VettichSP3.connectAccount("fb")',
				'fb_login_res' => 'html::',
			],
		],
		[
			'name' => '#.INSTA#',
			'title' => '#.INSTA_ADD_TITLE#',
			'params' => [
				'insta_username' => 'text:#.INSTA_USERNAME#',
				'insta_password' => 'password:#.INSTA_PASSWORD#',
				'insta_proxy' => 'text:#.INSTA_PROXY#:placeholder=#.INSTA_PROXY_PLACEHOLDER#',
				'insta_code' => [
					'type' => 'text',
					'title' => '#.INSTA_CODE#',
					'template' => '<tr id="{id}-wrap" style="display:none"><td width="40%"><label for="{id}">{title}{help}</label></td><td width="60%">{content}</td></tr>',
				],
				'insta_login' => 'divbutton::#.INSTA_LOGIN_BTN#:onclick=VettichSP3.connectInsta()',
				'insta_login_res' => 'html::',
			],
		],
		[
			'name' => '#.TG#',
			'title' => '#.TG_ADD_TITLE#',
			'params' => [
				'tg_bot_token' => 'text:#.TG_BOT_TOKEN#:params=[placeholder=#.TG_BOT_TOKEN_PLACEHOLDER#]',
				'tg_username' => 'text:#.TG_USERNAME#:params=[placeholder=#.TG_USERNAME_PLACEHOLDER#]',
				'tg_login' => 'divbutton::#.TG_LOGIN_BTN#:onclick=VettichSP3.connectTg()',
				'tg_login_res' => 'html::',
				'tg_help' => 'note:#.TG_HELP_BLOCK#',
			],
		],
	],
	'data' => null,
]))->render();

require(__DIR__.'/../include/epilog_authorized_page.php');
