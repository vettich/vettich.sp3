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
				'vk_login' => 'divbutton::#.VK_LOGIN_BTN#:onclick=VettichSP3.vkLogin()',
				'vk_login_res' => 'html::',
			],
		],
	],
	'data' => null,
]))->render();

require(__DIR__.'/../include/epilog_authorized_page.php');
