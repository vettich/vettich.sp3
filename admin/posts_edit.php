<?php
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Module;

$issetID = !empty($_GET['id']);

$res = vettich\sp3\db\Accounts::getList();
$accountsMap = [];
foreach ($res as $account) {
	$accountsMap[$account['id']] = $account['name'];
}
$accountsMap = Module::convertToSiteCharset($accountsMap);

$data = new \vettich\sp3\db\Posts(['prefix' => '_']);

$tabGeneralParams = [
	'h1' => 'heading:#.POST_HEADER_MAIN#',
	'_id' => 'hidden',
	'_fields[text]' => 'textarea:#.POST_TEXT#:params=[rows=6]:help=#.POST_TEXT_HELP#',
	'_fields[link]' => 'text:#.POST_LINK#:help=#.POST_LINK_HELP#:params=[placeholder=http\://domain.com/page.html]',
	'_fields[tags]' => 'text:#.POST_TAGS#:help=#.POST_TAGS_HELP#:params=[placeholder=#.POST_TAGS_PLACEHOLDER#]',
];

if (!$issetID) {
	$tabGeneralParams['_fields[images]'] = 'image:#.POST_PICTURE#:maxCount=5:raw=true';
} else {
	$tabGeneralParams['_fields[images]'] = [
		'type' => 'html',
		'title' => '#.POST_PICTURE#',
		'on renderTemplate' => function (&$obj, $template, &$replaces) {
			$value = $obj->getValue($obj->data);
			$tpl = '<img src="{src}" width=40 height=40 /> ';
			$res = Module::api()->getFilesURL($value);
			$value = '';
			foreach ($res['urls'] as $url) {
				$value .= str_replace('{src}', $url, $tpl);
			}
			$replaces['{value}'] = $value;
		},
	];
}

$tabGeneralParams = array_merge($tabGeneralParams, [
	'_publish_at' => 'datetime:#.POST_PUBLISH_AT#:help=#.POST_PUBLISH_AT_HELP#',
	'h2' => 'heading:#.POST_HEADER_ACCOUNTS#',
	'_networks[accounts]' => [
		'type' => 'checkbox',
		'title' => '#.POST_ACCOUNTS#',
		'options' => $accountsMap,
		'help' => '#.POST_ACCOUNTS_HELP#',
		'multiple' => true,
	],
]);

(new \vettich\devform\AdminForm('devform', [
	'pageTitle' => !$issetID ? '#.POST_ADD_PAGE#' : '#.POST_EDIT_PAGE#',
	'tabs' => [
		[
			'name' => '#.POST#',
			'title' => '#.POST_TITLE#',
			'params' => $tabGeneralParams,
		],
		[
			'name' => '#.POST_VK#',
			'title' => '#.POST_VK_TITLE#',
			'params' => [
				'_vk_fields[from_group]' => 'checkbox:#.POST_VK_FROM_GROUP#:Y:native=true:help=#.POST_VK_FROM_GROUP_HELP#',
				'_vk_fields[signed]' => 'checkbox:#.POST_VK_SIGNED#:native=true:help=#.POST_VK_SIGNED_HELP#',
			],
		],
	],
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
