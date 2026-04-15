<?php
IncludeModuleLangFile(__FILE__);

CModule::IncludeModule('vettich.sp3');
use vettich\sp3\Config;

$module_id = 'vettich.sp3';
$optionMenuShowChangefeed = 'menu_show_changefeed';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
	&& check_bitrix_sessid()
	&& $APPLICATION->GetGroupRight($module_id) >= 'W') {
	if (isset($_POST['Update'])) {
		Config::setShowChangefeedMenu(isset($_POST[$optionMenuShowChangefeed]));
	}
}

$aTabs = [
	[
		'DIV' => 'settings',
		'TAB' => GetMessage('VETTICH_SP3_OPTIONS_TAB_SETTINGS'),
		'TITLE' => GetMessage('VETTICH_SP3_OPTIONS_TAB_SETTINGS_TITLE'),
	],
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
?>
<tr>
	<td width="50%"><?php echo GetMessage('VETTICH_SP3_OPTIONS_MENU_SHOW_CHANGEFEED'); ?></td>
	<td width="50%">
		<input
			type="checkbox"
			name="<?php echo $optionMenuShowChangefeed; ?>"
			value="Y"
			<?php if (Config::showChangefeedMenu()) { echo 'checked'; } ?>
		/>
	</td>
</tr>
<?php

	$tabControl->BeginNextTab();
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/admin/group_rights.php');

$tabControl->Buttons();

?><input type="submit" name="Update" value="<?echo GetMessage('MAIN_SAVE')?>">
	<input type="reset" name="reset" value="<?echo GetMessage('MAIN_RESET')?>"><?
echo bitrix_sessid_post();

$tabControl->End();
?>
</form>
