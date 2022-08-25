<?php
$prolog_admin_after = false;
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Module;

(new \vettich\sp3\devform\AdminList('#.TEMPLATES_PAGE_TITLE#', 'vap_templates_list', [
	'dbClass' => 'vettich\sp3\db\template',
	'params' => [
		'ID' => 'number',
		'NAME' => 'textlink:#VDF_NAME#',
		'IBLOCK_TYPE' => [
			'type' => 'text',
			'title' => '#.POST_IBLOCK_TYPE#',
			/* 'on renderView' => ['Vettich\SP\Module', 'onRenderViewIblockType'], */
		],
		'IBLOCK_ID' => [
			'type' => 'text',
			'title' => '#.POST_IBLOCK_ID#',
			/* 'on renderView' => ['Vettich\SP\Module', 'onRenderViewIblockId'], */
		],
	],
	'actions' => Module::hasGroupWrite() ? ['edit', 'copy', 'delete'] : [],
	'dontEdit' => ['ID', 'IBLOCK_TYPE', 'IBLOCK_ID'],
	'dontEditAll' => true,
	'linkEditInsert' => ['NAME'],
	'buttons' => !Module::hasGroupWrite() ? ['add' => ''] : [],
]))->render();

require(__DIR__.'/../include/epilog_authorized_page.php');
