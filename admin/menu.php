<?php
$module_id = 'vettich.sp3';
if (!$APPLICATION->GetGroupRight($module_id)>'D') {
	return false;
}

if (!CModule::IncludeModule($module_id)) {
	return false;
}

IncludeModuleLangFile(__FILE__);
$GLOBALS['APPLICATION']->SetAdditionalCSS("/bitrix/css/$module_id/menu.css");
$GLOBALS['APPLICATION']->AddHeadScript("/bitrix/js/$module_id/script.js");

use vettich\sp3\Module;

$menuName = Module::m('MENU_TEXT', ['#V#' => Module::version()]);

$aMenu = [
	'parent_menu'	=> 'global_menu_services',
	'sort'			=> 99,
	'icon'			=> 'vettich_sp3',
	'text'			=> $menuName,
	'items_id'		=> 'vettich_sp3',
	'module_id'		=> $module_id,
	'dynamic'		=> 'true',
	'items'			=> [],
];

$isExists = method_exists($this, 'IsSectionActive');
if (!($isExists && $this->IsSectionActive('vettich_sp3'))) {
	return $aMenu;
}

if (Module::isAuth()) {
	$aMenu['items'][] = [
		'text'		=> Module::m('MENU_USER'),
		'url'		=> '/bitrix/admin/vettich.sp3.user.php',
	];
	$aMenu['items'][] = [
		'text'		=> Module::m('MENU_ACCOUNTS'),
		'url'		=> '/bitrix/admin/vettich.sp3.accounts_list.php',
		'more_url' => [
			'/bitrix/admin/vettich.sp3.accounts_add.php',
			'/bitrix/admin/vettich.sp3.accounts_edit.php',
		]
	];
	$aMenu['items'][] = [
		'text'		=> Module::m('MENU_POSTS'),
		'url'		=> '/bitrix/admin/vettich.sp3.posts_list.php',
		'more_url' => [
			'/bitrix/admin/vettich.sp3.posts_edit.php',
		]
	];
	$aMenu['items'][] = [
		'text'		=> Module::m('MENU_TEMPLATES'),
		'url'		=> '/bitrix/admin/vettich.sp3.templates_list.php',
		'more_url' => [
			'/bitrix/admin/vettich.sp3.templates_edit.php',
		]
	];
//$aMenu['items'][] = [
	//	'text'		=> Module::m('MENU_SETTINGS'),
	//	'url'		=> '/bitrix/admin/settings.php?lang=ru&mid='.$module_id,
	//	'items_id'	=> 'vettich_sp3_settings',
	//];
} else {
	$aMenu['items'][] = [
		'text'		=> Module::m('MENU_START_USE'),
		'url'		=> '/bitrix/admin/vettich.sp3.start_use.php',
	];
}

return $aMenu;
