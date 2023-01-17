<?php
namespace vettich\sp3;

use vettich\sp3\devform;
use Bitrix\Main\Type\DateTime;

class IBlockHelpers
{
	const ANY_TYPE    = 0;
	const STRING_TYPE = 1;
	const FILE_TYPE   = 2;
	const DATE_TYPE   = 3;
	const URL_TYPE    = 4;

	private static $_allPropsFor         = [];
	private static $_allPropsMacrosFor   = [];
	private static $_iblockFields        = null;
	private static $_iblockProps         = [];
	private static $_catalogFields       = [];
	private static $_iblocktypes         = null;
	private static $_iblocktypesisall    = false;
	private static $_iblockids           = [];
	private static $_iblockElemIds       = [];
	private static $_iblockSections      = [];
	private static $_iblockElementFilled = [];

	public static function allPropsFor($iblockId, $fieldsType=0)
	{
		if (empty($iblockId)) {
			return ['' => Module::m('BEFORE_IBLOCK_SELECT')];
		}
		if (isset(self::$_allPropsFor["$iblockId-$fieldsType"])) {
			return self::$_allPropsFor["$iblockId-$fieldsType"];
		}
		$result   = [];
		$result[] = [
			'label' => Module::m('MAIN_FIELDS'),
			'items' => self::iblockFields($fieldsType),
		];
		$result[] = [
			'label' => Module::m('PROPERTIES'),
			'items' => self::iblockProps($iblockId, $fieldsType),
		];
		$result[] = [
			'label' => Module::m('CATALOG_FIELDS'),
			'items' => self::catalogFields($iblockId, $fieldsType),
		];
		self::$_allPropsFor["$iblockId-$fieldsType"] = $result;
		return $result;
	}


	public static function iblockFields($fieldsType=0)
	{
		if (self::$_iblockFields == null) {
			self::$_iblockFields = [
				''                   => 'none',
				'ID'                 => Module::m('PROP_ID'),
				'CODE'               => Module::m('PROP_CODE'),
				'XML_ID'             => Module::m('PROP_XML_ID'),
				'NAME'               => Module::m('PROP_NAME'),
				'IBLOCK_ID'          => Module::m('PROP_IBLOCK_ID'),
				'IBLOCK_SECTION_ID'  => Module::m('PROP_IBLOCK_SECTION_ID'),
				'IBLOCK_CODE'        => Module::m('PROP_IBLOCK_CODE'),
				'ACTIVE'             => Module::m('PROP_ACTIVE'),
				'DATE_ACTIVE_FROM'   => Module::m('PROP_DATE_ACTIVE_FROM'),
				'DATE_ACTIVE_TO'     => Module::m('PROP_DATE_ACTIVE_TO'),
				'SORT'               => Module::m('PROP_SORT'),
				'PREVIEW_PICTURE'    => Module::m('PROP_PREVIEW_PICTURE'),
				'PREVIEW_TEXT'       => Module::m('PROP_PREVIEW_TEXT'),
				'DETAIL_PICTURE'     => Module::m('PROP_DETAIL_PICTURE'),
				'DETAIL_TEXT'        => Module::m('PROP_DETAIL_TEXT'),
				'DATE_CREATE'        => Module::m('PROP_DATE_CREATE'),
				'CREATED_BY'         => Module::m('PROP_CREATED_BY'),
				'CREATED_USER_NAME'  => Module::m('PROP_CREATED_USER_NAME'),
				'TIMESTAMP_X'        => Module::m('PROP_TIMESTAMP_X'),
				'MODIFIED_BY'        => Module::m('PROP_MODIFIED_BY'),
				'USER_NAME'          => Module::m('PROP_USER_NAME'),
				'LIST_PAGE_URL'      => Module::m('PROP_LIST_PAGE_URL'),
				'DETAIL_PAGE_URL'    => Module::m('PROP_DETAIL_PAGE_URL'),
				'SHOW_COUNTER'       => Module::m('PROP_SHOW_COUNTER'),
				'SHOW_COUNTER_START' => Module::m('PROP_SHOW_COUNTER_START'),
				'WF_COMMENTS'        => Module::m('PROP_WF_COMMENTS'),
				'WF_STATUS_ID'       => Module::m('PROP_WF_STATUS_ID'),
				'TAGS'               => Module::m('PROP_TAGS'),
			];
		}

		if ($fieldsType != self::ANY_TYPE) {
			if ($fieldsType == self::STRING_TYPE) {
				return Tools::filterByKeys(self::$_iblockFields, [
					'', 'ID', 'CODE', 'XML_ID', 'NAME',
					'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'IBLOCK_CODE',
					'ACTIVE', 'SORT', 'PREVIEW_TEXT', 'DETAIL_TEXT',
					'CREATED_USER_NAME', 'USER_NAME', 'TAGS',
				]);
			}

			if ($fieldsType == self::FILE_TYPE) {
				return Tools::filterByKeys(self::$_iblockFields, [
					'', 'PREVIEW_PICTURE', 'DETAIL_PICTURE',
				]);
			}

			if ($fieldsType == self::DATE_TYPE) {
				return Tools::filterByKeys(self::$_iblockFields, [
					'', 'DATE_ACTIVE_FROM', 'DATE_ACTIVE_TO', 'DATE_CREATE',
				]);
			}

			if ($fieldsType == self::URL_TYPE) {
				return Tools::filterByKeys(self::$_iblockFields, [
					'', 'LIST_PAGE_URL', 'DETAIL_PAGE_URL',
				]);
			}
		}

		return self::$_iblockFields;
	}

	public static function iblockProps($iblockId, $fieldsType=0)
	{
		if (empty($iblockId)) {
			return null;
		}

		if (!isset(self::$_iblockProps[$iblockId])) {
			$arProps = [
				self::ANY_TYPE    => [],
				self::STRING_TYPE => [],
				self::FILE_TYPE   => [],
				self::DATE_TYPE   => [],
				self::URL_TYPE    => [],
			];
			$rsProperties = \CIBlockProperty::GetList(
				[],
				['ACTIVE'=> 'Y', 'IBLOCK_ID'=>$iblockId]
			);
			while ($prop_fields = $rsProperties->GetNext()) {
				$str                               = "[PROPERTY_$prop_fields[CODE]] <b>$prop_fields[NAME]</b>";
				$str                               = str_replace("'", '"', $str);
				$str                               = str_replace(["\"", '&quot;', '&#34;'], "'", $str);
				$propKey                           = 'PROPERTY_'.$prop_fields['CODE'];
				$arProps[self::ANY_TYPE][$propKey] = $str;
				// var_dump($prop_fields);

				$propType = $prop_fields['PROPERTY_TYPE'];
				$userType = $prop_fields['USER_TYPE'];
				if ($propType == 'F') {
					$arProps[self::FILE_TYPE][$propKey] = $str;
				}
				if ($propType == 'S' || $propType == 'N' || $propType == 'L') {
					$arProps[self::STRING_TYPE][$propKey] = $str;
				}
				if ($propType == 'S' && in_array($userType, ['Date', 'DateTime'])) {
					$arProps[self::DATE_TYPE][$propKey] = $str;
				}
			}
			self::$_iblockProps[$iblockId] = $arProps;
		}

		return self::$_iblockProps[$iblockId][$fieldsType];
	}

	public static function catalogFields($iblockId, $fieldsType=0)
	{
		if (empty($iblockId) ||
			$fieldsType == self::FILE_TYPE ||
			$fieldsType == self::URL_TYPE) {
			return null;
		}

		if (!isset(self::$_catalogFields[$iblockId])) {
			$arProps = [];
			if (\CModule::IncludeModule('catalog')
				&& \CCatalog::GetByID($iblockId)) {
				$arProps['CATALOG_QUANTITY'] = Module::m('PROP_CAT_QUANTITY');
				$arProps['CATALOG_WEIGHT']   = Module::m('PROP_CAT_WEIGHT');
				$arProps['CATALOG_WIDTH']    = Module::m('PROP_CAT_WIDTH');
				$arProps['CATALOG_LENGTH']   = Module::m('PROP_CAT_LENGTH');
				$arProps['CATALOG_HEIGHT']   = Module::m('PROP_CAT_HEIGHT');

				$rs         = \CCatalogGroup::GetList([], [], false, false, ['ID', 'NAME_LANG']);
				$arCurrency = [];
				while ($ar = $rs->Fetch()) {
					$arProps['CATALOG_PRICE_'.$ar['ID']] = Module::m('PROP_CAT_PRICE', [
						'#TYPE#'     => $ar['NAME_LANG'],
						'#PRICE_ID#' => $ar['ID'],
					]);
					$arCurrency['CATALOG_CURRENCY_'.$ar['ID']] = Module::m('PROP_CAT_CURRENCY', [
						'#TYPE#'     => $ar['NAME_LANG'],
						'#PRICE_ID#' => $ar['ID'],
					]);
				}
				foreach ((array)$arCurrency as $key => $value) {
					$arProps[$key] = $value;
				}

				$arProps['CATALOG_DISCOUNT_NAME']        = Module::m('PROP_CAT_DISCOUNT_NAME');
				$arProps['CATALOG_DISCOUNT_ACTIVE_FROM'] = Module::m('PROP_CAT_DISCOUNT_ACTIVE_FROM');
				$arProps['CATALOG_DISCOUNT_ACTIVE_TO']   = Module::m('PROP_CAT_DISCOUNT_ACTIVE_TO');
				self::$_catalogFields[$iblockId]         = $arProps;
			}
		}

		if ($fieldsType != self::ANY_TYPE) {
			$props      = self::$_catalogFields[$iblockId];
			$dateFields = ['CATALOG_DISCOUNT_ACTIVE_FROM', 'CATALOG_DISCOUNT_ACTIVE_TO'];

			if ($fieldsType == self::DATE_TYPE) {
				return Tools::filterByKeys($props, $dateFields);
			}

			if ($fieldsType == self::STRING_TYPE) {
				return Tools::filterByUnKeys($props, $dateFields);
			}
		}

		return self::$_catalogFields[$iblockId];
	}

	public static function allPropsMacrosFor($iblockId)
	{
		if (isset(self::$_allPropsMacrosFor[$iblockId])) {
			return self::$_allPropsMacrosFor[$iblockId];
		}
		$result = self::allPropsFor($iblockId);
		foreach ((array)$result as $key => $value) {
			if (empty($key) && $key !== 0) {
				continue;
			}
			if (isset($value['items'])) {
				foreach ((array)$value['items'] as $key2 => $value2) {
					if (!$key2) {
						continue;
					}
					devform\Module::changeKey($key2, "#$key2#", $value['items']);
				}
				// $value = $result[$key];
				unset($result[$key]);
				$result[$key] = $value;
			} else {
				devform\Module::changeKey($key, "#$key#", $result);
			}
		}
		self::$_allPropsMacrosFor[$iblockId] = $result;
		return $result;
	}

	public static function prepareIblockElem($id)
	{
		if (\CModule::IncludeModule('iblock')) {
			$rs = \CIBlockElement::GetByID($id);
			if ($rs && $arFields = $rs->GetNext()) {
				self::iblockValueFill($arFields);
				return $arFields;
			}
		}
	}

	public static function iblockValueFill(&$arFields, $isFull=false, $fromCache=true)
	{
		if ($fromCache && isset(self::$_iblockElementFilled[$arFields['ID']])) {
			$arFields = self::$_iblockElementFilled[$arFields['ID']];
			return;
		}

		if ($isFull) {
			$arFields = self::iblockElemId($arFields['ID'], $arFields['IBLOCK_ID'], false, $fromCache);
		}

		$rsProp = \CIBlockElement::GetProperty($arFields['IBLOCK_ID'], $arFields['ID'], [], []);
		while ($arProp = $rsProp->GetNext()) {
			if (!isset($arFields['PROPERTY_'.$arProp['CODE']])) {
				$arFields['PROPERTY_'.$arProp['CODE']] = $arProp;
			}

			if ($arProp['MULTIPLE'] == 'Y') {
				if ($arProp['VALUE']) {
					$arFields['PROPERTY_'.$arProp['CODE']]['VALUES'][] = $arProp['VALUE'];
				}

				if ($arProp['~VALUE']) {
					$arFields['PROPERTY_'.$arProp['CODE']]['~VALUES'][] = $arProp['~VALUE'];
				}

				$arFields['PROPERTY_'.$arProp['CODE']]['VALUES_ENUM'][]    = $arProp['VALUE_ENUM'];
				$arFields['PROPERTY_'.$arProp['CODE']]['~VALUES_ENUM'][]   = $arProp['~VALUE_ENUM'];
				$arFields['PROPERTY_'.$arProp['CODE']]['VALUES_XML_ID'][]  = $arProp['VALUE_XML_ID'];
				$arFields['PROPERTY_'.$arProp['CODE']]['~VALUES_XML_ID'][] = $arProp['~VALUE_XML_ID'];
			}
		}

		$isCatalog = \CModule::IncludeModule('catalog') && \CCatalog::GetByID($arFields['IBLOCK_ID']);
		if ($isCatalog) {
			$db_res = \CCatalogProduct::GetList(
				[],
				["ID" => $arFields['ID']],
				false,
				false,
				[
					'ID',
					'QUANTITY',
					'WEIGHT',
					'WIDTH',
					'LENGTH',
					'HEIGHT',
				]
			);
			if ($ar = $db_res->Fetch()) {
				foreach ((array)$ar as $key => $value) {
					$arFields['CATALOG_'.$key] = $value;
				}

				$rs = \CCatalogGroup::GetList([], [], false, false, ['ID', 'NAME_LANG']);
				while ($ar = $rs->Fetch()) {
					$rsPrice = \CPrice::GetListEx(
						[],
						[
							'PRODUCT_ID'       => $arFields['ID'],
							'CATALOG_GROUP_ID' => $ar['ID'],
						],
						false,
						false,
						[
							'ID',
							'PRICE',
							'CURRENCY',
						]
					);
					if ($arPrice = $rsPrice->Fetch()) {
						$arFields['CATALOG_PRICE_'.$ar['ID']]    = $arPrice['PRICE'];
						$arFields['CATALOG_CURRENCY_'.$ar['ID']] = $arPrice['CURRENCY'];
					}
				}

				$rsDiscount = \CCatalogDiscount::GetList(
					[],
					[
						'PRODUCT_ID' => $arFields['ID'],
					],
					false,
					false,
					[
						'ID',
						'ACTIVE_FROM',
						'ACTIVE_TO',
						'NAME',
					]
				);
				if ($arDiscount = $rsDiscount->Fetch()) {
					$arFields['CATALOG_DISCOUNT_NAME']        = $arDiscount['NAME'];
					$arFields['CATALOG_DISCOUNT_ACTIVE_FROM'] = $arDiscount['ACTIVE_FROM'];
					$arFields['CATALOG_DISCOUNT_ACTIVE_TO']   = $arDiscount['ACTIVE_TO'];
				}
			}

			$rs = current(\CCatalogSKU::getOffersList($arFields['ID'], $arFields['IBLOCK_ID']));
			if (!empty($rs)) {
				foreach ((array)$rs as $ar) {
					$arFields['SKU'][$ar['ID']] = self::iblockElemId($ar['ID'], $ar['IBLOCK_ID']);
				}
			}
		}
		self::$_iblockElementFilled[$arFields['ID']] = $arFields;
	}

	public static function iblockTypes()
	{
		if (self::$_iblocktypes === null
			or !self::$_iblocktypesisall) {
			$rsIBlockType = \CIBlockType::GetList();
			while ($arIBlockType = $rsIBlockType->GetNext()) {
				if ($arIBType = \CIBlockType::GetByIDLang($arIBlockType["ID"], LANG)) {
					$arIBlockType['NAME']         = $arIBType['NAME'];
					$arIBlockType['SECTION_NAME'] = $arIBType['SECTION_NAME'];
					$arIBlockType['ELEMENT_NAME'] = $arIBType['ELEMENT_NAME'];
					self::$_iblocktypes[$type]    = $arIBlockType;
				}
			}
			self::$_iblocktypesisall = true;
		}
		return self::$_iblocktypes;
	}

	public static function iblockType($type)
	{
		if (self::$_iblocktypes === null
			or !isset(self::$_iblocktypes[$type])) {
			self::$_iblocktypes[$type] = null;
			$rsIBlockType              = \CIBlockType::GetByID($type);
			if ($arIBlockType = $rsIBlockType->GetNext()) {
				if ($arIBType = \CIBlockType::GetByIDLang($arIBlockType["ID"], LANG)) {
					$arIBlockType['NAME']         = $arIBType['NAME'];
					$arIBlockType['SECTION_NAME'] = $arIBType['SECTION_NAME'];
					$arIBlockType['ELEMENT_NAME'] = $arIBType['ELEMENT_NAME'];
					self::$_iblocktypes[$type]    = $arIBlockType;
				}
			}
		}
		return self::$_iblocktypes[$type];
	}

	public static function iblockId($id)
	{
		if (empty($id) or !\CModule::IncludeModule('iblock')) {
			return null;
		}
		if (!isset(self::$_iblockids[$id])) {
			self::$_iblockids[$id] = null;
			$rs                    = \CIBlock::GetByID($id);
			if ($rs = $rs->GetNext()) {
				self::$_iblockids[$id] = $rs;
			}
		}
		return self::$_iblockids[$id];
	}

	public static function iblockElemId($id, $iblockId, $isFill=true, $fromCache=true)
	{
		if (!\CModule::IncludeModule('iblock')) {
			return null;
		}

		$existsArrElemsInCache = is_array($id) && Tools::array_in_array($id, array_keys(self::$_iblockElemIds));
		$existElemInCache = !is_array($id) && !empty(self::$_iblockElemIds[$id]);
		$needFetch = !$existsArrElemsInCache || !$existElemInCache || !$fromCache;
		if ($needFetch) {
			$rs = \CIBlockElement::GetList(
				['sort'=>'asc'],
				['ID' => $id, 'IBLOCK_ID' => $iblockId]
			);
			while ($ar = $rs->GetNext()) {
				if ($isFill) {
					self::iblockValueFill($ar);
				}
				$ar['filled']                    = $isFill;
				self::$_iblockElemIds[$ar['ID']] = $ar;
			}
		}

		if (is_array($id)) {
			$result = [];
			foreach ((array)$id as $i) {
				if ($isFill && !self::$_iblockElemIds[$i]['filled']) {
					self::iblockValueFill(self::$_iblockElemIds[$i]);
				}
				$result[$i] = self::$_iblockElemIds[$i];
			}
			return $result;
		}

		if ($isFill && !self::$_iblockElemIds[$id]['filled']) {
			self::iblockValueFill(self::$_iblockElemIds[$id]);
		}
		return self::$_iblockElemIds[$id];
	}

	public static function iblockSection($id, $iblockId)
	{
		if (!\CModule::IncludeModule('iblock')) {
			return null;
		}
		if ((is_array($id) && in_array($id, array_keys(self::$_iblockSections)))
			or empty(self::$_iblockSections[$id])) {
			$rs = \CIBlockSection::GetList(
				['sort'=>'asc'],
				['ID' => $id, 'IBLOCK_ID' => $iblockId]
			);
			while ($ar = $rs->GetNext()) {
				self::$_iblockSections[$ar['ID']] = $ar;
			}
		}
		if (is_array($id)) {
			$result = [];
			foreach ((array)$id as $i) {
				$result[$i] = self::$_iblockSections[$i];
			}
			return $result;
		}
		return self::$_iblockSections[$id];
	}

	public static function cmpFields($fields, $conditions=false)
	{
		if ($fields['PUBLISH']['CONDITIONS']['ACTIVE'] == 'Y' && $fields['ACTIVE'] != 'Y') {
			return false;
		}

		if (!$conditions) {
			$conditions = $fields['CONDITIONS'];
		}

		if ($conditions) {
			$valueKeys = ['', 'VALUE', 'VALUE_XML_ID', 'XML_ID', 'VALUE_ENUM'];
			foreach ((array)$conditions as $cond) {
				$isRight = false;
				foreach ($valueKeys as $valKey) {
					$macro = [$cond['field']];
					if (!empty($valKey)) {
						$macro = [$cond['field'], $valKey];
					}

					$fieldVal = TextProcessor::macroValue($macro, $fields);
					if (self::isDatetime($cond['field'], $fields)) {
						$fieldVal = (new DateTime($fieldVal))->getTimestamp();
						$condVal = (new DateTime($cond['value']))->getTimestamp();
						$isRight = self::cmp($fieldVal, $cond['cmp'], $condVal);
					} else {
						$isRight = self::cmp($fieldVal, $cond['cmp'], $cond['value']);
					}

					if ($isRight) {
						break;
					}
				}

				if (!$isRight) {
					return false;
				}
			}
		}
		return true;
	}

	private static function cmp($left, $op, $right)
	{
		switch ($op) {
		case '>=':
			return $left >= $right;
		case '<=':
			return $left <= $right;
		case '=':
		case '==':
			return $left == $right;
		case '!=':
			return $left != $right;
		case 'include':
			return @strpos($left, $right) !== false;
		case 'notinclude':
			return @strpos($left, $right) === false;
		}
		return true;
	}

	public static function inSections($fields)
	{
		$isFound = true;
		if ($fields['IS_SECTIONS'] == 'Y'
			&& !empty($fields['IBLOCK_SECTIONS'])) {
			$isFound = false;
			$rsSect  = \CIBlockSection::GetNavChain(
				IntVal($fields['IBLOCK_ID']),
				IntVal($fields['IBLOCK_SECTION_ID']),
				['ID']
			);
			while ($arSect = $rsSect->GetNext()) {
				if (in_array($arSect['ID'], $fields['IBLOCK_SECTIONS'])) {
					$isFound = true;
					break;
				}
			}
		}
		return $isFound;
	}

	public static function isDatetime($name, $fields, $arProp=[]) {
		if (in_array($name, ['DATE_ACTIVE_FROM', 'DATE_ACTIVE_TO', 'DATE_CREATE'])) {
			return true;
		}

		if (strpos($name, 'PROPERTY_') === 0) {
			if (!empty($fields)) {
				$arProp = $fields[$name];
			}
			if (!empty($arProp) && in_array($arProp['USER_TYPE'], ['Date', 'DateTime'])) {
				return true;
			}
		}

		return false;
	}
}
