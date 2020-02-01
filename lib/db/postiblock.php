<?php
namespace vettich\sp3\db;

use Bitrix\Main\Entity;
use Bitrix\Main\ORM\Fields\ArrayField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type;
use vettich\sp3\Module;

class PostIBlockTable extends OrmBase
{
	public static function getTableName()
	{
		return 'vettich_sp3_post_iblock';
	}

	public static function getMap()
	{
		global $USER;
		$arMap = [
			new Entity\IntegerField('ID', [
				'primary' => true,
				'autocomplete' => true
			]),

			new Entity\StringField('IBLOCK_ID', [
				'default_value' => '',
			]),

			new Entity\IntegerField('ELEM_ID', [
				'default_value' => 0,
			]),

			new Entity\IntegerField('TEMPLATE_ID', [
				'default_value' => 0,
			]),

			(new ArrayField('TEMPLATE', [
				'default_value' => []
			]))->configureSerializationPhp()
				->addValidator(new LengthValidator(0, 2000)),

			new Entity\StringField('POST_ID', [
				'default_value' => '',
			]),
		];
		return $arMap;
	}
}
