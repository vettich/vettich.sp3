<?php
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Module;

$res = Module::api()->me();
$user = $res['response'] ?: [];

(new \vettich\devform\AdminForm('devform', [
	'pageTitle' => '#.USER_INFO#',
	'tabs' => [
		[
			'name' => '#.USER#',
			'title' => '#.USER_TITLE#',
			'params' => [
				'username' => 'plaintext:#.USERNAME#:'.$user['username'],
				'logout_btn' => 'divbutton::#.LOGOUT#:onclick=VettichSP3.logout()',
				'logout_res' => 'html::',
			],
		],
	],
	'data' => null,
]))->render();

require(__DIR__.'/../include/epilog_authorized_page.php');
