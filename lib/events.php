<?php
namespace vettich\sp3;

IncludeModuleLangFile(__FILE__);

use Bitrix\Main\EventManager;

class Events
{
	public static $iblockPages = [
		'/bitrix/admin/iblock_element_admin.php',
		'/bitrix/admin/iblock_list_admin.php',
		'/bitrix/admin/iblock_section_admin.php',
		'/bitrix/admin/cat_product_list.php',
		'/bitrix/admin/cat_product_admin.php',
		'/bitrix/admin/cat_section_admin.php',
	];

	public static function afterIblockElementAddHandler($arFields = [])
	{
		TemplateHelpers::publish($arFields, ['event' => 'add']);
	}

	public static function beforeIblockElementUpdateHandler($arFields = [])
	{
		TemplateHelpers::cacheIblockElement($arFields['ID'], $arFields['IBLOCK_ID']);
	}

	public static function afterIblockElementUpdateHandler($arFields = [])
	{
		TemplateHelpers::update($arFields, ['event' => 'update']);
	}

	public static function afterIblockElementDeleteHandler($arFields = [])
	{
		TemplateHelpers::delete($arFields, ['event' => 'delete']);
	}

	public static function adminListDisplayHandler(&$list)
	{
		if (!in_array($GLOBALS['APPLICATION']->GetCurPage(), self::$iblockPages)) {
			return;
		}

		\CJSCore::Init(['vettich_sp3_script']);

		$list->arActions['VETTICH_SP3_IBLOCK_MENU_SEND'] = [
			'name' => Module::m('IBLOCK_MENU_SEND'),
			'action' => 'VettichSP3.MenuSendWithTemplate('.\CUtil::PhpToJSObject(['IBLOCK_ID' => $_GET['IBLOCK_ID']]).');',
		];

		$curPage = $GLOBALS['APPLICATION']->GetCurPage();
		foreach ((array)$list->aRows as $id => $v) {
			$arnewActions = [];
			foreach ((array)$v->aActions as $i => $act) {
				if ($act['ICON'] != 'delete') {
					$arnewActions[] = $act;
					continue;
				}
				$subtype = substr($v->id, 0, 1); // S - SECTION, E - ELEMENT
				$emptySubtype = !in_array($subtype, ['E', 'S']);
				$id = !$emptySubtype ? substr($v->id, 1) : $v->id;
				$queries = ['IBLOCK_ID' => $v->arRes["IBLOCK_ID"]];
				if ($emptySubtype) {
					$subtype = strpos($curPage, 'section') !== false ? 'S' : 'E';
				}
				if ($subtype == 'E') {
					$queries['ELEMS'] = [$id];
				} else {
					$queries['SECTIONS'] = [$id];
				}
				$q = \CUtil::PhpToJSObject($queries);
				$actionKey = (SM_VERSION <= '18.0.4' ? 'ACTION' : 'ONCLICK');
				$arnewActions[] = [
					'GLOBAL_ICON' => 'vettich-sp3-publish',
					'TEXT' => Module::m('IBLOCK_MENU_SEND'),
					'ACTION' => 'VettichSP3.MenuSendWithTemplate('.$q.');',
					/* 'MENU' => [ */
					/* 	[ */
					/* 		'TEXT' => Module::m('IBLOCK_MENU_SEND_WITH_TEMPLATE'), */
					/* 		$actionKey => 'VettichSP3.MenuSendWithTemplate('.$q.');', */
					/* 	], */
					/* 	[ */
					/* 		'TEXT' => Module::m('IBLOCK_MENU_SEND_CUSTOM'), */
					/* 		$actionKey => 'VettichSP3.MenuSendCustom('.$q.');', */
					/* 	], */
					/* ], */
				];
				$arnewActions[] = ['SEPARATOR' => true];
				$arnewActions[] = $act;
			}
			$v->aActions = $arnewActions;
		}
	}

	public static function beforePrologHandler()
	{
		if (!in_array($GLOBALS['APPLICATION']->GetCurPage(), self::$iblockPages) &&
			$_REQUEST['action'] == 'VETTICH_SP3_MENU_TEMPLATES') {
			return;
		}
	}
}
