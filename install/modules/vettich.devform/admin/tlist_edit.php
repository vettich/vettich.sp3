<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

CModule::IncludeModule('vettich.devform');
IncludeModuleLangFile(__FILE__);

(new \vettich\devform\AdminForm('devform', array(
	'pageTitle' => ($_GET['ID'] > 0 ? 'Редактирование записи' : 'Добавление записи'),
	'tabs' => array(
		array(
			'name' => 'Запись',
			'title' => 'Настройка записи',
			'params' => array(
				'_NAME' => 'text:#VDF_NAME#:Default value',
				'_IS_ENABLE' => 'checkbox:#VDF_IS_ENABLE#:Y',
				'heading1' => 'heading:Настройка инфоблоков',
				'IBLOCK_TYPE' => array(
					'type' => 'select',
					'title' => 'Тип инфоблока',
					'options' => array(
						'news' => 'Новости',
					),
				),
				'IBLOCK_ID' => array(
					'type' => 'select',
					'title' => 'ID инфоблока',
					'options' => array(
						'3' => 'Новости сайта',
						'4' => 'Новости магазина',
						'5' => 'Новости завода',
					),
					'default_value' => '4',
				),
			),
		),
	),
	'buttons' => array(
		'_save' => 'buttons\saveSubmit:#VDF_SAVE#',
		'_apply' => 'buttons\submit:#VDF_APPLY#',
	),
	'data' => 'orm:dbClass=vettich\devform\db:paramPrefix=_',
)))->render();

require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
