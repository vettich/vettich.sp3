<?php
IncludeModuleLangFile(__FILE__);

$module_id = 'vettich.sp3';

$aTabs = [
	[
		'DIV' => 'access',
		'TAB' => GetMessage('MAIN_TAB_RIGHTS'),
		'TITLE' => GetMessage('MAIN_TAB_TITLE_RIGHTS'),
	],
];

$tabControl = new CAdminTabControl('tabControl', $aTabs);

$tabControl->Begin();

$currentPage = $APPLICATION->GetCurPage();
$actionUrl = "$currentPage?mid=$module_id&lang=".LANG;
?><form method='post' action='<?php echo $actionUrl ?>' name='<?php echo $module_id ?>_settings'><?

$tabControl->BeginNextTab();

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/admin/group_rights.php');

$tabControl->Buttons();

?><input type="submit" name="Update" value="<?echo GetMessage('MAIN_SAVE')?>">
	<input type="reset" name="reset" value="<?echo GetMessage('MAIN_RESET')?>"><?
echo bitrix_sessid_post();

$tabControl->End();
