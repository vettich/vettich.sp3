<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
CModule::IncludeModule('vettich.devform');
IncludeModuleLangFile(__FILE__);

(new \vettich\devform\AdminList('Список элементов', 'sTableID', array(
	'dbClass' => 'vettich\devform\db',
	'params' => array(
		'ID' => 'number',
		'NAME' => 'text:#VDF_NAME#',
		'IS_ENABLE' => 'checkbox:#VDF_IS_ENABLE#',
	),
	'dontEdit' => array('ID'),
)))->render();

require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
