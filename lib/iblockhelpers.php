<?php
namespace vettich\sp3;

class IBlockHelpers
{
	private static $_allPropsFor = [];
	private static $_allPropsMacrosFor = [];
	private static $_iblockFields = null;
	private static $_iblockProps = [];
	private static $_catalogFields = [];
	private static $_iblocktypes = null;
	private static $_iblocktypesisall = false;
	private static $_iblockids = [];
	private static $_iblockElemIds = [];
	private static $_iblockSections = [];

	public static function allPropsFor($iblockId, $isIblockIsset=true)
	{
		if ($isIblockIsset && empty($iblockId)) {
			return ['' => self::m('BEFORE_IBLOCK_SELECT')];
		}
		if (isset(self::$_allPropsFor[$iblockId])) {
			return self::$_allPropsFor[$iblockId];
		}
		$result = [];
		$result[] = [
			'label' => self::m('MAIN_FIELDS'),
			'items' => self::iblockFields(),
		];
		$result[] = [
			'label' => self::m('PROPERTIES'),
			'items' => self::iblockProps($iblockId),
		];
		$result[] = [
			'label' => self::m('CATALOG_FIELDS'),
			'items'=> self::catalogFiedls($iblockId),
		];
		self::$_allPropsFor[$iblockId] = $result;
		return $result;
	}


	public static function iblockFields()
	{
		if (self::$_iblockFields == null) {
			self::$_iblockFields = [
				''                   => 'none',
				'ID'                 => self::m('PROP_ID'),
				'CODE'               => self::m('PROP_CODE'),
				'XML_ID'             => self::m('PROP_XML_ID'),
				'NAME'               => self::m('PROP_NAME'),
				'IBLOCK_ID'          => self::m('PROP_IBLOCK_ID'),
				'IBLOCK_SECTION_ID'  => self::m('PROP_IBLOCK_SECTION_ID'),
				'IBLOCK_CODE'        => self::m('PROP_IBLOCK_CODE'),
				'ACTIVE'             => self::m('PROP_ACTIVE'),
				'DATE_ACTIVE_FROM'   => self::m('PROP_DATE_ACTIVE_FROM'),
				'DATE_ACTIVE_TO'     => self::m('PROP_DATE_ACTIVE_TO'),
				'SORT'               => self::m('PROP_SORT'),
				'PREVIEW_PICTURE'    => self::m('PROP_PREVIEW_PICTURE'),
				'PREVIEW_TEXT'       => self::m('PROP_PREVIEW_TEXT'),
				'DETAIL_PICTURE'     => self::m('PROP_DETAIL_PICTURE'),
				'DETAIL_TEXT'        => self::m('PROP_DETAIL_TEXT'),
				'DATE_CREATE'        => self::m('PROP_DATE_CREATE'),
				'CREATED_BY'         => self::m('PROP_CREATED_BY'),
				'CREATED_USER_NAME'  => self::m('PROP_CREATED_USER_NAME'),
				'TIMESTAMP_X'        => self::m('PROP_TIMESTAMP_X'),
				'MODIFIED_BY'        => self::m('PROP_MODIFIED_BY'),
				'USER_NAME'          => self::m('PROP_USER_NAME'),
				'LIST_PAGE_URL'      => self::m('PROP_LIST_PAGE_URL'),
				'DETAIL_PAGE_URL'    => self::m('PROP_DETAIL_PAGE_URL'),
				'SHOW_COUNTER'       => self::m('PROP_SHOW_COUNTER'),
				'SHOW_COUNTER_START' => self::m('PROP_SHOW_COUNTER_START'),
				'WF_COMMENTS'        => self::m('PROP_WF_COMMENTS'),
				'WF_STATUS_ID'       => self::m('PROP_WF_STATUS_ID'),
				'TAGS'               => self::m('PROP_TAGS'),
			];
		}
		return self::$_iblockFields;
	}

	public static function iblockProps($iblockId)
	{
		if (empty($iblockId)) {
			return null;
		}
		if (!isset(self::$_iblockProps[$iblockId])) {
			$arProps = [];
			$rsProperties = \CIBlockProperty::GetList(
				[],
				['ACTIVE'=>'Y', 'IBLOCK_ID'=>$iblockId]
			);
			while ($prop_fields = $rsProperties->GetNext()) {
				/* $str = $prop_fields['NAME']. ' [PROPERTY_'. $prop_fields['CODE']. ']'; */
				$str = "[PROPERTY_$prop_fields[CODE]] <b>$prop_fields[NAME]</b>";
				$str = str_replace("'", '"', $str);
				$str = str_replace(["\"", '&quot;', '&#34;'], "'", $str);
				$arProps['PROPERTY_'.$prop_fields['CODE']] = $str;
			}
			self::$_iblockProps[$iblockId] = $arProps;
		}
		return self::$_iblockProps[$iblockId];
	}

	public static function catalogFiedls($iblockId)
	{
		if (empty($iblockId)) {
			return null;
		}
		if (!isset(self::$_catalogFields[$iblockId])) {
			$arProps = [];
			if (\CModule::IncludeModule('catalog')
				&& \CCatalog::GetByID($iblockId)) {
				$arProps['CATALOG_QUANTITY'] = self::m('PROP_CAT_QUANTITY');
				$arProps['CATALOG_WEIGHT'] = self::m('PROP_CAT_WEIGHT');
				$arProps['CATALOG_WIDTH'] = self::m('PROP_CAT_WIDTH');
				$arProps['CATALOG_LENGTH'] = self::m('PROP_CAT_LENGTH');
				$arProps['CATALOG_HEIGHT'] = self::m('PROP_CAT_HEIGHT');

				$rs = \CCatalogGroup::GetList([], [], false, false, ['ID', 'NAME_LANG']);
				$arCurrency = [];
				while ($ar = $rs->Fetch()) {
					$arProps['CATALOG_PRICE_'.$ar['ID']] = self::m('PROP_CAT_PRICE', [
						'#TYPE#' => $ar['NAME_LANG'],
						'#PRICE_ID#' => $ar['ID'],
					]);
					$arCurrency['CATALOG_CURRENCY_'.$ar['ID']] = self::m('PROP_CAT_CURRENCY', [
						'#TYPE#' => $ar['NAME_LANG'],
						'#PRICE_ID#' => $ar['ID'],
					]);
				}
				foreach ((array)$arCurrency as $key => $value) {
					$arProps[$key] = $value;
				}

				$arProps['CATALOG_DISCOUNT_NAME'] = self::m('PROP_CAT_DISCOUNT_NAME');
				$arProps['CATALOG_DISCOUNT_ACTIVE_FROM'] = self::m('PROP_CAT_DISCOUNT_ACTIVE_FROM');
				$arProps['CATALOG_DISCOUNT_ACTIVE_TO'] = self::m('PROP_CAT_DISCOUNT_ACTIVE_TO');
				self::$_catalogFields[$iblockId] = $arProps;
			}
		}
		return self::$_catalogFields[$iblockId];
	}

	public static function allPropsMacrosFor($iblockId, $isIblockIsset=true)
	{
		if (isset(self::$_allPropsMacrosFor[$iblockId])) {
			return self::$_allPropsMacrosFor[$iblockId];
		}
		$result = self::allPropsFor($iblockId, $isIblockIsset);
		foreach ((array)$result as $key => $value) {
			if (empty($key) && $key !== 0) {
				continue;
			}
			if (isset($value['items'])) {
				foreach ((array)$value['items'] as $key2 => $value2) {
					if (!$key2) {
						continue;
					}
					DevFormModule::changeKey($key2, "#$key2#", $value['items']);
				}
				// $value = $result[$key];
				unset($result[$key]);
				$result[$key] = $value;
			} else {
				DevFormModule::changeKey($key, "#$key#", $result);
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

	public static function iblockValueFill(&$arFields, $isFull=false)
	{
		if ($isFull) {
			$arFields = self::iblockElemId($arFields['ID'], $arFields['IBLOCK_ID'], false);
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
				$arFields['PROPERTY_'.$arProp['CODE']]['VALUES_ENUM'][] = $arProp['VALUE_ENUM'];
				$arFields['PROPERTY_'.$arProp['CODE']]['~VALUES_ENUM'][] = $arProp['~VALUE_ENUM'];
				$arFields['PROPERTY_'.$arProp['CODE']]['VALUES_XML_ID'][] = $arProp['VALUE_XML_ID'];
				$arFields['PROPERTY_'.$arProp['CODE']]['~VALUES_XML_ID'][] = $arProp['~VALUE_XML_ID'];
			}
		}
		if (\CModule::IncludeModule('catalog')
			&& \CCatalog::GetByID($arFields['IBLOCK_ID'])) {
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
							'PRODUCT_ID' => $arFields['ID'],
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
						$arFields['CATALOG_PRICE_'.$ar['ID']] = $arPrice['PRICE'];
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
					$arFields['CATALOG_DISCOUNT_NAME'] = $arDiscount['NAME'];
					$arFields['CATALOG_DISCOUNT_ACTIVE_FROM'] = $arDiscount['ACTIVE_FROM'];
					$arFields['CATALOG_DISCOUNT_ACTIVE_TO'] = $arDiscount['ACTIVE_TO'];
				}
			}
			$rs = current(\CCatalogSKU::getOffersList($arFields['ID'], $arFields['IBLOCK_ID']));
			if (!empty($rs)) {
				foreach ((array)$rs as $ar) {
					$arFields['SKU'][$ar['ID']] = self::iblockElemId($ar['ID'], $ar['IBLOCK_ID']);
				}
			}
		}
	}

	public static function iblockTypes()
	{
		if (self::$_iblocktypes === null
			or !self::$_iblocktypesisall) {
			$rsIBlockType = \CIBlockType::GetList();
			while ($arIBlockType = $rsIBlockType->GetNext()) {
				if ($arIBType = \CIBlockType::GetByIDLang($arIBlockType["ID"], LANG)) {
					$arIBlockType['NAME'] = $arIBType['NAME'];
					$arIBlockType['SECTION_NAME'] = $arIBType['SECTION_NAME'];
					$arIBlockType['ELEMENT_NAME'] = $arIBType['ELEMENT_NAME'];
					self::$_iblocktypes[$type] = $arIBlockType;
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
			$rsIBlockType = \CIBlockType::GetByID($type);
			if ($arIBlockType = $rsIBlockType->GetNext()) {
				if ($arIBType = \CIBlockType::GetByIDLang($arIBlockType["ID"], LANG)) {
					$arIBlockType['NAME'] = $arIBType['NAME'];
					$arIBlockType['SECTION_NAME'] = $arIBType['SECTION_NAME'];
					$arIBlockType['ELEMENT_NAME'] = $arIBType['ELEMENT_NAME'];
					self::$_iblocktypes[$type] = $arIBlockType;
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
			$rs = \CIBlock::GetByID($id);
			if ($rs = $rs->GetNext()) {
				self::$_iblockids[$id] = $rs;
			}
		}
		return self::$_iblockids[$id];
	}

	public static function iblockElemId($id, $iblockId, $isFill=true)
	{
		if (!\CModule::IncludeModule('iblock')) {
			return null;
		}
		if ((is_array($id) && in_array($id, array_keys(self::$_iblockElemIds)))
			or empty(self::$_iblockElemIds[$id])) {
			$rs = \CIBlockElement::GetList(
				['sort'=>'asc'],
				[
					'ID' => $id,
					'IBLOCK_ID' => $iblockId,
				]
			);
			while ($ar = $rs->GetNext()) {
				if ($isFill) {
					self::iblockValueFill($ar);
				}
				$ar['filled'] = $isFill;
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
				[
					'ID' => $id,
					'IBLOCK_ID' => $iblockId,
				]
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
}