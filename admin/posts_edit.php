<?php
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Module;
use vettich\sp3\Api;

$issetID = !empty($_GET['id']);
if (!$issetID && $userTariffExpired) {
	$APPLICATION->SetTitle(Module::m('POSTS_ADD_TITLE')); ?>
	<div class="adm-info-message" style="display:block">
		<?=Module::m('POSTS_ADD_EXPIRED')?>
	</div><?php
	require(__DIR__.'/../include/epilog_authorized_page.php');
	exit;
}

$dataArgs = ['prefix' => '_'];
if (isset($_GET['FROM_id'])) {
	$dataArgs['filter'] = ['id' => $_GET['FROM_id']];
}
$data = new \vettich\sp3\db\Posts($dataArgs);

$tabGeneralParams = [];
if ($issetID) {
	$row = vettich\sp3\db\PostIBlockTable::getRow(['filter' => ['POST_ID' => $_GET['id']]]);
	if (!empty($row['IBLOCK_ID']) && !empty($row['ELEM_ID'])) {
		$iblockName = CIBlock::GetArrayByID($row['IBLOCK_ID'], 'NAME');
		$iblockIDValue = "[$row[IBLOCK_ID]] <a href=\"/bitrix/admin/iblock_edit.php?type=$iblockType&ID=$row[IBLOCK_ID]\">$iblockName</a>";
		$rs = CIBlockElement::GetList([], ['ID' => $row['ELEM_ID']], false, false, ['ID', 'NAME']);
		if ($ar = $rs->GetNext()) {
			$elemValue = "[$row[ELEM_ID]] <a href=\"/bitrix/admin/iblock_element_edit.php?type=$iblockType&IBLOCK_ID=$iblockID&ID=$row[ELEM_ID]\">$ar[NAME]</a>";
		}
		$tabGeneralParams = [
			'iblock' => [
				'type' => 'plaintext',
				'title' => '#.IBLOCK#',
				'value' => $iblockIDValue,
			],
			'iblock_elem' => [
				'type' => 'plaintext',
				'title' => '#.IBLOCK_ELEM#',
				'value' => $elemValue,
			],
		];
	}
}

$tabGeneralParams = array_merge($tabGeneralParams, [
	'h1' => 'heading:#.POST_HEADER_MAIN#',
	'_id' => 'hidden',
	'_fields[text]' => 'textarea:#.POST_TEXT#:params=[rows=6]:help=#.POST_TEXT_HELP#',
	'_fields[link]' => 'text:#.POST_LINK#:help=#.POST_LINK_HELP#:params=[placeholder=http\://domain.com/page.html]',
	/* '_fields[need_utm]' => 'checkbox:#.POST_UTM#:Y:help=#.POST_UTM_HELP#:native=true', */
	'_fields[tags]' => 'text:#.POST_TAGS#:help=#.POST_TAGS_HELP#:params=[placeholder=#.POST_TAGS_PLACEHOLDER#]',
]);

if (!$issetID) {
	$tabGeneralParams['_fields[images]'] = 'image:#.POST_PICTURE#:maxCount=10:raw=true';
} else {
	$tabGeneralParams['_fields[images]'] = [
		'type' => 'html',
		'title' => '#.POST_PICTURE#',
		'on renderTemplate' => function (&$obj, $template, &$replaces) {
			$value = $obj->getValue($obj->data);
			$tpl = '<img src="{src}" width=40 height=40 /> ';
			$res = Api::getFilesURL($value);
			$value = '';
			foreach ((array)$res['response']['urls'] as $url) {
				$value .= str_replace('{src}', $url, $tpl);
			}
			$replaces['{value}'] = $value;
			if (empty($res['response']['urls'])) {
				$replaces['{value}'] = Module::m('NO_IMAGES');
			}
		},
	];
}

$tabGeneralParams = array_merge($tabGeneralParams, [
	'_publish_at' => 'datetime:#.POST_PUBLISH_AT#:help=#.POST_PUBLISH_AT_HELP#',
	'h2' => 'heading:#.POST_HEADER_ACCOUNTS#',
]);
$accList = (new vettich\sp3\db\Accounts())->getListType();
foreach ($accList as $t => $accType) {
	$accountsMap = [];
	foreach ($accType as $account) {
		$accountsMap[$account['id']] = $account['name'];
	}
	$tabGeneralParams[] = new \vettich\devform\types\checkbox('_networks[accounts]', [
		'title' => Module::m(strtoupper($t)),
		'options' => $accountsMap,
		'multiple' => true,
	]);
}

$tabGeneralParams = array_merge($tabGeneralParams, [
	'vk_header' => 'heading:#.POST_VK_TITLE#',
	'_fields[extra][vk_from_group]' => 'checkbox:#.POST_VK_FROM_GROUP#:Y:native=true:help=#.POST_VK_FROM_GROUP_HELP#',
	'_fields[extra][vk_signed]' => 'checkbox:#.POST_VK_SIGNED#:native=true:help=#.POST_VK_SIGNED_HELP#',
]);

$tabs = [
	[
		'name' => '#.POST#',
		'title' => '#.POST_TITLE#',
		'params' => $tabGeneralParams,
	],
];
if ($issetID) {
	$results = [];
	$resData = $data->get('_results');
	foreach ($resData as $id => $ar) {
		$acc = vettich\sp3\db\Accounts::getById($id);
		$results[] = [
			'type' => 'plaintext',
			'title' => $acc['name'],
			'value' => $ar['success'] ? 'success' : 'fail',
		];
	}
	if (empty($results)) {
		$results[] = [
			'type' => 'plaintext',
			'title' => '',
			'value' => '#.POST_EMPTY_RESULTS#',
		];
	}
	$tabs[] = [
		'name' => '#.POST_RESULTS#',
		'title' => '#.POST_RESULTS_TITLE#',
		'params' => $results,
	];
}

(new \vettich\devform\AdminForm('devform', [
	'pageTitle' => !$issetID ? '#.POST_ADD_PAGE#' : '#.POST_EDIT_PAGE#',
	'tabs' => $tabs,
	'buttons' => [
		'_save' => 'buttons\saveSubmit:'.(!$issetID ? '#.POST_ADD_BTN#' : '#.POST_UPDATE_BTN#'),
	],
	'data' => $data,
]))->render();

if (!$issetID):
	?>
	<div class="adm-info-message" style="display:block">
		<pre style="white-space: pre-wrap;"><?=Module::m('POST_FROM_IBLOCK_HELP')?></pre>
	</div>
	<?php
endif;

require(__DIR__.'/../include/epilog_authorized_page.php');
