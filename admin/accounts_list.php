<?php
$prolog_admin_after = false;
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Module;
use vettich\sp3\devform\Module as DevModule;
use vettich\sp3\Api;

$res = Api::me();
$user = $res['response'] ?: [];
$addBtn = [
	'type' => 'buttons\newLink',
	'title' => '#VDF_ADD# ('.Module::m('ACCOUNTS_LIMIT_USAGE', [
		'#max#' => $user['tariff_limits']['accounts_cnt'],
		'#current#' => $user['tariff_limits']['accounts_current_cnt'],
	]).')',
	'default_value' => '/bitrix/admin/vettich.sp3.accounts_add.php?back_url\=vettich.sp3.accounts_list.php',
	'params' => [
		'data-title' => DevModule::mess('#VDF_ADD# ('.Module::m('ACCOUNTS_LIMIT_USAGE', [
			'#max#' => $user['tariff_limits']['accounts_cnt'],
		]).')'),
		'data-max' => $user['tariff_limits']['accounts_cnt'],
		'data-url' => '/bitrix/admin/vettich.sp3.accounts_add.php?back_url\=vettich.sp3.accounts_list.php',
	],
];
if ($user['tariff_limits']['accounts_current_cnt'] >= $user['tariff_limits']['accounts_cnt']) {
	$addBtn['params']['disabled'] = 'disabled';
	$addBtn['params']['class'] = 'adm-btn adm-btn-save adm-btn-add adm-btn-disabled';
	$addBtn['default_value'] = '#';
}

?>
<script>
function updatePageTitle() {
	setTimeout(function() {
		var title = $('a#add').data('title') || '';
		var max = $('a#add').data('max') || '0';
		var accCnt = $('.adm-list-table-row').length;
		if (accCnt < max) {
			$('a#add')
				.attr('href', $('a#add').data('url') || '#')
				.prop('disabled', false)
				.removeClass('adm-btn-disabled');
		}
		$('a#add').text(title.split('#current#').join(accCnt));
	}, 200);
}
</script>
<?php

(new \vettich\sp3\devform\AdminList('#.ACCOUNTS_LIST_PAGE#', 'sp3_accounts', [
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
				$tpl = '<a href="{href}" target="_blank">{value}</a>'.
					'<script>updatePageTitle()</script>';
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
		'add' => $addBtn,
	],
]))->render();

require(__DIR__.'/../include/epilog_authorized_page.php');
