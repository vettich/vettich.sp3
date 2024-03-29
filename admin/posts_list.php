<?php
$prolog_admin_after = false;
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Module;
use vettich\sp3\Api;

CModule::IncludeModule('iblock');

$params = [
	'data'        => new vettich\sp3\db\Posts(),
	'hideFilters' => true,
	'idKey'       => 'id',
	'params'      => [
		'id'           => 'plaintext:ID',
		'fields[text]' => [
			'type'          => 'plaintext',
			'title'         => '#.POST_TEXT#',
			'sortKey'       => 'fields.text',
			'defaultValue'  => '<empty>',
			'on renderView' => function (&$obj, &$value) {
				if (empty($value)) {
					return;
				}
				$ind = strpos($value, "\n");
				if ($ind !== false) {
					$value = substr($value, 0, $ind);
				}
			},
		],
		'fields[images]' => [
			'type'          => 'html',
			'title'         => '#.POST_PICTURE#',
			'on renderView' => function (&$obj, &$value, $arRow=[]) {
				$tpl = '<img src="{value}" width=40 height=40 /> ';
				$value = '';
				foreach ($arRow['fields']['image_urls'] as $url) {
					$value .= str_replace('{value}', $url, $tpl);
				}
			},
		],
		'fields[extra][id]' => [
			'type'          => 'html',
			'title'         => '#.IBLOCK_ELEM#',
			'on renderView' => function (&$obj, &$value, $arRow=[]) {
				if (empty($value)) {
					$value = '-';
					return;
				}
				$rs = \CIBlockElement::GetList([], ['ID' => $value], false, false, ['ID', 'NAME']);
				if ($ar = $rs->GetNext()) {
					/* $iblockId = $arRow['extra_fields']['iblock_id']; */
					$iblockId = $arRow['fields']['extra']['iblock_id'];
					$iblockType = \CIBlock::GetArrayByID($iblockId, 'IBLOCK_TYPE_ID');
					$value = "[$value] <a href=\"/bitrix/admin/iblock_element_edit.php?type=$iblockType&IBLOCK_ID=$iblockId&ID=$value\">$ar[NAME]</a>";
				}
			},
		],
		'publish_at' => 'datetime:#.POST_PUBLISH_AT#',
		'status'     => [
			'type'          => 'plaintext',
			'title'         => '#.POST_STATUS#',
			'on renderView' => function (&$obj, &$value) {
				$key = empty($value) ? 'SUCCESS' : strtoupper($value);
				$value = Module::m('POST_STATUS_'.$key);
			},
		],
		'networks[accounts]' => [
			'type'          => 'plaintext',
			'title'         => '#.SOCIALS#',
			'on renderView' => function (&$obj, &$value) {
				$res = [];
				$types = [];
				/* $accs = (new vettich\sp3\db\Accounts(['filter' => ['id' => $value]]))->getListType(); */
				$accs = vettich\sp3\db\Accounts::getByIds($value);
				foreach ($accs as $acc) {
					$types[$acc['type']] = true;
				}
				foreach ($types as $t => $i) {
					$res[] = Module::m(strtoupper($t));
				}
				$value = implode(', ', $res);
			},
		],
	],
	'actions' => Module::hasGroupWrite() ? ['edit', 'delete'] : [],
	'hiddenParams' => ['id', 'fields[images]'],
	'dontEditAll'  => true,
	'editLink'     => 'vettich.sp3.posts_edit.php',
	'sortDefault'  => ['publish_at' => 'desc'],
];

if (!Module::hasGroupWrite()) {
	$params['buttons'] = ['add' => ''];
}

(new \vettich\sp3\PostsAdminList('#.POSTS_LIST_PAGE#', 'sp3_posts', $params))->render();

require(__DIR__.'/../include/epilog_authorized_page.php');
