<?php
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Module;

$GLOBALS['APPLICATION']->AddHeadScript('/bitrix/js/vettich.sp3/script.js');

(new \vettich\devform\AdminForm('devform', [
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
	],
	'data' => null,
]))->render();

require(__DIR__.'/../include/epilog_authorized_page.php');
