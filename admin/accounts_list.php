<?php
$prolog_admin_after = false;
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Module;

(new \vettich\devform\AdminList('#.ACCOUNTS_LIST_PAGE#', 'sTableID', [
	'data' => new vettich\sp3\db\Accounts(),
	'hideFilters' => true,
	'idKey' => 'id',
	'params' => [
		'id' => 'plaintext:ID',
		'photo' => [
			'type' => 'html',
			'title' => '#.ACCOUNT_PHOTO#',
			'on renderView' => function (&$obj, &$value) {
				$tpl = '<img src="{value}" width=30 height=30 />';
				$value = str_replace('{value}', $value, $tpl);
			},
		],
		'name' => [
			'type' => 'html',
			'title' => '#.ACCOUNT_NAME#',
			'on renderView' => function ($obj, &$value, $arRes) {
				$tpl = '<a href="{href}" target="_blank">{value}</a>';
				$href = $arRes['link'];
				$value = str_replace(['{href}', '{value}'], [$href, $value], $tpl);
			},
		],
		'type' => [
			'type' => 'plaintext',
			'title' => '#.ACCOUNT_TYPE#',
			'on renderView' => function (&$obj, &$value) {
				$value = Module::m(strtoupper($value));
			},
		],
		'link' => [
			'type' => 'plaintext',
			'title' => '#.ACCOUNT_LINK#',
			'on renderView' => function (&$obj, &$value) {
				$value = "<a href=\"$value\" target=\"_blank\">$value</a>";
			},
		],
	],
	'on actionsBuild' => function ($obj, $row, $arActions) {
		unset($arActions['edit']);
		return $arActions;
	},
	'hiddenParams' => ['id'],
	'dontEditAll' => true,
	'buttons' => [
		'add' => 'buttons\newLink:#VDF_ADD#:/bitrix/admin/vettich.sp3.accounts_add.php?back_url\=vettich.sp3.accounts_list.php',
	],
]))->render();

require(__DIR__.'/../include/epilog_authorized_page.php');
