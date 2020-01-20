<?php
namespace vettich\sp3\db;

use Bitrix\Main\Entity;
use Bitrix\Main\ORM\Fields\ArrayField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
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
		$arMap = [
			new Entity\IntegerField('ID', [
				'primary' => true,
				'autocomplete' => true
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

			(new ArrayField('IBLOCK_SECTIONS', [
				'default_value' => []
			]))->configureSerializationPhp()
				->addValidator(new LengthValidator(0, 2000)),

			new Entity\StringField('DOMAIN', [
				'default_value' => ''
			])->addValidator(new LengthValidator(0, 1000)),

			new Entity\TextField('URL_PARAMS', [
				'default_value' => ''
			])->addValidator(new LengthValidator(0, 1000)),

			(new ArrayField('CONDITIONS', [
				'default_value' => []
			]))->configureSerializationPhp()
				->addValidator(new LengthValidator(0, 2000)),

			(new ArrayField('ACCOUNTS', [
				'default_value' => []
			]))->configureSerializationPhp()
				->addValidator(new LengthValidator(0, 2000)),

			(new ArrayField('PUBLISH', [
				'default_value' => []
			]))->configureSerializationPhp()
				->addValidator(new LengthValidator(0, 2000)),

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
