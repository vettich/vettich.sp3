<?php
namespace vettich\sp3;

IncludeModuleLangFile(__FILE__);

use Bitrix\Main\EventManager;

class Events
{
	const ADD     = 'add';
	const POP_ADD = 'pop_add';
	const UPDATE  = 'update';
	const DELETE  = 'delete';

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
		TemplateHelpers::publish($arFields, ['event' => self::ADD]);
	}

	public static function beforeIblockElementUpdateHandler($arFields = [])
	{
		TemplateHelpers::cacheIblockElement($arFields['ID'], $arFields['IBLOCK_ID']);
	}

	public static function afterIblockElementUpdateHandler($arFields = [])
	{
		TemplateHelpers::update($arFields, ['event' => self::UPDATE]);
	}

	public static function afterIblockElementDeleteHandler($arFields = [])
	{
		TemplateHelpers::delete($arFields, ['event' => self::DELETE]);
	}

	public static function adminListDisplayHandler(&$list)
	{
		if (!in_array($GLOBALS['APPLICATION']->GetCurPage(), self::$iblockPages)) {
			return;
		}

		\CJSCore::Init(['vettich_sp3_script']);

		$list->arActions['VETTICH_SP3_IBLOCK_MENU_SEND'] = [
			'name'   => Module::m('IBLOCK_MENU_SEND'),
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
				$subtype      = substr($v->id, 0, 1); // S - SECTION, E - ELEMENT
				$emptySubtype = !in_array($subtype, ['E', 'S']);
				$id           = !$emptySubtype ? substr($v->id, 1) : $v->id;
				$queries      = ['IBLOCK_ID' => $v->arRes["IBLOCK_ID"]];
				if ($emptySubtype) {
					$subtype = strpos($curPage, 'section') !== false ? 'S' : 'E';
				}
				if ($subtype == 'E') {
					$queries['ELEMS'] = [$id];
				} else {
					$queries['SECTIONS'] = [$id];
				}
				$q              = \CUtil::PhpToJSObject($queries);
				$actionKey      = (SM_VERSION <= '18.0.4' ? 'ACTION' : 'ONCLICK');
				$arnewActions[] = [
					'GLOBAL_ICON' => 'vettich-sp3-publish',
					'TEXT'        => Module::m('IBLOCK_MENU_SEND'),
					'ACTION'      => 'VettichSP3.MenuSendWithTemplate('.$q.');',
				];
				$arnewActions[] = ['SEPARATOR' => true];
				$arnewActions[] = $act;
			}
			$v->aActions = $arnewActions;
		}
	}

	public static function beforePrologHandler()
	{
		$arElem = self::popPostElemID();
		if (!$arElem) {
			self::unRegPageStart();
			return;
		}
		$arFields = ['ID' => $arElem[0], 'IBLOCK_ID' => $arElem[1]];
		TemplateHelpers::publish($arFields, ['event' => self::POP_ADD]);
	}

	public static function regPageStart()
	{
		RegisterModuleDependences('main', 'OnBeforeProlog', 'vettich.sp3', get_class(), 'beforePrologHandler');
	}

	public static function unRegPageStart()
	{
		UnRegisterModuleDependences('main', 'OnBeforeProlog', 'vettich.sp3', get_class(), 'beforePrologHandler');
	}

	public static function pushPostElemID($ID, $IBLOCK_ID)
	{
		$arElems = unserialize(\COption::GetOptionString('vettich.sp3', 'post_elems', ''));
		if (empty($arElems)) {
			$arElems = [];
		}
		$arElems[] = [$ID, $IBLOCK_ID];
		\COption::SetOptionString('vettich.sp3', 'post_elems', serialize($arElems));
	}

	public static function popPostElemID()
	{
		$arElems = unserialize(\COption::GetOptionString('vettich.sp3', 'post_elems', ''));
		if (empty($arElems)) {
			return null;
		}
		$ret = array_shift($arElems);
		\COption::SetOptionString('vettich.sp3', 'post_elems', serialize($arElems));
		return $ret;
	}
}
