<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
// require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

CModule::IncludeModule('vettich.devform');
IncludeModuleLangFile(__FILE__);

(new vettich\devform\AdminList('Options for modules', 'sTableID', array(
	'dbClass' => 'vettich\devform\db\option',
	'params' => array(
		'ID' => 'text',
		'MODULE_ID' => 'text:MODULE_ID',
		'NAME' => 'text:NAME',
		'VALUE' => 'text:VALUE',
	),
	'dontEdit' => array('ID'),
)))->render();

require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
