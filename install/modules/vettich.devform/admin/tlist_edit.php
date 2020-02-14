<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

CModule::IncludeModule('vettich.devform');
IncludeModuleLangFile(__FILE__);

(new \vettich\devform\AdminForm('devform', array(
	'pageTitle' => ($_GET['ID'] > 0 ? '�������������� ������' : '���������� ������'),
	'tabs' => array(
		array(
			'name' => '������',
			'title' => '��������� ������',
			'params' => array(
				'_NAME' => 'text:#VDF_NAME#:Default value',
				'_IS_ENABLE' => 'checkbox:#VDF_IS_ENABLE#:Y',
				'heading1' => 'heading:��������� ����������',
				'IBLOCK_TYPE' => array(
					'type' => 'select',
					'title' => '��� ���������',
					'options' => array(
						'news' => '�������',
					),
				),
				'IBLOCK_ID' => array(
					'type' => 'select',
					'title' => 'ID ���������',
					'options' => array(
						'3' => '������� �����',
						'4' => '������� ��������',
						'5' => '������� ������',
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
