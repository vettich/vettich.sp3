<?php
namespace vettich\sp3;

class PostsAdminList extends devform\AdminList
{
	private const LQ_TOGGLE_ID = 'vettich-sp3-posts-lq-toggle';
	private const LQ_DETAIL_ID = 'vettich-sp3-posts-lq-detail';

	/**
	 * @var array<int, array{NAME: string, IBLOCK_ID: int, IBLOCK_TYPE_ID: string}>|null
	 */
	private static $iblockElementPrefetch = null;

	private $nav;

	/**
	 * Данные элементов ИБ для колонки «элемент ИБ» (заполняется на время render списка постов).
	 *
	 * @return array<int, array{NAME: string, IBLOCK_ID: int, IBLOCK_TYPE_ID: string}>
	 */
	public static function getIblockElementPrefetch(): array
	{
		return self::$iblockElementPrefetch ?? [];
	}

	public function __construct($pageTitle, $sTableID, $args)
	{
		parent::__construct($pageTitle, $sTableID, $args);

		$this->nav = new \Bitrix\Main\UI\AdminPageNavigation('posts');
	}

	public function getDataSource($arOrder=[], $arFilter=[], $arSelect=[])
	{
		$params = [
			'paging' => [
				'page' => $this->nav->getCurrentPage(),
				'size' => $this->nav->getPageSize(),
			]
		];
		if (!empty($arOrder)) {
			$params['order'] = $arOrder;
		}
		if (!empty($arFilter)) {
			$params['filter'] = $arFilter;
		}
		if (!empty($arSelect)) {
			$params['select'] = $arSelect;
		}
		if (!in_array($this->idKey, $params['select'])) {
			$params['select'][] = $this->idKey;
		}
		if (!empty($this->datas->datas)) {
			foreach ((array)$this->datas->datas as $data) {
				if (method_exists($data, 'getList')) {
					return $data->getList($params, false);
				}
			}
		}
		return null;
	}

	protected function renderNav($dataSource)
	{
		global $APPLICATION;

		ob_start();

		$this->nav->setRecordCount($dataSource['paging']['total']);

		$params = [
			'NAV_TITLE'=> 'nav title',
			// 'NAV_RESULT' => $this,
			// 'SHOW_ALWAYS' => $showAlways
			'NAV_OBJECT' => $this->nav,
			'TABLE_ID'   => $this->sTableID,
			// 'SEF_MODE'   => 'Y',
		];

		$navComponentObject = $APPLICATION->IncludeComponent(
			'bitrix:main.pagenavigation',
			'admin',
			$params,
			false
		);

		$result = ob_get_contents();
		ob_end_clean();

		return $result;
	}

	/**
	* show page on display
	* @global $APPLICATION
	*/
	public function render()
	{
		$this->renderBegin();
		$select     = $this->getSelectedFields();
		$dataSource = $this->getDataSource($this->getOrder(), $this->getFilter(), $select);
		$posts      = $dataSource['posts'] ?? [];
		self::$iblockElementPrefetch = $this->buildIblockElementPrefetchMap(is_array($posts) ? $posts : []);
		try {
			$this->renderPostsListBody($dataSource, $select);
		} finally {
			self::$iblockElementPrefetch = null;
		}
		$this->renderEnd();
	}

	/**
	 * @param array<int, array<string, mixed>> $posts
	 * @return array<int, array{NAME: string, IBLOCK_ID: int, IBLOCK_TYPE_ID: string}>
	 */
	protected function buildIblockElementPrefetchMap(array $posts): array
	{
		$elementIds = [];
		foreach ($posts as $p) {
			$eid = isset($p['fields']['extra']['id']) ? (int) $p['fields']['extra']['id'] : 0;
			if ($eid > 0) {
				$elementIds[$eid] = $eid;
			}
		}
		if (empty($elementIds)) {
			return [];
		}
		$map = [];
		$rs  = \CIBlockElement::GetList(
			[],
			['ID' => array_values($elementIds)],
			false,
			false,
			['ID', 'NAME', 'IBLOCK_ID']
		);
		while ($row = $rs->GetNext(false, false)) {
			$eid = (int) $row['ID'];
			$map[$eid] = [
				'NAME'            => (string) $row['NAME'],
				'IBLOCK_ID'       => (int) $row['IBLOCK_ID'],
				'IBLOCK_TYPE_ID'  => '',
			];
		}
		$iblockIds = [];
		foreach ($map as $info) {
			$bid = $info['IBLOCK_ID'];
			if ($bid > 0) {
				$iblockIds[$bid] = $bid;
			}
		}
		if (empty($iblockIds)) {
			return $map;
		}
		$typeByIblock = [];
		$rsb = \CIBlock::GetList([], ['ID' => array_values($iblockIds)], false);
		while ($b = $rsb->GetNext(false, false)) {
			$typeByIblock[(int) $b['ID']] = (string) $b['IBLOCK_TYPE_ID'];
		}
		foreach ($map as $eid => &$info) {
			$info['IBLOCK_TYPE_ID'] = $typeByIblock[$info['IBLOCK_ID']] ?? '';
		}
		unset($info);

		return $map;
	}

	/**
	 * @param array<string, mixed> $dataSource
	 * @param array<int|string, mixed> $select
	 */
	protected function renderPostsListBody($dataSource, $select): void
	{
		$this->list->NavText($this->renderNav($dataSource));
		// while ($arRes = $data->NavNext(false)) {
		foreach ($dataSource['posts'] as $arRes) {
			$row = $this->list->AddRow($arRes[$this->idKey], $arRes);
			$this->onHandler('renderRow', $this, $row);
			foreach ((array)$select as $fieldId) {
				$param = $this->params[$fieldId];
				if (!$param) {
					continue;
				}
				if (in_array($param->id, $this->linkEditInsert)) {
					$param->href = $this->getLinkEdit([$this->idKey => $arRes[$this->idKey]]);
				}
				$view = $param->renderView(self::arrayChain($arRes, self::strToChain($param->id)), $arRes);
				$row->AddViewField($param->id, $view);

				if ($this->dontEditAll || in_array($param->id, $this->dontEdit)) {
					continue;
				}
				if (($pos = strpos($param->id, '[')) !== false) {
					$prekey  = substr($param->id, 0, $pos);
					$postkey = substr($param->id, $pos);
					$name    = "FIELDS[{$arRes[$this->idKey]}][$prekey]$postkey";
				} else {
					$name = "FIELDS[{$arRes[$this->idKey]}][{$param->id}]";
				}
				$edit = $param->renderTemplate('{content}', [
					'{id}'    => 'FIELDS-'.$arRes[$this->idKey].'-'.str_replace(['][', ']', '['], ['-', '', '-'], $param->id),
					'{value}' => self::arrayChain($arRes, self::strToChain($param->id)),
					'{name}'  => $name,
				]);
				$row->AddEditField($param->id, $edit);
			}
			$arActions = $this->getActions($row);
			$row->AddActions($arActions);
			$this->onHandler('afterRow', $this, $row);
		}
	}

	protected function renderBeforeList()
	{
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
						id="<?= self::LQ_TOGGLE_ID ?>"
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
				<div id="<?= self::LQ_DETAIL_ID ?>" class="vettich-sp3-local-queue-detail" style="display:none;margin-top:10px;line-height:1.45;max-width:52rem">
					<?= $detailHtml ?>
				</div>
			</div>
		</div>
		<script>
		BX.ready(function () {
			var t = BX('<?= \CUtil::JSEscape(self::LQ_TOGGLE_ID) ?>');
			var d = BX('<?= \CUtil::JSEscape(self::LQ_DETAIL_ID) ?>');
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
		<?php
	}
}
