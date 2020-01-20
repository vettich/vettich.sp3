<?php
$prolog_admin_after = false;
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Module;

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
				$value = '';
				foreach ($res['urls'] as $url) {
					$value .= str_replace('{value}', $url, $tpl);
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
				$value = Module::m('POST_STATUS_'.$value);
			},
		],
	],
	'hiddenParams' => ['id'],
	'dontEditAll' => true,
	/* 'editLink' => 'vettich.sp3.posts_edit.php', */
	/* 'buttons' => [ */
	/* 	'add' => 'buttons\newLink:#VDF_ADD#:/bitrix/admin/vettich.sp3.posts_edit.php?back_url\=vettich.sp3.posts_list.php', */
	/* ], */
]))->render();

require(__DIR__.'/../include/epilog_authorized_page.php');
