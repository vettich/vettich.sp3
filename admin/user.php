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
if ($days_left <= 0) {
	$days_left = 0;
}

$color = '#1ea81e';
if ($days_left < 7) {
	$color = '#c42222';
} elseif ($days_left < 20) {
	$color = '#c88d1e';
}

\CJSCore::Init(['vettich_sp3_script']);

$dateEsc = htmlspecialcharsbx(date('d.m.Y', $expiry_at));
$colorEsc = htmlspecialcharsbx($color);
if ($days_left <= 0) {
	$expiryHtml = '<span style="color: '.$colorEsc.'">'.$dateEsc.'</span> ('
		.htmlspecialcharsbx(Module::m('EXPIRED_AT_VALUE'))
		.', <a href="/bitrix/admin/vettich.sp3.tariffs.php">'
		.htmlspecialcharsbx(Module::m('EXPIRED_RENEW'))
		.'</a>)';
} else {
	$expiryHtml = '<span style="color: '.$colorEsc.'">'.$dateEsc.'</span> ('
		.htmlspecialcharsbx(Module::m('EXPIRY_AT_VALUE', [
			'#days#' => (string) (int) $days_left,
		]))
		.')';
}

(new \vettich\sp3\devform\AdminForm('devform', [
	'pageTitle' => '#.USER_INFO#',
	'tabs' => [
		[
			'name' => '#.USER#',
			'title' => '#.USER_TITLE#',
			'params' => [
				'username' => [
					'type' => 'plaintext',
					'title' => '#.USERNAME#',
					'value' => $user['username'] ?: '<anonymous>',
				],
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
					'type' => 'html',
					'title' => '#.EXPIRY_AT#',
					'value' => $expiryHtml,
				],
				'tariff_list' => [
					'type' => 'link',
					'title' => '#.TARIFF_LIST#',
					'link' => '/bitrix/admin/vettich.sp3.tariffs.php',
					'text' => Module::m('GOTO'),
				],
				'logout_btn' => Module::hasGroupWrite() ? 'divbutton::#.LOGOUT#:onclick=VettichSP3.logout()' : '',
				'logout_res' => 'html::',
			],
		],
	],
	'data' => null,
]))->render();

require(__DIR__.'/../include/epilog_authorized_page.php');
