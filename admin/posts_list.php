<?php
$prolog_admin_after = false;
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Module;
use vettich\sp3\Api;
use vettich\sp3\LocalQueue;
use vettich\sp3\View;

const LQ_TOGGLE_ID = 'vettich-sp3-posts-lq-toggle';
const LQ_DETAIL_ID = 'vettich-sp3-posts-lq-detail';

function showLocalQueueIfExist() {
	$cnt = LocalQueue::getPendingCount();
	if ($cnt <= 0) {
		return;
	}

	$line = Module::m('LOCAL_QUEUE_LINE', ['#COUNT#' => (string) $cnt]);

	$helpShort = Module::m('LOCAL_QUEUE_HELP_SHORT');
	$detail    = Module::m('LOCAL_QUEUE_HELP_DETAIL');
	$toggleHint = Module::m('LOCAL_QUEUE_TOGGLE_HINT');

	$helpShortEsc = htmlspecialcharsbx($helpShort);
	$detailHtml   = $detail;
	$lineEsc      = htmlspecialcharsbx($line);
	$toggleHintEsc = htmlspecialcharsbx($toggleHint);

	?>
	<div class="adm-info-message-wrap" style="margin:0 0 14px; overflow: unset">
		<div class="adm-info-message vettich-sp3-local-queue-notice" style="display:block">
			<span class="vettich-sp3-local-queue-line" style="display:inline-flex;align-items:center;flex-wrap:wrap;gap:6px">
				<span
					id="<?= LQ_TOGGLE_ID ?>"
					class="vettich-sp3-local-queue-toggle"
					role="button"
					tabindex="0"
					style="cursor:pointer;border-bottom:1px dotted rgba(0,0,0,.35);font-weight:600"
					title="<?= $toggleHintEsc ?>"
				><?= $lineEsc ?></span>
				<span class="voptions-help vettich-sp3-local-queue-help" style="vertical-align:middle">
					<span class="voptions-help-btn" title="<?= $toggleHintEsc ?>"></span>
					<span class="voptions-help-text"><?= $helpShortEsc ?></span>
				</span>
			</span>
			<div id="<?= LQ_DETAIL_ID ?>" class="vettich-sp3-local-queue-detail" style="display:none;margin-top:10px;line-height:1.45;max-width:52rem">
				<?= $detailHtml ?>
			</div>
		</div>
	</div>
	<script>
	BX.ready(function () {
		var t = BX('<?= \CUtil::JSEscape(LQ_TOGGLE_ID) ?>');
		var d = BX('<?= \CUtil::JSEscape(LQ_DETAIL_ID) ?>');
		if (!t || !d) {
			return;
		}
		function toggle() {
			d.style.display = (d.style.display === 'none' || d.style.display === '') ? 'block' : 'none';
		}
		BX.bind(t, 'click', function (e) { e.preventDefault(); toggle(); });
		BX.bind(t, 'keydown', function (e) {
			if (e.keyCode === 13 || e.keyCode === 32) {
				e.preventDefault();
				toggle();
			}
		});
	});
	</script>
	<?
}

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');
showLocalQueueIfExist();

if ($_GET['prev_version'] != 'Y') {
	$APPLICATION->SetTitle(Module::m('POSTS_LIST_PAGE'));

	View::embed_front('posts');

	require(__DIR__.'/../include/epilog_authorized_page.php');
	exit;
}

CModule::IncludeModule('iblock');

$params = [
	'data'        => new vettich\sp3\db\Posts(),
	'hideFilters' => true,
	'idKey'       => 'id',
	'params'      => [
		'id'           => 'plaintext:ID',
		'fields[text]' => [
			'type'          => 'plaintext',
			'title'         => '#.POST_TEXT#',
			'sortKey'       => 'fields.text',
			'defaultValue'  => '<empty>',
			'on renderView' => function (&$obj, &$value) {
				if (empty($value)) {
					return;
				}
				$ind = strpos($value, "\n");
				if ($ind !== false) {
					$value = substr($value, 0, $ind);
				}
			},
		],
		'fields[images]' => [
			'type'          => 'html',
			'title'         => '#.POST_PICTURE#',
			'on renderView' => function (&$obj, &$value, $arRow=[]) {
				$tpl = '<img src="{value}" width=40 height=40 /> ';
				$value = '';
				foreach ($arRow['fields']['image_urls'] as $url) {
					$value .= str_replace('{value}', $url, $tpl);
				}
			},
		],
		'fields[extra][id]' => [
			'type'          => 'html',
			'title'         => '#.IBLOCK_ELEM#',
			'on renderView' => function (&$obj, &$value, $arRow=[]) {
				if (empty($value)) {
					$value = '-';
					return;
				}
				$elemId = (int) $value;
				$pref   = \vettich\sp3\PostsAdminList::getIblockElementPrefetch();
				if (isset($pref[$elemId])) {
					$ar = $pref[$elemId];
				} else {
					$rs = \CIBlockElement::GetList([], ['ID' => $elemId], false, false, ['ID', 'NAME', 'IBLOCK_ID']);
					$ar = $rs->GetNext(false, false);
					if (is_array($ar)) {
						$bid = (int) $ar['IBLOCK_ID'];
						$ar  = [
							'NAME'           => (string) $ar['NAME'],
							'IBLOCK_ID'      => $bid,
							'IBLOCK_TYPE_ID' => $bid > 0 ? (string) \CIBlock::GetArrayByID($bid, 'IBLOCK_TYPE_ID') : '',
						];
					} else {
						$ar = null;
					}
				}
				if (!is_array($ar)) {
					return;
				}
				$iblockId   = (int) $ar['IBLOCK_ID'];
				$iblockType = (string) $ar['IBLOCK_TYPE_ID'];
				$nameEsc    = htmlspecialcharsbx($ar['NAME']);
				$href       = '/bitrix/admin/iblock_element_edit.php?type='.rawurlencode($iblockType)
					.'&IBLOCK_ID='.$iblockId.'&ID='.$elemId;
				$value = '['.$elemId.'] <a href="'.htmlspecialcharsbx($href).'">'.$nameEsc.'</a>';
			},
		],
		'publish_at' => 'datetime:#.POST_PUBLISH_AT#',
		'status'     => [
			'type'          => 'plaintext',
			'title'         => '#.POST_STATUS#',
			'on renderView' => function (&$obj, &$value) {
				$key = empty($value) ? 'SUCCESS' : strtoupper($value);
				$value = Module::m('POST_STATUS_'.$key);
			},
		],
		'networks[accounts]' => [
			'type'          => 'plaintext',
			'title'         => '#.SOCIALS#',
			'on renderView' => function (&$obj, &$value) {
				$res = [];
				$types = [];
				/* $accs = (new vettich\sp3\db\Accounts(['filter' => ['id' => $value]]))->getListType(); */
				$accs = vettich\sp3\db\Accounts::getByIds($value);
				foreach ($accs as $acc) {
					$types[$acc['type']] = true;
				}
				foreach ($types as $t => $i) {
					$res[] = Module::m(strtoupper($t));
				}
				$value = implode(', ', $res);
			},
		],
	],
	'actions' => Module::hasGroupWrite() ? ['edit', 'delete'] : [],
	'hiddenParams' => ['id', 'fields[images]'],
	'dontEditAll'  => true,
	'editLink'     => 'vettich.sp3.posts_edit.php',
	'sortDefault'  => ['publish_at' => 'desc'],
];

if (!Module::hasGroupWrite()) {
	$params['buttons'] = ['add' => ''];
}

(new \vettich\sp3\PostsAdminList('#.POSTS_LIST_PAGE#', 'sp3_posts', $params))->render();

require(__DIR__.'/../include/epilog_authorized_page.php');
