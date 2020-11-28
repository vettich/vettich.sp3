<?php
namespace vettich\sp3;

use Bitrix\Main\Type\DateTime as BxDateTime;
use vettich\sp3\db;

class TemplateHelpers
{
	private static $_iblockElements = [];

	/**
	 * publishes a post from an infoblock
	 *
	 * a template for publishing is selected automatically
	 * based on conditions in saved templates
	 *
	 * @param array $arFields can be ['ID' => <elemID>, 'IBLOCK_ID' => <iblockID>]
	 * @param array $params (optional)
	 * @return array
	 *   'errors' - if errors exists
	 *   'post_ids' - array of added post ids
	 */
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
			$filter = ['IBLOCK_ID' => $arFields['IBLOCK_ID']];
			if ($params['event'] == 'add') {
				$filter['IS_AUTO'] = 'Y';
			}

			$rsTemplate = db\TemplateTable::getList(['filter' => $filter]);
			while ($ar = $rsTemplate->fetch()) {
				$arTemplates[$ar['ID']] = $ar;
			}
		}

		$result = ['errors' => [], 'post_ids' => []];
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
					'IBLOCK_ID'   => $arFields['IBLOCK_ID'],
					'ELEM_ID'     => $arFields['ID'],
					'TEMPLATE_ID' => $arTemplate['ID'],
					'POST_ID'     => $res['response']['post_id'],
				];
				$result['post_ids'][] = $res['response']['post_id'];
				db\PostIBlockTable::add($postIblockData);
				db\TemplateTable::updateLastPublishTime($arTemplate['ID']);
			} else {
				$result['errors'][] = $res['error'];
			}
		}

		return $result;
	}

	public static function publishWithTemplate($arFilter, $templateIds, $checkConditions=false)
	{
		if (!\CModule::IncludeModule('iblock')) {
			return null;
		}

		$arTemplates = [];
		$rsTemplate  = db\TemplateTable::getList(['filter' => ['ID' => $templateIds]]);
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
		$rs       = \CIBlockElement::GetList([], $arFilter, false, false, ['ID', 'IBLOCK_ID']);
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
					$arResult[]     = $res['response']['post_id'];
					$postIblockData = [
						'IBLOCK_ID' => $arFields['IBLOCK_ID'],
						'ELEM_ID'   => $arFields['ID'],
						'POST_ID'   => $res['response']['post_id'],
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

	public static function cacheIblockElement($id, $iblockId)
	{
		if (empty($id) || empty($iblockId)) {
			return;
		}
		$elem                       = IBlockHelpers::iblockElemId($id, $iblockId, false);
		self::$_iblockElements[$id] = $elem;
	}

	public static function update($arFields, $params=[])
	{
		IBlockHelpers::iblockValueFill($arFields, true, false);
		$found = false;
		try {
			$filter = [
				'ELEM_ID'   => $arFields['ID'],
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

				if (empty($arTemplate) ||
					$arTemplate['UPDATE_IN_NETWORKS'] != 'Y') {
					continue;
				}

				$post = self::preparePostData($arFields, $arTemplate);
				if (empty($post)) {
					continue;
				}

				$found      = true;
				$post['id'] = $arPostIBlock['POST_ID'];
				$res        = Api::updatePost($post);
			}
		} catch (\Exception $e) {
			Module::log(['code' => $e->getCode(), 'msg' => $e->getMessage()]);
			return;
		}

		if (!$found &&
			$arFields['ACTIVE'] == 'Y' &&
			isset(self::$_iblockElements[$arFields['ID']]) &&
			self::$_iblockElements[$arFields['ID']]['ACTIVE'] != $arFields['ACTIVE']) {
			self::publish($arFields, ['event' => 'add']);
		}
	}

	public static function delete($arFields, $params=[])
	{
		try {
			$filter = [
				'ELEM_ID'   => $arFields['ID'],
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

	public static function unload()
	{
		if (!\CModule::IncludeModule('iblock')) {
			return [
				'status' => 'fail',
				'error'  => [
					'code' => 500,
					'msg'  => 'iblock module cannot be include'
				]
			];
		}

		$results = [];
		try {
			$rs = db\TemplateTable::getList(['filter' => [
				'UNLOAD_ENABLE' => 'Y',
			]]);
			while ($arTemplate = $rs->fetch()) {
				if (!self::checkUnloadDatetime($arTemplate)) {
					continue;
				}

				$arSort           = self::prepareUnloadSort($arTemplate);
				$arFilter         = self::prepareUnloadFilter($arTemplate);
				$arNavStartParams = ['nTopCount' => 1];
				$arSelect         = ['ID', 'IBLOCK_ID', 'IBLOCK_TYPE', 'NAME', 'IBLOCK_SECTION_ID'];

				$rsElem = \CIBlockElement::GetList($arSort, $arFilter, false, $arNavStartParams, $arSelect);
				$params = ['arTemplate' => $arTemplate];
				if ($arElem = $rsElem->GetNext()) {
					$results[] = self::publish($arElem, $params);
				}
			}
		} catch (\Exception $e) {
			$error = ['code' => $e->getCode(), 'msg' => $e->getMessage()];
			Module::log($error);
			return ['status' => 'fail', 'error' => $error];
		}
		return ['status' => 'ok', 'results' => $results];
	}

	protected static function checkUnloadDatetime($arTemplate)
	{
		if (!$arTemplate['LAST_PUBLISHED_AT'] instanceof BxDateTime) {
			return false;
		}

		$defaultTimezone = date_default_timezone_get();
		if (!empty($arTemplate['UNLOAD_TIMEZONE'])) {
			date_default_timezone_set($arTemplate['UNLOAD_TIMEZONE']);
		}

		$now         = new \DateTime();
		$bxnow       = BxDateTime::createFromPhp($now);
		$diff        = $arTemplate['LAST_PUBLISHED_AT']->getDiff($bxnow);
		$diffMinutes = Tools::getTotalInterval($diff, 'minutes');
		if ($arTemplate['UNLOAD_KEEP_INTERVAL'] == 'Y' && $diffMinutes < 25) {
			date_default_timezone_set($defaultTimezone);
			return false;
		}

		$curWeekday = Tools::getCurrentWeekday();
		$arTime     = array_merge(
			(array)$arTemplate['UNLOAD_DATETIME']['ALL'],
			(array)$arTemplate['UNLOAD_DATETIME'][$curWeekday]
		);

		$result = false;
		foreach ($arTime as $t) {
			$time                  = new \DateTime();
			list($hours, $minutes) = explode(':', $t);
			$time->setTime($hours, $minutes);
			$diff        = $now->diff($time);
			$diffMinutes = Tools::getTotalInterval($diff, 'minutes');
			if ($diffMinutes < 5) {
				$result = true;
				break;
			}
		}

		date_default_timezone_set($defaultTimezone);
		return $result;
	}

	protected static function prepareUnloadSort($arTemplate)
	{
		$arSort = [];
		$field  = $arTemplate['UNLOAD_SORT_FIELD'];
		$order  = $arTemplate['UNLOAD_SORT_ORDER'];
		if ($order == 'RAND') {
			$arSort['RAND'] = 'ASC';
		} elseif (!empty($field) && $field != 'none') {
			$arSort[$field] = $order;
		}
		return $arSort;
	}

	protected static function prepareUnloadFilter($arTemplate)
	{
		$arFilter = [
			'IBLOCK_TYPE' => $arTemplate['IBLOCK_TYPE'],
			'IBLOCK_ID'   => $arTemplate['IBLOCK_ID'],
		];

		$rsPostIblockElems = db\PostIBlockTable::getList([
			'filter' => [
				'IBLOCK_ID'   => $arTemplate['IBLOCK_ID'],
				'TEMPLATE_ID' => $arTemplate['ID'],
			],
			'group' => ['ELEM_ID'],
		]);
		$arExistsIDs = [];
		while ($arPostIBlockElem = $rsPostIblockElems->fetch()) {
			$arExistsIDs[] = $arPostIBlockElem['ELEM_ID'];
		}
		if (!empty($arExistsIDs)) {
			$arFilter['!ID'] = $arExistsIDs;
		}

		if ($arTemplate['PUBLISH']['CONDITIONS']['ACTIVE'] == 'Y') {
			$arFilter['ACTIVE'] = 'Y';
		}

		if ($arTemplate['CONDITIONS']) {
			foreach ($arTemplate['CONDITIONS'] as $arCondition) {
				switch ($arCondition['cmp']) {
				case '==':
				case 'include':
					$arFilter[$arCondition['field']] = $arCondition['value'];
					break;
				case '!=':
				case 'notinclude':
					$arFilter['!'.$arCondition['field']] = $arCondition['value'];
					break;
				default:
					$arFilter[$arCondition['cmp'].$arCondition['field']] = $arCondition['value'];
				}
			}
		}

		if ($arTemplate['IS_SECTIONS'] == 'Y' && !empty($arTemplate['IBLOCK_SECTIONS'])) {
			$arFilter['SECTION_ID']          = $arTemplate['IBLOCK_SECTIONS'];
			$arFilter['INCLUDE_SUBSECTIONS'] = 'Y';
		}

		return $arFilter;
	}

	protected static function preparePostData($arFields, $arTemplate)
	{
		$fields       = $arFields + $arTemplate;
		$mainPicture  = $arTemplate['PUBLISH']['COMMON']['MAIN_PICTURE'];
		$otherPicture = $arTemplate['PUBLISH']['COMMON']['OTHER_PICTURE'];
		$images       = self::prepareImages([$mainPicture, $otherPicture], $fields);
		$post         = [
			'fields' => [
				'text'       => self::prepareText($arTemplate['PUBLISH']['COMMON']['TEXT'], $fields),
				'link'       => TextProcessor::macroValue($arTemplate['PUBLISH']['COMMON']['LINK'], $fields),
				'need_utm'   => $arTemplate['NEED_UTM'] == 'Y',
				'utm_params' => [
					'utm_source'   => TextProcessor::replace($arTemplate['UTM_SOURCE'], $fields, false),
					'utm_medium'   => TextProcessor::replace($arTemplate['UTM_MEDIUM'], $fields, false),
					'utm_campaign' => TextProcessor::replace($arTemplate['UTM_CAMPAIGN'], $fields, false),
					'utm_term'     => TextProcessor::replace($arTemplate['UTM_TERM'], $fields, false),
					'utm_content'  => TextProcessor::replace($arTemplate['UTM_CONTENT'], $fields, false),
				],
				'tags'   => TextProcessor::macroValue($arTemplate['PUBLISH']['COMMON']['TAGS'], $fields),
				'images' => $images,
				'extra'  => [
					'vk_from_group' => ($arTemplate['PUBLISH']['VK']['FROM_GROUP'] == 'Y'),
					'vk_signed'     => ($arTemplate['PUBLISH']['VK']['SIGNED'] == 'Y'),
					'id'            => intval($arFields['ID']),
					'iblock_id'     => intval($arFields['IBLOCK_ID']),
					'template_id'   => intval($arTemplate['ID']),
				],
			],
			'publish_at' => Api::toTime(TextProcessor::macroValue($arTemplate['PUBLISH']['COMMON']['PUBLISH_AT'], $fields)),
			'networks'   => [
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

	protected static function prepareImages($fieldKeys, $fields)
	{
		$images = [];
		foreach ($fieldKeys as $fieldKey) {
			$files = TextProcessor::getFileNames($fields[$fieldKey]);
			if (empty($files)) {
				continue;
			}
			foreach ((array)$files as $filepath) {
				$filepath = Module::convertToUtf8($filepath);
				if (!db\Posts::checkImageMime($filepath) or !db\Posts::checkImageSize($filepath)) {
					continue;
				}
				$res = Api::uploadFile($filepath, basename($filepath));
				if (empty($res['error'])) {
					$images[] = $res['response']['file_id'];
				}
				if (count($images) >= 10) {
					break;
				}
			}
		}
		return $images;
	}

	public static function listTemplates($iblockId)
	{
		$arResult     = ['templates' => [], 'elems' => []];
		$rsTemplate   = db\TemplateTable::getList(['filter' => ['IBLOCK_ID' => $iblockId]]);
		$templatesMap = [];
		while ($ar = $rsTemplate->fetch()) {
			$templatesMap[$ar['ID']] = $ar['NAME'];
		}
		$arResult['templates'] = $templatesMap;
		return $arResult;
	}
}
