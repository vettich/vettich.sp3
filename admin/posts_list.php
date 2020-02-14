<?php
$prolog_admin_after = false;
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Module;

CModule::IncludeModule('iblock');

(new \vettich\devform\AdminList('#.POSTS_LIST_PAGE#', 'sTableID', [
	'data' => new vettich\sp3\db\Posts(),
	'hideFilters' => true,
	'idKey' => 'id',
	'params' => [
		'id' => 'plaintext:ID',
		'fields[text]' => [
			'type' => 'plaintext',
			'title' => '#.POST_TEXT#',
			'sortKey' => 'fields.text',
			'defaultValue' => '<empty>',
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
			'type' => 'html',
			'title' => '#.POST_PICTURE#',
			'on renderView' => function (&$obj, &$value) {
				$tpl = '<img src="{value}" width=40 height=40 /> ';
				$res = Module::api()->getFilesURL($value);
				if (!empty($res['error'])) {
					return;
				}
				$value = '';
				foreach ($res['response']['urls'] as $url) {
					$value .= str_replace('{value}', $url, $tpl);
				}
			},
		],
		'extra_fields[id]' => [
			'type' => 'html',
			'title' => '#.IBLOCK_ELEM#',
			'on renderView' => function (&$obj, &$value, $arRow=[]) {
				if (empty($value)) {
					$value = '-';
					return;
				}
				$rs = \CIBlockElement::GetList([], ['ID' => $value], false, false, ['ID', 'NAME']);
				if ($ar = $rs->GetNext()) {
					$iblockId = $arRow['extra_fields']['iblock_id'];
					$iblockType = CIBlock::GetArrayByID($iblockId, 'IBLOCK_TYPE_ID');
					$value = "[$value] <a href=\"/bitrix/admin/iblock_element_edit.php?type=$iblockType&IBLOCK_ID=$iblockId&ID=$value\">$ar[NAME]</a>";
				}
			},
		],
		'publish_at' => 'datetime:#.POST_PUBLISH_AT#',
		'status' => [
			'type' => 'plaintext',
			'title' => '#.POST_STATUS#',
			'on renderView' => function (&$obj, &$value) {
				if (empty($value)) {
					$value = 'SUCCESS';
				}
				/* $value = Module::m('POST_STATUS_'.$value); */
			},
		],
		'networks[accounts]' => [
			'type' => 'plaintext',
			'title' => '#.SOCIALS#',
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
	'actions' => ['edit', 'copy', 'delete'],
	'hiddenParams' => ['id', 'fields[images]'],
	'dontEditAll' => true,
	'editLink' => 'vettich.sp3.posts_edit.php',
	'sortDefault' => ['publish_at' => 'desc'],
	/* 'buttons' => [ */
	/* 	'add' => 'buttons\newLink:#VDF_ADD#:/bitrix/admin/vettich.sp3.posts_edit.php?back_url\=vettich.sp3.posts_list.php', */
	/* ], */
]))->render();

require(__DIR__.'/../include/epilog_authorized_page.php');
