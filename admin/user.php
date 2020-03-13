<?php
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Api;
use vettich\sp3\Module;

$res = Api::getTariff($user['tariff']['id'] ?: $user['tariff']['code']);
$tariff = $res['response'] ?: [];
$tariff = Module::convertToSiteCharset($tariff);

$expiry_at = strtotime($user['tariff']['expiry_at']);
$time_left = $expiry_at - strtotime('now');
$days_left = round((($time_left/24)/60)/60);
$color = '#1ea81e';
if ($days_left < 7) {
	$color = '#c42222';
} elseif ($days_left < 20) {
	$color = '#c88d1e';
}

\CJSCore::Init(['vettich_sp3_script']);

(new \vettich\devform\AdminForm('devform', [
	'pageTitle' => '#.USER_INFO#',
	'tabs' => [
		[
			'name' => '#.USER#',
			'title' => '#.USER_TITLE#',
			'params' => [
				'username' => 'plaintext:#.USERNAME#:'.$user['username'],
				/* 'balance' => 'plaintext:#.BALANCE#:$999', */
				'tariff' => [
					'type' => 'plaintext',
					'title' => '#.TARIFF_NAME#',
					'value' => $tariff['name'],
				],
				'limit' => [
					'type' => 'plaintext',
					'title' => '#.ACCOUNTS_LIMIT#',
					'value' => Module::m('ACCOUNTS_LIMIT_USAGE', [
						'#max#' => $user['tariff_limits']['accounts_cnt'],
						'#current#' => $user['tariff_limits']['accounts_current_cnt'],
					]),
				],
				'expiry_at' => [
					'type' => 'plaintext',
					'title' => '#.EXPIRY_AT#',
					'value' => Module::m('EXPIRY_AT_VALUE', [
						'#date#' => date('d.m.Y', $expiry_at),
						'#days#' => $days_left,
						'#color#' => $color,
					]),
				],
				'tariff_list' => [
					'type' => 'link',
					'title' => '#.TARIFF_LIST#',
					'link' => '/bitrix/admin/vettich.sp3.tariffs.php',
					'text' => Module::m('GOTO'),
				],
				'logout_btn' => 'divbutton::#.LOGOUT#:onclick=VettichSP3.logout()',
				'logout_res' => 'html::',
			],
		],
	],
	'data' => null,
]))->render();

require(__DIR__.'/../include/epilog_authorized_page.php');
