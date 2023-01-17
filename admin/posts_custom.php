<?php
$prolog_admin_after = false;
require(__DIR__.'/../include/prolog_authorized_page.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_popup_admin.php');

IncludeModuleLangFile(__FILE__);

use vettich\sp3\Module;
use vettich\sp3\FormHelpers;
use vettich\sp3\IBlockHelpers;

CModule::IncludeModule('iblock');

if (isset($_POST['_save'])) {
	if (empty($_POST['_ACCOUNTS'])) {
		?>
		<div class="adm-info-message" style="display:block">
			<span style="color: red"><?=Module::m('ERR_ACCOUNTS_EMPTY')?></span>
		</div>
		<?php
	} else {
		$arTemplate = [];
		foreach ($_POST as $key => $value) {
			if (!is_string($key) || $key[0] != '_') {
				continue;
			}
			$newKey = substr($key, 1);
			$arTemplate[$newKey] = $value;
		}
		$arTemplate['ACCOUNTS'] = [];
		foreach ($_POST['_ACCOUNTS'] as $id => $v) {
			$arTemplate['ACCOUNTS'][] = $id;
		}
		$arTemplate['ID'] = 0;
		$arFilter = ['IBLOCK_ID' => $_POST['IBLOCK_ID']];
		foreach ($_POST['ELEMS'] as $id => $v) {
			$arFilter['ID'][] = $id;
		}
		$res = vettich\sp3\TemplateHelpers::publishWithTemplateStep2($arFilter, [$arTemplate]);
		header('Location: ?'.http_build_query(['res' => $res]));
		exit;
	}
}

if ($_GET['res']) {
	echo '<br/><br/>';
	echo Module::m('ADDED_N_POST2', ['#N#' => count($_GET['res'])]);
	echo '<br/><br/>';
	echo '<button class="adm-btn" onclick="window.close();">'.Module::m('CLOSE_WIN').'</button>';
	require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_popup_admin.php");
	exit;
}

$issetID = !empty($_GET['ID']);
$iblock_id = intval($_GET['IBLOCK_ID']);
$iblock_type = CIBlock::GetArrayByID($iblock_id, 'IBLOCK_TYPE_ID');
$iblock_type_name = CIBlockType::GetByIDLang($iblock_type, LANG);
$iblock_type_name = $iblock_type_name['NAME'];

$arFilter = ['IBLOCK_ID' => $_GET['IBLOCK_ID']];
$arFilterLogic = ['LOGIC' => 'OR'];
if (!empty($_GET['ELEMS'])) {
	$arFilterLogic[] = ['ID' => $_GET['ELEMS']];
}
if (!empty($_GET['SECTIONS'])) {
	$arFilterLogic[] = [
		'SECTION_ID' => $_GET['SECTIONS'],
		'INCLUDE_SUBSECTIONS' => true
	];
}
if (count($arFilterLogic) == 1) {
	exit;
}
$arFilter[] = $arFilterLogic;
$arElems = [];
$arElemsDefaults = [];
$rs = \CIBlockElement::GetList([], $arFilter, false, false, ['ID', 'IBLOCK_ID', 'NAME']);
while ($arFields = $rs->Fetch()) {
	$arElems[$arFields['ID']] = $arFields['NAME'];
	$arElemsDefaults[] = $arFields['ID'];
}

$params = [
	'heading1' => 'heading:#.IBLOCK_HEADING#',
	'IBLOCK_TYPE2' => [
		'type' => 'plaintext',
		'title' => '#.IBLOCK_TYPE#',
		'value' => "[$iblock_type] $iblock_type_name",
	],
	'IBLOCK_ID2' => [
		'type' => 'plaintext',
		'title' => '#.IBLOCK#',
		'value' => '['.$iblock_id.'] '.CIBlock::GetArrayByID($iblock_id, 'NAME'),
	],
	'IBLOCK_TYPE' => 'hidden:value='.$iblock_type,
	'IBLOCK_ID' => 'hidden:value='.$iblock_id,
	'ELEMS' => [
		'type' => 'checkbox',
		'title' => '#.ELEMS#',
		'multiple' => true,
		'options' => $arElems,
		'default_value' => $arElemsDefaults,
	],
];

$needUTM = ($_POST['_NEED_UTM'] == 'Y');
$params += [
	'heading2' => 'heading:#.DOMAIN_HEADING#',
	'_DOMAIN' => 'text:#.DOMAIN_NAME#:'.$_SERVER['HTTP_HOST'].':help=#.DOMAIN_NAME_HELP#',
	'_NEED_UTM' => 'checkbox:#.NEED_UTM#:N:refresh=Y:help=#.NEED_UTM_HELP#',
	'_UTM_SOURCE' => !$needUTM ? 'hidden' : 'text:#.UTM_SOURCE#:#SOCIAL_CODE#:help=#.UTM_SOURCE_HELP#',
	'_UTM_MEDIUM' => !$needUTM ? 'hidden' : 'text:#.UTM_MEDIUM#:social:help=#.UTM_MEDIUM_HELP#',
	'_UTM_CAMPAIGN' => !$needUTM ? 'hidden' : 'text:#.UTM_CAMPAIGN#:#IBLOCK_CODE#:help=#.UTM_CAMPAIGN_HELP#',
	'_UTM_TERM' => !$needUTM ? 'hidden' : 'text:#.UTM_TERM#:help=#.UTM_TERM_HELP#',
	'_UTM_CONTENT' => !$needUTM ? 'hidden' : 'text:#.UTM_CONTENT#:help=#.UTM_CONTENT_HELP#',
	/* 'heading6' => 'heading:#.CONDITIONS_HEADING#', */
	/* '_PUBLISH[CONDITIONS][ACTIVE]' => 'checkbox:#.PUBLISH_CONDITIONS_ACTIVE#:Y:help=#.PUBLISH_CONDITIONS_ACTIVE_HELP#', */
	/* '_CONDITIONS' => [ */
	/* 	'type' => 'group', */
	/* 	'title' => '#.CONDITIONS#', */
	/* 	'options' => [ */
	/* 		'field' => [ */
	/* 			'type' => 'select', */
	/* 			'title' => '', */
	/* 			'options' => IBlockHelpers::allPropsFor($iblock_id), */
	/* 			'params' => ['style' => 'max-width: 25em'], */
	/* 		], */
	/* 		'cmp' => [ */
	/* 			'type' => 'select', */
	/* 			'title' => '', */
	/* 			'options' => [ */
	/* 				'==' => '#.==#', */
	/* 				'!=' => '#.!=#', */
	/* 				'<=' => '#.<=#', */
	/* 				'>=' => '#.>=#', */
	/* 				'include' => '#.COND_INCLUDE#', */
	/* 				'notinclude' => '#.COND_NOTINCLUDE#', */
	/* 			], */
	/* 			'params' => ['style' => 'max-width: 10em'], */
	/* 		], */
	/* 		'value' => 'text::params=[size=auto]', */
	/* 	], */
	/* ], */
	'heading7' => 'heading:#.BINDING_TO_IBLOCK#',
	'_QUEUE_ELEMENT_UPDATE' => 'checkbox:#.PENDING_QUEUE_ELEMENT_UPDATE#:Y:help=#.PENDING_QUEUE_ELEMENT_UPDATE_HELP#',
	'_QUEUE_ELEMENT_DELETE' => 'checkbox:#.PENDING_QUEUE_ELEMENT_DELETE#:Y:help=#.PENDING_QUEUE_ELEMENT_DELETE_HELP#',
	'_QUEUE_DUPLICATE' => 'checkbox:#.PENDING_QUEUE_DUPLICATE#:N:help=#.PENDING_QUEUE_DUPLICATE_HELP#',
];
/* $individ = ($_POST['_PUBLISH']['COMMON']['INDIVIDUAL_SETTINGS'] == 'Y' */
/* 	or $data->get('_PUBLISH[COMMON][INDIVIDUAL_SETTINGS]') == 'Y'); */

$params = array_merge($params, FormHelpers::buildAccountsList('_ACCOUNTS'));

$templateDataParams = [
	// 'none_acc' => 'plaintext::'.vettich\sp3\devform\Module::m('#.NONE_ACCOUNTS#'),
	'heading5' => 'heading:#.COMMON_DESCRIPTION#',
	/* '_PUBLISH[COMMON][INDIVIDUAL_SETTINGS]' => 'checkbox:#.PUBLISH_INDIVIDUAL_SETTINGS#:N:help=#.PUBLISH_INDIVIDUAL_SETTINGS_HELP#:refresh=Y', */
	'_PUBLISH[COMMON][TEXT]' => [
		'type' => 'textarea',
		'title' => '#.PUBLISH_TEXT#',
		'help' => '#.PUBLISH_TEXT_HELP#',
		'items' => IBlockHelpers::allPropsMacrosFor($iblock_id),
		'default_value' => "#NAME##BR#\n#BR#\n#PREVIEW_TEXT#",
		'params' => ['rows' => 6],
	],
	'_PUBLISH[COMMON][TAGS]' => [
		'type' => 'select',
		'title' => '#.PUBLISH_TAGS#',
		'help' => '#.PUBLISH_TAGS_HELP#',
		'options' => IBlockHelpers::allPropsFor($iblock_id),
		'default_value' => 'TAGS',
	],
	'_PUBLISH[COMMON][LINK]' => [
		'type' => 'select',
		'title' => '#.PUBLISH_LINK#',
		'help' => '#.PUBLISH_LINK_HELP#',
		'options' => IBlockHelpers::allPropsFor($iblock_id),
		'default_value' => 'DETAIL_PAGE_URL',
	],
	'_PUBLISH[COMMON][MAIN_PICTURE]' => [
		'type' => 'select',
		'title' => '#.PUBLISH_MAIN_PICTURE#',
		'help' => '#.PUBLISH_MAIN_PICTURE_HELP#',
		'options' => IBlockHelpers::allPropsFor($iblock_id),
		'default_value' => 'DETAIL_PICTURE',
	],
	'_PUBLISH[COMMON][OTHER_PICTURE]' => [
		'type' => 'select',
		'title' => '#.PUBLISH_OTHER_PICTURE#',
		'help' => '#.PUBLISH_OTHER_PICTURE_HELP#',
		'options' => IBlockHelpers::allPropsFor($iblock_id),
		'default_value' => 'PROPERTY_MORE_PICTURES',
	],
	'_PUBLISH[COMMON][PUBLISH_AT]' => [
		'type' => 'select',
		'title' => '#.POST_PUBLISH_AT#',
		'help' => '#.POST_PUBLISH_AT_HELP#',
		'options' => IBlockHelpers::allPropsFor($iblock_id),
		'default_value' => 'DATE_ACTIVE_FROM',
	],
	'heading6' => 'heading:#.POST_VK_TITLE#',
	'_PUBLISH[VK][FROM_GROUP]' => 'checkbox:#.POST_VK_FROM_GROUP#:Y:help=#.POST_VK_FROM_GROUP_HELP#',
	'_PUBLISH[VK][SIGNED]' => 'checkbox:#.POST_VK_SIGNED#:help=#.POST_VK_SIGNED_HELP#',
];

$tabs = [
	[
		'name' => '#.TEMPLATE_GENERAL#',
		'title' => '#.TEMPLATE_GENERAL_SETTINGS#',
		'params' => $params,
	],
	[
		'name' => '#.TEMPLATE_DATA#',
		'title' => '#.TEMPLATE_DATA_SETTINGS#',
		'params' => $templateDataParams,
	],
];

(new \vettich\sp3\devform\AdminForm('devform', [
	'pageTitle' => ($id > 0 ? '#.EDIT_RECORD#' : '#.ADD_RECORD#'),
	'tabs' => $tabs,
	'buttons' => Module::hasGroupWrite() ? [
		'_save' => 'buttons\saveSubmit:#.PUBLISH_BTN#',
		'_cancel' => 'buttons\submit:#.CANCEL#:params=[onclick=window.close();]',
	] : [],
]))->render();

/* require(__DIR__.'/../include/epilog_authorized_page.php'); */
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_popup_admin.php");
