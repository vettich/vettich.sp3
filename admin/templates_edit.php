<?php
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Module;
use vettich\sp3\IBlockHelpers;
use vettich\devform\types;

CModule::IncludeModule('iblock');

$issetID = !empty($_GET['ID']);

$arIblockTypes = ['' => Module::m('IBLOCK_TYPE_SELECT')];
$rsIblockTypes = CIBlockType::GetList();
while ($ar = $rsIblockTypes->Fetch()) {
	if ($arIBType = CIBlockType::GetByIDLang($ar["ID"], LANG)) {
		$arIblockTypes["$ar[ID]"] = "[$ar[ID]] ".htmlspecialcharsEx($arIBType["NAME"]);
	}
}

$params = [
	'_NAME' => 'text:#VDF_NAME#::help=#.NAME_HELP#:params=[placeholder=#.NAME_PLACEHOLDER#]',
	'NAME_AUTO' => 'hidden::',
	'_IS_ENABLE' => 'checkbox:#VDF_IS_ENABLE#:Y:help=#.IS_ENABLE_HELP#',
	'_IS_AUTO' => 'checkbox:#.IS_AUTO#:Y:help=#.IS_AUTO_HELP#',
	'heading1' => 'heading:#.IBLOCK_HEADING#',
	'_IBLOCK_TYPE' => [
		'type' => 'select',
		'title' => '#.IBLOCK_TYPE#',
		'help' => '#.IBLOCK_TYPE_HELP#',
		'options' => $arIblockTypes,
		'params' => ['onchange' => 'Vettich.Devform.Refresh(this);'],
	],
];
$data = new vettich\devform\data\orm([
	'dbClass' => 'vettich\sp3\db\template',
	'prefix' => '_',
]);

$isSections = false;
$name_auto = '';
$iblock_type = $_POST['_IBLOCK_TYPE'] ?: $data->get('_IBLOCK_TYPE');
$iblock_id = $_POST['_IBLOCK_ID'] ?: $data->get('_IBLOCK_ID');
// get iblock ids list if iblock type selected
if ($iblock_type) {
	// выборка инфоблоков
	$arIblockIds = ['' => Module::m('#.IBLOCK_ID_SELECT#')];
	$rsIblockIds = CIBlock::GetList([], [
		'TYPE' => $iblock_type,
	]);
	while ($ar = $rsIblockIds->Fetch()) {
		$arIblockIds["$ar[ID]"] = "[$ar[ID]] $ar[NAME]";
	}

	if ($iblock_type && $iblock_id && isset($arIblockIds[$iblock_id])) {
		// выборка секций
		$arSections = ['' => Module::m('IBLOCK_SECTION_SELECT')];
		$arFilter = ['IBLOCK_ID' => $iblock_id, 'IBLOCK_TYPE' => $iblock_type, 'ACTIVE' => 'Y'];
		$arSelect = ['ID', 'NAME', 'DEPTH_LEVEL'];
		$rsSections = \CIBlockSection::GetTreeList($arFilter, $arSelect);
		while ($ar = $rsSections->Fetch()) {
			$arSections["$ar[ID]"] = str_repeat('- ', intval($ar['DEPTH_LEVEL'])-1)."[$ar[ID]] $ar[NAME]";
		}
	}

	if (($iblock_id = $_POST['_IBLOCK_ID']) or ($iblock_id = $data->get('_IBLOCK_ID')) && !empty($arIblockIds[$iblock_id])) {
		$s = $arIblockIds[$iblock_id];
		if (($pos = strpos($s, ']')) !== false) {
			$s = substr($s, $pos+1);
		}
		$name_auto = trim($s);
	}

	$isSections = count($arSections) > 1;
	$params['_IBLOCK_ID'] = [
		'type' => 'select',
		'title' => '#.IBLOCK#',
		'help' => '#.IBLOCK_HELP#',
		'options' => $arIblockIds,
		'params' => ['onchange' => 'Vettich.Devform.Refresh(this);'],
	];
	if ($isSections) {
		$params['_IS_SECTIONS'] = 'checkbox:#.IBLOCK_IS_SECTIONS#:refresh=Y:help=#.IBLOCK_IS_SECTIONS_HELP#';
		if ($_POST['_IS_SECTIONS'] == 'Y' or (empty($_POST) && $data->get('_IS_SECTIONS') == 'Y')) {
			$params['_IBLOCK_SECTIONS'] = [
				'type' => 'multiselect',
				'title' => '#.IBLOCK_SECTIONS#',
				'help' => '#.IBLOCK_SECTIONS_HELP#',
				'options' => $arSections,
				'params' => ['size' => count($arSections) > 10 ? 10 : count($arSections)],
			];
		}
	}
}

// автоподстановка названия
$name = $_POST['_NAME'];
$name_auto_old = $_POST['NAME_AUTO'];
$name_auto = str_replace(['[', ']', '='], ['\[', '\]', '\='], $name_auto);
if ($name == $name_auto_old or empty($name)) {
	$params['_NAME'] = 'text:#VDF_NAME#:'.$name_auto.':help=#.NAME_HELP#:params=[placeholder=#.NAME_PLACEHOLDER#]';
}
$params['NAME_AUTO'] = 'hidden::value='.$name_auto;
// $params['NAME_AUTO_2'] = 'plaintext:value='.$name

if (!$iblock_id) {
	if (!empty($_POST) && $_POST['needDecodeFromHidden'] != 'Y') {
		$_POST['_CONDITIONS'] = htmlspecialcharsEx(serialize($_POST['_CONDITIONS']));
		$_POST['_PUBLISH'] = htmlspecialcharsEx(serialize($_POST['_PUBLISH']));
	}
	$params += [
		'_DOMAIN' => 'hidden',
		'_URL_PARAMS' => 'hidden',
		'_CONDITIONS' => 'hidden',
		'_PUBLISH' => 'hidden',
		'needDecodeFromHidden' => 'hidden::Y',
	];
} else {
	if ($_POST['needDecodeFromHidden'] == 'Y') {
		$_POST['_CONDITIONS'] = unserialize(htmlspecialcharsBack($_POST['_CONDITIONS']));
		$_POST['_PUBLISH'] = unserialize(htmlspecialcharsBack($_POST['_PUBLISH']));
	}
	$params += [
		'heading2' => 'heading:#.DOMAIN_HEADING#',
		'_DOMAIN' => 'text:#.DOMAIN_NAME#:'.$_SERVER['SERVER_NAME'].':help=#.DOMAIN_NAME_HELP#',
		'_URL_PARAMS' => 'text:#.URL_PARAMS#:utm_source\=#SOCIAL_ID#&utm_medium\=cpc:help=#.URL_PARAMS_HELP#',
		'heading6' => 'heading:#.CONDITIONS_HEADING#',
		'_PUBLISH[CONDITIONS][ACTIVE]' => 'checkbox:#.PUBLISH_CONDITIONS_ACTIVE#:Y:help=#.PUBLISH_CONDITIONS_ACTIVE_HELP#',
		'_CONDITIONS' => [
			'type' => 'group',
			'title' => '#.CONDITIONS#',
			'options' => [
				'field' => [
					'type' => 'select',
					'title' => '',
					'options' => IBlockHelpers::allPropsFor($iblock_id),
					'params' => ['style' => 'max-width: 25em'],
				],
				'cmp' => [
					'type' => 'select',
					'title' => '',
					'options' => [
						'==' => '#.==#',
						'!=' => '#.!=#',
						'<=' => '#.<=#',
						'>=' => '#.>=#',
						'include' => '#.COND_INCLUDE#',
						'notinclude' => '#.COND_NOTINCLUDE#',
					],
					'params' => ['style' => 'max-width: 10em'],
				],
				'value' => 'text::params=[size=auto]',
			],
		],
		'heading7' => 'heading:#.QUEUE_SETTINGS#',
		'_QUEUE_ELEMENT_UPDATE' => 'checkbox:#.PENDING_QUEUE_ELEMENT_UPDATE#:Y:help=#.PENDING_QUEUE_ELEMENT_UPDATE_HELP#',
		'_QUEUE_ELEMENT_DELETE' => 'checkbox:#.PENDING_QUEUE_ELEMENT_DELETE#:Y:help=#.PENDING_QUEUE_ELEMENT_DELETE_HELP#',
		'_QUEUE_DUPLICATE' => 'checkbox:#.PENDING_QUEUE_DUPLICATE#:N:help=#.PENDING_QUEUE_DUPLICATE_HELP#',
		'heading4' => 'heading:#.CHOOSE_POST_ACCOUNTS#',
	];
	$individ = ($_POST['_PUBLISH']['COMMON']['INDIVIDUAL_SETTINGS'] == 'Y'
		or $data->get('_PUBLISH[COMMON][INDIVIDUAL_SETTINGS]') == 'Y');

	$res = Module::api()->accountsList();
	$accountsMap = [];
	foreach ($res['accounts'] as $account) {
		$accountsMap[$account['id']] = $account['name'];
	}
	$accountsMap = Module::convertToSiteCharset($accountsMap);
	$params[] = new \vettich\devform\types\checkbox($paramid, [
		'title' => '#.POST_ACCOUNTS#',
		'options' => $accountsMap,
		'multiple' => true,
		// 'refresh' => true,
		/* 'params' => $params, */
	]);

	/* $params += (array) Module::socialAccountsForDevForm('_ACCOUNTS', $individ ? ['onclick' => 'Vettich.Devform.Refresh(this);'] : []); */
	$templateDataParams = [
		// 'none_acc' => 'plaintext::'.vettich\devform\Module::m('#.NONE_ACCOUNTS#'),
		'heading5' => 'heading:#.COMMON_DESCRIPTION#',
		/* '_PUBLISH[COMMON][INDIVIDUAL_SETTINGS]' => 'checkbox:#.PUBLISH_INDIVIDUAL_SETTINGS#:N:help=#.PUBLISH_INDIVIDUAL_SETTINGS_HELP#:refresh=Y', */
		'_PUBLISH[COMMON][MAIN_PICTURE]' => [
			'type' => $individ ? 'hidden' : 'select',
			'title' => '#.PUBLISH_MAIN_PICTURE#',
			'help' => '#.PUBLISH_MAIN_PICTURE_HELP#',
			'options' => IBlockHelpers::allPropsFor($iblock_id),
			'default_value' => 'DETAIL_PICTURE',
		],
		'_PUBLISH[COMMON][OTHER_PICTURE]' => [
			'type' => $individ ? 'hidden' : 'select',
			'title' => '#.PUBLISH_OTHER_PICTURE#',
			'help' => '#.PUBLISH_OTHER_PICTURE_HELP#',
			'options' => IBlockHelpers::allPropsFor($iblock_id),
			'default_value' => 'PROPERTY_MORE_PICTURES',
		],
		'_PUBLISH[COMMON][LINK]' => [
			'type' => $individ ? 'hidden' : 'select',
			'title' => '#.PUBLISH_LINK#',
			'help' => '#.PUBLISH_LINK_HELP#',
			'options' => IBlockHelpers::allPropsFor($iblock_id),
			'default_value' => 'DETAIL_PAGE_URL',
		],
		'_PUBLISH[COMMON][TEXT]' => [
			'type' => $individ ? 'hidden' : 'textarea',
			'title' => '#.PUBLISH_TEXT#',
			'help' => '#.PUBLISH_TEXT_HELP#',
			'items' => IBlockHelpers::allPropsMacrosFor($iblock_id),
			'default_value' => "#NAME##BR#\n#BR#\n#PREVIEW_TEXT#",
			'params' => ['rows' => 6],
		],
	];
}

$tabs = [
	[
		'name' => '#.TEMPLATE_GENERAL#',
		'title' => '#.TEMPLATE_GENERAL_SETTINGS#',
		'params' => $params,
	],
	/* [ */
	/* 	'name' => '#.PENDING_POSTING#', */
	/* 	'title' => '#.PENDING_POSTING_SETTINGS#', */
	/* 	'params' => [ */
	/* 	], */
	/* ], */
];
if ($iblock_id) {
	$tabs[] = [
		'name' => '#.TEMPLATE_DATA#',
		'title' => '#.TEMPLATE_DATA_SETTINGS#',
		'params' => $templateDataParams,
	];
}

if ($individ
	&& (($accounts = array_keys($_POST['_ACCOUNTS']))
		or (empty($_POST) && $accounts = $data->get('_ACCOUNTS')))) {
	$show_types = [];
	foreach ($accounts as $key => $v) {
		$social = Module::socialForId($v);
		if (empty($social)
			|| in_array($social['id'], $show_types)) {
			continue;
		}
		$show_types[] = $social['id'];
		$_params = $social['class']->publishParams($iblock_id, '_PUBLISH['.$social['class']::$socialid.']');
		/**
		 * отображение условий публикаций в соц. сеть
		 */
		$_params['heading_cond_'.$social['class']::$socialid] = 'heading:#.CONDITIONS_POST#';
		$_params['_PUBLISH['.$social['class']::$socialid.'][CONDITIONS]'] = [
			'type' => 'group',
			'title' => '#.CONDITIONS#',
			'options' => [
				'field' => [
					'type' => 'select',
					'title' => '',
					'options' => Module::allPropsFor($iblock_id),
				],
				'cmp' => [
					'type' => 'select',
					'title' => '',
					'options' => [
						'==' => '#.==#',
						'!=' => '#.!=#',
						'<=' => '#.<=#',
						'>=' => '#.>=#',
						'include' => '#.COND_INCLUDE#',
						'notinclude' => '#.COND_NOTINCLUDE#',
					],
				],
				'value' => 'text::params=[size=auto]',
			],
		];
		$tabs[] = [
			'name' => $social['name'],
			'title' => '#.SETTINGS_FOR# '.$social['name'],
			'params' => $_params,
		];
	}
}

(new \vettich\devform\AdminForm('devform', [
	'pageTitle' => ($id > 0 ? '#.EDIT_RECORD#' : '#.ADD_RECORD#'),
	'tabs' => $tabs,
	'buttons' => [
		'_save' => 'buttons\saveSubmit:#VDF_SAVE#',
		'_apply' => 'buttons\submit:#VDF_APPLY#',
	],
	'data' => $data,
	'on beforeSave' => function (&$arValues, $args, $obj) {
		if (isset($arValues['_PERIOD_FROM'])) {
			$arValues['_PERIOD_FROM'] = Module::timeFromUserTime($arValues['_PERIOD_FROM']);
		}
		if (isset($arValues['_PERIOD_TO'])) {
			$arValues['_PERIOD_TO'] = Module::timeFromUserTime($arValues['_PERIOD_TO']);
		}
		return true;
	},
]))->render();

require(__DIR__.'/../include/epilog_authorized_page.php');
