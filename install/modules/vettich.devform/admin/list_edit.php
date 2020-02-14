<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

CModule::IncludeModule('vettich.devform');
IncludeModuleLangFile(__FILE__);

(new vettich\devform\AdminForm('devform', array(
	'pageTitle' => 'Edit option',
	'tabs' => array(
		new vettich\devform\Tab(array(
			'name' => 'Option',
			'title' => '',
			'params' => array(
				'_ID' => 'hidden',
				'_MODULE_ID' => 'text:MODULE_ID',
				'_NAME' => 'text:NAME',
				'_VALUE' => 'text:VALUE',
			),
		)),
	),
	'buttons' => array(
		'_save' => 'buttons\saveSubmit:#VDF_SAVE#',
		'_apply' => 'buttons\submit:#VDF_APPLY#',
	),
	'data' => 'orm:dbClass=vettich\devform\db\option:paramPrefix=_',
)))->render();

require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
