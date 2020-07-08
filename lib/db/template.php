<?php
namespace vettich\sp3\db;

use Bitrix\Main\Entity;
use Bitrix\Main\Type;
use vettich\sp3\Module;

class TemplateTable extends OrmBase
{
	public static function getTableName()
	{
		return 'vettich_sp3_template';
	}

	public static function getMap()
	{
		global $USER;
		$arMap = [
			new Entity\IntegerField('ID', [
				'primary' => true,
				'autocomplete' => true
			]),

			new Entity\BooleanField('IS_ENABLE', [
				'values'=>['N', 'Y'],
				'default_value' => 'Y'
			]),

			new Entity\StringField('NAME'),

			new Entity\StringField('IBLOCK_TYPE', [
				'default_value' => ''
			]),

			new Entity\StringField('IBLOCK_ID', [
				'default_value' => ''
			]),

			new Entity\BooleanField('IS_SECTIONS', [
				'values'=>['N', 'Y'],
				'default_value' => 'N'
			]),

			new Entity\BooleanField('NEED_UTM', [
				'values'=>['N', 'Y'],
				'default_value' => 'Y'
			]),

			new Entity\StringField('UTM_SOURCE', [
				'default_value' => ''
			]),

			new Entity\StringField('UTM_MEDIUM', [
				'default_value' => ''
			]),

			new Entity\StringField('UTM_CAMPAIGN', [
				'default_value' => ''
			]),

			new Entity\StringField('UTM_TERM', [
				'default_value' => ''
			]),

			new Entity\StringField('UTM_CONTENT', [
				'default_value' => ''
			]),

			/* (new Entity\TextField('URL_PARAMS', [ */
			/* 	'default_value' => '' */
			/* ]))->addValidator(new LengthValidator(0, 1000)), */

			new Entity\BooleanField('IS_AUTO', [
				'values'=>['N', 'Y'],
				'default_value' => 'Y'
			]),

			new Entity\StringField('PUBLISH_AT', [
				'default_value' => ''
			]),

			new Entity\BooleanField('UPDATE_IN_NETWORKS', [
				'values'=>['N', 'Y'],
				'default_value' => 'Y'
			]),

			new Entity\BooleanField('DELETE_IN_NETWORKS', [
				'values'=>['N', 'Y'],
				'default_value' => 'Y'
			]),

			new Entity\BooleanField('QUEUE_DUPLICATE', [
				'values'=>['N', 'Y'],
				'default_value' => 'N'
			]),

			new Entity\IntegerField('USER_ID', [
				'default_value' => $USER->GetID()
			]),

			new Entity\DatetimeField('UPDATED_AT', [
				'default_value' => Type\DateTime::createFromPhp(new \DateTime()),
			]),

			new Entity\DatetimeField('CREATED_AT', [
				'default_value' => Type\DateTime::createFromPhp(new \DateTime()),
			]),
		];

		if (version_compare(SM_VERSION, '18.1.4') < 0) {
			$arMap[] = new Entity\TextField('IBLOCK_SECTIONS', [
				'serialized' => true,
				'default_value' => ''
			]);
			$arMap[] = new Entity\StringField('DOMAIN', [
				'default_value' => ''
			]);
			$arMap[] = new Entity\TextField('CONDITIONS', [
				'serialized' => true,
				'default_value' => ''
			]);
			$arMap[] = new Entity\TextField('ACCOUNTS', [
				'serialized' => true,
				'default_value' => ''
			]);
			$arMap[] = new Entity\TextField('PUBLISH', [
				'serialized' => true,
				'default_value' => ''
			]);
		} else {
			$v = new \Bitrix\Main\ORM\Fields\Validators\LengthValidator(0, 2000);

			$arMap[] = (new \Bitrix\Main\ORM\Fields\ArrayField('IBLOCK_SECTIONS', [
				'default_value' => []
			]))->configureSerializationPhp()->addValidator($v);

			$arMap[] = (new Entity\StringField('DOMAIN', [
				'default_value' => ''
			]))->addValidator($v);

			$arMap[] = (new \Bitrix\Main\ORM\Fields\ArrayField('CONDITIONS', [
				'default_value' => []
			]))->configureSerializationPhp()->addValidator($v);

			$arMap[] = (new \Bitrix\Main\ORM\Fields\ArrayField('ACCOUNTS', [
				'default_value' => []
			]))->configureSerializationPhp()->addValidator($v);

			$arMap[] = (new \Bitrix\Main\ORM\Fields\ArrayField('PUBLISH', [
				'default_value' => []
			]))->configureSerializationPhp()->addValidator($v);
		}
		return $arMap;
	}

	public static function OnBeforeAdd(Entity\Event $event)
	{
		$data = $event->getParameter('fields');
		$result = new Entity\EventResult;
		$modFields = self::cleanConditions($data);
		$modFields['UPDATED_AT'] = new Type\DateTime();
		$result->modifyFields($modFields);
		return $result;
	}

	public static function OnBeforeUpdate(Entity\Event $event)
	{
		$data = $event->getParameter('fields');
		$result = new Entity\EventResult;
		$modFields = self::cleanConditions($data);
		$modFields['UPDATED_AT'] = new Type\DateTime();
		$result->modifyFields($modFields);
		return $result;
	}

	public static function cleanConditions($data)
	{
		$modFields = [];
		if (!empty($data['CONDITIONS'])) {
			$modFields['CONDITIONS'] = Module::cleanConditions($data['CONDITIONS']);
		}
		if (isset($data['PUBLISH']) && is_array($data['PUBLISH'])) {
			$modFields['PUBLISH'] = $data['PUBLISH'];
			foreach ((array)$data['PUBLISH'] as $key => $value) {
				if (isset($value['CONDITIONS'])) {
					$modFields['PUBLISH'][$key]['CONDITIONS'] = Module::cleanConditions($value['CONDITIONS']);
				}
			}
		}
		return $modFields;
	}
}
