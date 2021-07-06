<?php
require(__DIR__.'/../include/prolog_authorized_page.php');

IncludeModuleLangFile(__FILE__);

use vettich\sp3\Module;
use vettich\sp3\FormHelpers;
use vettich\sp3\IBlockHelpers;
use vettich\sp3\TextProcessor;
use vettich\sp3\devform\types;

CModule::IncludeModule('iblock');
\CJSCore::Init(['vettich_sp3_script']);

$issetID  = !empty($_GET['ID']);
$dataArgs = [
	'dbClass' => 'vettich\sp3\db\template',
	'prefix'  => '_'
];
if (!$issetID && isset($_GET['FROM_ID'])) {
	$dataArgs['filter'] = ['ID' => $_GET['FROM_ID']];
}
$data = new vettich\sp3\devform\data\orm($dataArgs);

$arIblockTypes = ['' => Module::m('IBLOCK_TYPE_SELECT')];
$rsIblockTypes = CIBlockType::GetList();
while ($ar = $rsIblockTypes->Fetch()) {
	if ($arIBType = CIBlockType::GetByIDLang($ar["ID"], LANG)) {
		$arIblockTypes["$ar[ID]"] = "[$ar[ID]] ".htmlspecialcharsEx($arIBType["NAME"]);
	}
}

$params = [
	/* '_ID' => 'hidden:'.(!$issetID ? '' : 'value=0'), */
	'_NAME'        => 'text:#VDF_NAME#::help=#.NAME_HELP#:params=[placeholder=#.NAME_PLACEHOLDER#]',
	'NAME_AUTO'    => 'hidden::',
	'_IS_ENABLE'   => 'checkbox:#VDF_IS_ENABLE#:Y:help=#.IS_ENABLE_HELP#',
	'_IS_AUTO'     => 'checkbox:#.IS_AUTO#:Y:help=#.IS_AUTO_HELP#',
	'heading1'     => 'heading:#.IBLOCK_HEADING#',
	'_IBLOCK_TYPE' => [
		'type'    => 'select',
		'title'   => '#.IBLOCK_TYPE#',
		'help'    => '#.IBLOCK_TYPE_HELP#',
		'options' => $arIblockTypes,
		'params'  => ['onchange' => 'VettichSP3.Devform.Refresh(this);'],
	],
];
if ($issetID) {
	$params['_ID'] = 'hidden';
}

$isSections  = false;
$name_auto   = '';
$iblock_type = $_POST['_IBLOCK_TYPE'] ?: $data->get('_IBLOCK_TYPE');
$iblock_id   = $_POST['_IBLOCK_ID'] ?: $data->get('_IBLOCK_ID');
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
		$arFilter   = ['IBLOCK_ID' => $iblock_id, 'IBLOCK_TYPE' => $iblock_type, 'ACTIVE' => 'Y'];
		$arSelect   = ['ID', 'NAME', 'DEPTH_LEVEL'];
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

	$isSections           = count($arSections) > 1;
	$params['_IBLOCK_ID'] = [
		'type'    => 'select',
		'title'   => '#.IBLOCK#',
		'help'    => '#.IBLOCK_HELP#',
		'options' => $arIblockIds,
		'params'  => ['onchange' => 'VettichSP3.Devform.Refresh(this);'],
	];
	if ($isSections) {
		$params['_IS_SECTIONS'] = 'checkbox:#.IBLOCK_IS_SECTIONS#:refresh=Y:help=#.IBLOCK_IS_SECTIONS_HELP#';
		if ($_POST['_IS_SECTIONS'] == 'Y' or (empty($_POST) && $data->get('_IS_SECTIONS') == 'Y')) {
			$params['_IBLOCK_SECTIONS'] = [
				'type'    => 'multiselect',
				'title'   => '#.IBLOCK_SECTIONS#',
				'help'    => '#.IBLOCK_SECTIONS_HELP#',
				'options' => $arSections,
				'params'  => ['size' => count($arSections) > 10 ? 10 : count($arSections)],
			];
		}
	}
}

// автоподстановка названия
$name          = $_POST['_NAME'];
$name_auto_old = $_POST['NAME_AUTO'];
$name_auto     = str_replace(['[', ']', '='], ['\[', '\]', '\='], $name_auto);
if ($name == $name_auto_old or empty($name)) {
	$params['_NAME'] = 'text:#VDF_NAME#:'.$name_auto.':help=#.NAME_HELP#:params=[placeholder=#.NAME_PLACEHOLDER#]';
}
$params['NAME_AUTO'] = 'hidden::value='.$name_auto;
// $params['NAME_AUTO_2'] = 'plaintext:value='.$name

if (!$iblock_id) {
	if (!empty($_POST) && $_POST['needDecodeFromHidden'] != 'Y') {
		$_POST['_CONDITIONS'] = htmlspecialcharsEx(serialize($_POST['_CONDITIONS']));
		$_POST['_PUBLISH']    = htmlspecialcharsEx(serialize($_POST['_PUBLISH']));
	}
	$params += [
		'_DOMAIN'              => 'hidden',
		'_URL_PARAMS'          => 'hidden',
		'_CONDITIONS'          => 'hidden',
		'_PUBLISH'             => 'hidden',
		'needDecodeFromHidden' => 'hidden::Y',
	];
} else {
	if ($_POST['needDecodeFromHidden'] == 'Y') {
		$_POST['_CONDITIONS'] = unserialize(htmlspecialcharsBack($_POST['_CONDITIONS']));
		$_POST['_PUBLISH']    = unserialize(htmlspecialcharsBack($_POST['_PUBLISH']));
	}
	$needUTM = ($_POST['_NEED_UTM'] == 'Y' || (empty($_POST) && $data->get('_NEED_UTM') == 'Y'));
	$params += [
		'heading2'      => 'heading:#.DOMAIN_HEADING#',
		'_DOMAIN'       => 'text:#.DOMAIN_NAME#:'.$_SERVER['HTTP_HOST'].':help=#.DOMAIN_NAME_HELP#',
		'_NEED_UTM'     => 'checkbox:#.NEED_UTM#:N:refresh=Y:help=#.NEED_UTM_HELP#',
		'_UTM_SOURCE'   => !$needUTM ? 'hidden' : 'text:#.UTM_SOURCE#:#SOCIAL_CODE#:help=#.UTM_SOURCE_HELP#',
		'_UTM_MEDIUM'   => !$needUTM ? 'hidden' : 'text:#.UTM_MEDIUM#:social:help=#.UTM_MEDIUM_HELP#',
		'_UTM_CAMPAIGN' => !$needUTM ? 'hidden' : 'text:#.UTM_CAMPAIGN#:#IBLOCK_CODE#:help=#.UTM_CAMPAIGN_HELP#',
		'_UTM_TERM'     => !$needUTM ? 'hidden' : 'text:#.UTM_TERM#:help=#.UTM_TERM_HELP#',
		'_UTM_CONTENT'  => !$needUTM ? 'hidden' : 'text:#.UTM_CONTENT#:help=#.UTM_CONTENT_HELP#',
		/* '_URL_PARAMS' => 'text:#.URL_PARAMS#:utm_source\=#SOCIAL_ID#&utm_medium\=cpc:help=#.URL_PARAMS_HELP#', */
		'heading6'                     => 'heading:#.CONDITIONS_HEADING#',
		'_PUBLISH[CONDITIONS][ACTIVE]' => 'checkbox:#.PUBLISH_CONDITIONS_ACTIVE#:Y:help=#.PUBLISH_CONDITIONS_ACTIVE_HELP#',
		'_CONDITIONS'                  => [
			'type'    => 'group',
			'title'   => '#.CONDITIONS#',
			'help'    => '#.CONDITIONS_HELP#',
			'options' => [
				'field' => [
					'type'    => 'select',
					'title'   => '',
					'options' => IBlockHelpers::allPropsFor($iblock_id),
					'params'  => ['style' => 'max-width: 25em'],
				],
				'cmp' => [
					'type'    => 'select',
					'title'   => '',
					'options' => [
						'=='         => '#.==#',
						'!='         => '#.!=#',
						'<='         => '#.<=#',
						'>='         => '#.>=#',
						'include'    => '#.COND_INCLUDE#',
						'notinclude' => '#.COND_NOTINCLUDE#',
					],
					'params' => ['style' => 'max-width: 10em'],
				],
				'value' => 'text::params=[size=auto]',
			],
		],
		'heading7'            => 'heading:#.BINDING_TO_IBLOCK#',
		'_UPDATE_IN_NETWORKS' => 'checkbox:#.PENDING_QUEUE_ELEMENT_UPDATE#:Y:help=#.PENDING_QUEUE_ELEMENT_UPDATE_HELP#',
		'_DELETE_IN_NETWORKS' => 'checkbox:#.PENDING_QUEUE_ELEMENT_DELETE#:Y:help=#.PENDING_QUEUE_ELEMENT_DELETE_HELP#',
		'_QUEUE_DUPLICATE'    => 'checkbox:#.PENDING_QUEUE_DUPLICATE#:N:help=#.PENDING_QUEUE_DUPLICATE_HELP#',
	];

	$params = array_merge($params, FormHelpers::buildAccountsList('_ACCOUNTS'));

	$templateDataParams = [
		// 'none_acc' => 'plaintext::'.vettich\sp3\devform\Module::m('#.NONE_ACCOUNTS#'),
		'heading5' => 'heading:#.COMMON_DESCRIPTION#',
		/* '_PUBLISH[COMMON][INDIVIDUAL_SETTINGS]' => 'checkbox:#.PUBLISH_INDIVIDUAL_SETTINGS#:N:help=#.PUBLISH_INDIVIDUAL_SETTINGS_HELP#:refresh=Y', */
		'_PUBLISH[COMMON][TEXT]' => [
			'type'          => 'textarea',
			'title'         => '#.PUBLISH_TEXT#',
			'help'          => '#.PUBLISH_TEXT_HELP#',
			'items'         => IBlockHelpers::allPropsMacrosFor($iblock_id),
			'default_value' => "#NAME##BR#\n#BR#\n#PREVIEW_TEXT#",
			'params'        => ['rows' => 6],
		],
		'_PUBLISH[COMMON][TAGS]' => [
			'type'          => 'select',
			'title'         => '#.PUBLISH_TAGS#',
			'help'          => '#.PUBLISH_TAGS_HELP#',
			'options'       => IBlockHelpers::allPropsFor($iblock_id, IBlockHelpers::STRING_TYPE),
			'default_value' => 'TAGS',
		],
		'_PUBLISH[COMMON][LINK]' => [
			'type'          => 'select',
			'title'         => '#.PUBLISH_LINK#',
			'help'          => '#.PUBLISH_LINK_HELP#',
			'options'       => IBlockHelpers::allPropsFor($iblock_id, IBlockHelpers::URL_TYPE),
			'default_value' => 'DETAIL_PAGE_URL',
		],
		'_PUBLISH[COMMON][MAIN_PICTURE]' => [
			'type'          => 'select',
			'title'         => '#.PUBLISH_MAIN_PICTURE#',
			'help'          => '#.PUBLISH_MAIN_PICTURE_HELP#',
			'options'       => IBlockHelpers::allPropsFor($iblock_id, IBlockHelpers::FILE_TYPE),
			'default_value' => 'DETAIL_PICTURE',
		],
		'_PUBLISH[COMMON][OTHER_PICTURE]' => [
			'type'          => 'select',
			'title'         => '#.PUBLISH_OTHER_PICTURE#',
			'help'          => '#.PUBLISH_OTHER_PICTURE_HELP#',
			'options'       => IBlockHelpers::allPropsFor($iblock_id, IBlockHelpers::FILE_TYPE),
			'default_value' => 'PROPERTY_MORE_PICTURES',
		],
		'_PUBLISH[COMMON][OTHER_PICTURE_2]' => [
			'type'          => 'select',
			'title'         => '#.PUBLISH_OTHER_PICTURE_2#',
			'help'          => '#.PUBLISH_OTHER_PICTURE_2_HELP#',
			'options'       => IBlockHelpers::allPropsFor($iblock_id, IBlockHelpers::FILE_TYPE),
			'default_value' => '',
		],
		'_PUBLISH[COMMON][PUBLISH_AT]' => [
			'type'          => 'select',
			'title'         => '#.POST_PUBLISH_AT#',
			'help'          => '#.POST_PUBLISH_AT_HELP#',
			'options'       => IBlockHelpers::allPropsFor($iblock_id, IBlockHelpers::DATE_TYPE),
			'default_value' => 'DATE_ACTIVE_FROM',
		],

		'heading6'                 => 'heading:#.POST_VK_TITLE#',
		'_PUBLISH[VK][FROM_GROUP]' => 'checkbox:#.POST_VK_FROM_GROUP#:Y:help=#.POST_VK_FROM_GROUP_HELP#',
		'_PUBLISH[VK][SIGNED]'     => 'checkbox:#.POST_VK_SIGNED#:help=#.POST_VK_SIGNED_HELP#',

		/* 'heading7' => 'heading:#.POST_OK_TITLE#', */
		/* '_PUBLISH[OK][HIDDEN_POST]' => 'checkbox:#.POST_OK_HIDDEN_POST#:Y:help=#.POST_OK_HIDDEN_POST_HELP#', */
		/* '_PUBLISH[OK][ADS_POST]' => 'checkbox:#.POST_OK_ADS_POST#:help=#.POST_OK_ADS_POST_HELP#', */
	];

	$unloadDateTimeSelectHtml = '';
	$weekdays                 = [
		'ALL' => Module::m('WEEKDAY_ALL'),
		'MON' => Module::m('WEEKDAY_MON'),
		'TUE' => Module::m('WEEKDAY_TUE'),
		'WEN' => Module::m('WEEKDAY_WEN'),
		'THU' => Module::m('WEEKDAY_THU'),
		'FRI' => Module::m('WEEKDAY_FRI'),
		'SAT' => Module::m('WEEKDAY_SAT'),
		'SUN' => Module::m('WEEKDAY_SUN'),
	];
	$weekdaysValues = $_POST["_UNLOAD_DATETIME"] ?: $data->get("_UNLOAD_DATETIME");
	if (is_null($weekdaysValues)) {
		$weekdaysValues = ['ALL' => ['9:00', '12:00']];
	}
	foreach ($weekdays as $weekdayKey => $weekdayName) {
		$weekdayValues = $weekdaysValues[$weekdayKey] ?: [];
		$div           = '<div class="vettich-sp3-time-line"><span class="vettich-sp3-time-title">'.$weekdayName.'</span>';
		foreach ($weekdayValues as $value) {
			$div .= '<span class="vettich-sp3-time-item-wrap">';
			$div .= '<select class="vettich-sp3-time-item" name="_UNLOAD_DATETIME['.$weekdayKey.'][]">';
			for ($i = 0; $i < 24; $i++) {
				$val = "$i:00";
				$div .= '<option value="'.$val.'"'.($val == $value ? 'selected' : '').'>'.$val.'</option>';
				$val = "$i:30";
				$div .= '<option value="'.$val.'"'.($val == $value ? 'selected' : '').'>'.$val.'</option>';
			}
			$div .= '</select>';
			$div .= '<span class="vettich-sp3-time-remove-btn" onclick="VettichSP3.unloadDateTimeRemove(event)">x</span>';
			$div .= '</span>';
		}
		$div                      .= '<span class="vettich-sp3-time-plus-btn" onclick="VettichSP3.unloadDateTimeAdd(event, \''.$weekdayKey.'\')">+</span>';
		$div                      .= '</div>';
		$unloadDateTimeSelectHtml .= $div;
	}

	$unloadParams = [
		'_UNLOAD_ENABLE'          => 'checkbox:#.UNLOAD_ENABLE#:N:help=#.UNLOAD_ENABLE_HELP#',
		'_UNLOAD_KEEP_INTERVAL'   => 'checkbox:#.UNLOAD_KEEP_INTERVAL#:Y:help=#.UNLOAD_KEEP_INTERVAL_HELP#',
		'UNLOAD_DATETIME_HEADING' => 'heading:#.UNLOAD_DATETIME_HEADER#',
		'_UNLOAD_DATETIME'        => 'hidden',
		'UNLOAD_DATETIME_SELECT'  => [
			'type'  => 'html',
			'title' => '#.UNLOAD_DATETIME_SELECT#',
			'help'  => '#.UNLOAD_DATETIME_SELECT_HELP#',
			'value' => $unloadDateTimeSelectHtml,
		],
		'_UNLOAD_TIMEZONE' => [
			'type'          => 'select',
			'title'         => '#.UNLOAD_TIMEZONE#',
			'help'          => '#.UNLOAD_TIMEZONE_HELP#',
			'options'       => \CTimeZone::GetZones(),
			'default_value' => date_default_timezone_get(),
		],
		'UNLOAD_SORTING_HEADING' => 'heading:#.UNLOAD_SORTING_HEADER#',
		'_UNLOAD_SORT_FIELD'     => [
			'type'          => 'select',
			'title'         => '#.UNLOAD_SORT_FIELD#',
			'help'          => '#.UNLOAD_SORT_FIELD_HELP#',
			'options'       => IBlockHelpers::allPropsFor($iblock_id, IBlockHelpers::ANY_TYPE),
			'default_value' => 'ID',
		],
		'_UNLOAD_SORT_ORDER' => [
			'type'    => 'select',
			'title'   => '#.UNLOAD_SORT_ORDER#',
			'help'    => '#.UNLOAD_SORT_ORDER_HELP#',
			'options' => [
				'ASC'  => '#.UNLOAD_SORT_ASC#',
				'DESC' => '#.UNLOAD_SORT_DESC#',
				'RAND' => '#.UNLOAD_SORT_RAND#',
			],
			'default_value' => 'DESC',
		],
	];
}

$tabs = [
	[
		'name'   => '#.TEMPLATE_GENERAL#',
		'title'  => '#.TEMPLATE_GENERAL_SETTINGS#',
		'params' => $params,
	],
];
if ($iblock_id) {
	$tabs[] = [
		'name'   => '#.TEMPLATE_DATA#',
		'title'  => '#.TEMPLATE_DATA_SETTINGS#',
		'params' => $templateDataParams,
	];
	$tabs[] = [
		'name'   => '#.TEMPLATE_UNLOAD#',
		'title'  => '#.TEMPLATE_UNLOAD_SETTINGS#',
		'params' => $unloadParams,
	];
}

(new \vettich\sp3\devform\AdminForm('devform', [
	'pageTitle' => ($issetID ? '#.EDIT_TEMPLATE#' : '#.ADD_TEMPLATE#'),
	'tabs'      => $tabs,
	'buttons'   => [
		'_save'  => 'buttons\saveSubmit:#VDF_SAVE#',
		'_apply' => 'buttons\submit:#VDF_APPLY#',
	],
	'data'          => $data,
	'idKey'         => '_ID',
	'on beforeSave' => function ($arValues, $args, $obj) {
		$errs = [];
		if (empty($arValues['_NAME']) && !empty($arValues['_IBLOCK_ID'])) {
			$errs[] = Module::m('ERR_NAME_EMPTY');
		}
		if (empty($arValues['_IBLOCK_ID'])) {
			$errs[] = Module::m('ERR_IBLOCK_ID_EMPTY');
		}
		if (empty($arValues['_ACCOUNTS'])) {
			$errs[] = Module::m('ERR_ACCOUNTS_EMPTY');
		}
		if (!empty($errs)) {
			return ['error' => $errs];
		}
	},
]))->render();

if ($_GET['ajax'] != 'Y') {
	?>
	<div class="adm-info-message" style="display:block">
		<pre style="white-space: pre-wrap;"><?=Module::m('MACROS_HELP')?></pre>
	</div>
	<?php
}
require(__DIR__.'/../include/epilog_authorized_page.php');
