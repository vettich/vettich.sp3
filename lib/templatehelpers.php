<?php
namespace vettich\sp3;

use vettich\sp3\db;

class TemplateHelpers
{
	public static function publish($arFields, $params=[])
	{
		if (empty($arFields)
			or $arFields['ID'] <= 0
			or (!empty($arFields['WF_PARENT_ELEMENT_ID'])
				&& $arFields['ID'] != $arFields['WF_PARENT_ELEMENT_ID'])) {
			return false;
		}
		IBlockHelpers::iblockValueFill($arFields, true);

		$arTemplates = [];
		if (!empty($params['arTemplate'])) {
			$arTemplates[$params['arTemplate']['ID']] = $params['arTemplate'];
		} else {
			$filter = [
				/* 'IS_ENABLE' => 'Y', */
				'IBLOCK_ID' => $arFields['IBLOCK_ID'],
			];
			if ($params['event'] == 'add') {
				$filter['IS_AUTO'] = 'Y';
			}
			$rsTemplate = db\TemplateTable::getList(['filter' => $filter]);
			while ($ar = $rsTemplate->fetch()) {
				$arTemplates[$ar['ID']] = $ar;
			}
		}

		foreach ((array)$arTemplates as $arTemplate) {
			$fields = $arFields + $arTemplate;
			if (!IBlockHelpers::cmpFields($fields) || !IBlockHelpers::inSections($fields)) {
				continue;
			}
			$post = self::preparePostData($arFields, $arTemplate);
			if (empty($post)) {
				continue;
			}
			$res = Api::createPost($post);
			if (empty($res['error'])) {
				$postIblockData = [
					'IBLOCK_ID' => $arFields['IBLOCK_ID'],
					'ELEM_ID' => $arFields['ID'],
					'TEMPLATE_ID' => $arTemplate['ID'],
					'POST_ID' => $res['response']['post_id'],
				];
				db\PostIBlockTable::add($postIblockData);
			}
		}
	}

	public static function publishWithTemplate($arFilter, $templateIds, $checkConditions=false)
	{
		if (!\CModule::IncludeModule('iblock')) {
			return null;
		}

		$arTemplates = [];
		$rsTemplate = db\TemplateTable::getList(['filter' => ['id' => $templateIds]]);
		while ($arTemplate = $rsTemplate->fetch()) {
			$arTemplates[$arTemplate['ID']] = $arTemplate;
		}
		if (empty($arTemplates)) {
			return [];
		}
		return self::publishWithTemplateStep2($arFilter, $arTemplates, $checkConditions);
	}

	public static function publishWithTemplateStep2($arFilter, $arTemplates, $checkConditions=false)
	{
		$arResult = [];
		$rs = \CIBlockElement::GetList([], $arFilter, false, false, ['ID', 'IBLOCK_ID']);
		while ($arFields = $rs->Fetch()) {
			IBlockHelpers::iblockValueFill($arFields, true);
			foreach ($arTemplates as $arTemplate) {
				if ($checkConditions && !IBlockHelpers::cmpFields($fields)) {
					continue;
				}
				$post = self::preparePostData($arFields, $arTemplate);
				if (empty($post)) {
					continue;
				}
				$res = Api::createPost($post);
				if (empty($res['error'])) {
					$arResult[] = $res['response']['post_id'];
					$postIblockData = [
						'IBLOCK_ID' => $arFields['IBLOCK_ID'],
						'ELEM_ID' => $arFields['ID'],
						'POST_ID' => $res['response']['post_id'],
					];
					if (!isset($arTemplate['ID']) || $arTemplate['ID'] == 0) {
						$postIblockData['TEMPLATE'] = $arTemplate;
					} else {
						$postIblockData['TEMPLATE_ID'] = $arTemplate['ID'];
					}
					db\PostIBlockTable::add($postIblockData);
				}
			}
		}
		return $arResult;
	}

	public static function update($arFields, $params=[])
	{
		IBlockHelpers::iblockValueFill($arFields, true);
		try {
			$filter = [
				'ELEM_ID' => $arFields['ID'],
				'IBLOCK_ID' => $arFields['IBLOCK_ID'],
			];
			$rs = db\PostIBlockTable::getList(['filter' => $filter]);
			while ($arPostIBlock = $rs->fetch()) {
				if ($arPostIBlock['TEMPLATE_ID'] != 0) {
					$rsTemplate = db\TemplateTable::getById($arPostIBlock['TEMPLATE_ID']);
					$arTemplate = $rsTemplate->fetch();
				} else {
					$arTemplate = $arPostIBlock['TEMPLATE'];
				}
				if (empty($arTemplate)) {
					continue;
				}
				if ($arTemplate['UPDATE_IN_NETWORKS'] != 'Y') {
					continue;
				}
				$post = self::preparePostData($arFields, $arTemplate);
				if (empty($post)) {
					continue;
				}
				$post['id'] = $arPostIBlock['POST_ID'];
				$res = Api::updatePost($post);
			}
		} catch (\Exception $e) {
			Module::log(['code' => $e->getCode(), 'msg' => $e->getMessage()]);
			return;
		}
	}

	public static function delete($arFields, $params=[])
	{
		try {
			$filter = [
				'ELEM_ID' => $arFields['ID'],
				'IBLOCK_ID' => $arFields['IBLOCK_ID'],
			];
			$rs = db\PostIBlockTable::getList(['filter' => $filter]);
			while ($arPostIBlock = $rs->fetch()) {
				if ($arPostIBlock['TEMPLATE_ID'] != 0) {
					$rsTemplate = db\TemplateTable::getById($arPostIBlock['TEMPLATE_ID']);
					$arTemplate = $rsTemplate->fetch();
				} else {
					$arTemplate = $arPostIBlock['TEMPLATE'];
				}
				if (empty($arTemplate)) {
					continue;
				}
				if ($arTemplate['DELETE_IN_NETWORKS'] != 'Y') {
					continue;
				}
				$res = Api::deletePost($arPostIBlock['POST_ID']);
				db\PostIBlockTable::delete($arPostIBlock['ID']);
			}
		} catch (\Exception $e) {
			Module::log(['code' => $e->getCode(), 'msg' => $e->getMessage()]);
			return;
		}
	}

	protected static function preparePostData($arFields, $arTemplate)
	{
		$fields = $arFields + $arTemplate;
		$images = self::prepareImages($arTemplate['PUBLISH']['COMMON']['MAIN_PICTURE'], $fields);
		$images = array_merge($images, self::prepareImages($arTemplate['PUBLISH']['COMMON']['OTHER_PICTURE'], $fields));
		$post = [
			'fields' => [
				'text' => self::prepareText($arTemplate['PUBLISH']['COMMON']['TEXT'], $fields),
				'link' => TextProcessor::macroValue($arTemplate['PUBLISH']['COMMON']['LINK'], $fields),
				'tags' => TextProcessor::macroValue($arTemplate['PUBLISH']['COMMON']['TAGS'], $fields),
				'images' => $images,
				'extra' => [
					'vk_from_group' => ($arTemplate['PUBLISH']['VK']['FROM_GROUP'] == 'Y'),
					'vk_signed' => ($arTemplate['PUBLISH']['VK']['SIGNED'] == 'Y'),
					'id' => intval($arFields['ID']),
					'iblock_id' => intval($arFields['IBLOCK_ID']),
					'template_id' => intval($arTemplate['ID']),
				],
			],
			'publish_at' => Api::toTime(TextProcessor::macroValue($arTemplate['PUBLISH_AT'], $fields)),
			'networks' => [
				'accounts' => $arTemplate['ACCOUNTS'],
			],
		];
		return Module::convertToUtf8($post);
	}

	protected static function prepareText($fieldKey, $fields)
	{
		$text = TextProcessor::replace($fieldKey, $fields);
		$text = strip_tags($text);
		$text = trim(html_entity_decode($text));
		return $text;
	}

	protected static function prepareImages($fieldKey, $fields)
	{
		$files = TextProcessor::getFileNames($fields[$fieldKey]);
		if (empty($files)) {
			return [];
		}
		$images = [];
		foreach ((array)$files as $filepath) {
			$filepath = Module::convertToUtf8($filepath);
			$res = Api::uploadFile($filepath, basename($filepath));
			if (empty($res['error'])) {
				$images[] = $res['response']['file_id'];
			}
		}
		return $images;
	}

	public static function listTemplates($iblockId)
	{
		$arResult = ['templates' => [], 'elems' => []];
		$rsTemplate = db\TemplateTable::getList(['filter' => ['IBLOCK_ID' => $iblockId]]);
		$templatesMap = [];
		while ($ar = $rsTemplate->fetch()) {
			$templatesMap[$ar['ID']] = $ar['NAME'];
		}
		$arResult['templates'] = $templatesMap;
		return $arResult;
	}
}
