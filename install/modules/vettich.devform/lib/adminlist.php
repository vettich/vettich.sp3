<?php
namespace vettich\devform;

use CAdminSorting;
use CAdminList;
use CAdminResult;
use CAdminFilter;
use vettich\devform\types\_type;
use vettich\devform\data\_data;
use vettich\devform\data\orm;

/**
* show elements list on admin page
*
* @author Oleg Lenshin (Vettich)
* @var string $pageTitle
* @var string $sTableID
* @var string $navLabel
* @var CAdminSorting $sort
* @var CAdminList $list
* @var _data $datas
* @var array $params types
* @var array $hiddenParams
* @var array $dontEdit
* @var array $onHandlers
*/
class AdminList extends Module
{
	protected $pageTitle = '';
	protected $sTableID = '';
	protected $navLabel = '';
	protected $sort = null;
	protected $list = null;
	protected $datas = null;
	protected $params = [];
	protected $hiddenParams = [];
	protected $dontEdit = ['ID', 'id'];
	protected $dontEditAll = false;
	protected $linkEditInsert = [];
	protected $editLinkParams = [];
	protected $editLink = '';
	protected $hideFilters = false;
	protected $idKey = 'ID';
	protected $actions = ['edit', 'delete'];

	/**
	 * @param string $pageTitle
	 * @param string $sTableID
	 * @param boolean|array[] $arSort
	 * @param string $navLabel
	 */
	public function __construct($pageTitle, $sTableID, $args)
	{
		parent::__construct($args);
		$this->pageTitle = self::mess($pageTitle);
		$this->sTableID = $sTableID;
		$this->params = _type::createTypes($args['params']);

		$this->setSort($args);

		if (isset($args['data'])) {
			$this->datas = _data::createDatas($args['data']);
		} elseif (isset($args['dbClass'])) {
			$this->datas = _data::createDatas(new orm(['dbClass' => $args['dbClass'], 'filter' => []]));
		}

		if (isset($args['idKey'])) {
			$this->idKey = $args['idKey'];
		}
		if (isset($args['navLabel'])) {
			$this->navLabel = $args['navLabel'];
		}
		if (isset($args['linkEditInsert'])) {
			$this->linkEditInsert = $args['linkEditInsert'];
		}
		if (isset($args['hiddenParams'])) {
			$this->hiddenParams = $args['hiddenParams'];
		}
		if (isset($args['hideFilters'])) {
			$this->hideFilters = $args['hideFilters'];
		}
		if (isset($args['dontEdit'])) {
			$this->dontEdit = $args['dontEdit'];
		}
		if (isset($args['dontEditAll'])) {
			$this->dontEditAll = $args['dontEditAll'];
		}
		if (isset($args['editLink'])) {
			$this->editLink = $args['editLink'];
		}
		if (isset($args['editLinkParams'])) {
			$this->editLinkParams = $args['editLinkParams'];
		}
		if (isset($args['actions'])) {
			$this->actions = $args['actions'];
		}

		$this->list = new CAdminList($this->sTableID, $this->sort);

		if (!isset($args['buttons']['add'])) {
			$args['buttons'] = array_merge([
				'add' => 'buttons\newLink:#VDF_ADD#:'.str_replace(['=', '[', ']'], ['\=', '\[', '\]'], $this->getLinkEdit()),
			], (array)$args['buttons']);
		}
		$this->buttons = _type::createTypes($args['buttons']);

		if (!isset($args['isFilter']) or $args['isFilter']) {
			$filters = [
				'find',
				'find_type',
			];
			$this->list->InitFilter($filters);
		}

		$this->doGroupActions();
		$this->doEditAction();
	}

	public function getLinkEdit($params=[])
	{
		$params = array_merge($this->editLinkParams, $params);
		$p = $_GET;
		unset($p['mode']);
		$p = http_build_query($p);
		$params['back_url'] = $_SERVER['SCRIPT_NAME'].(empty($p) ? '' : '?'.$p);
		$link = $this->editLink;
		if (empty($link)) {
			$search = '.php';
			if (strpos($_SERVER['SCRIPT_NAME'], '_list.php') !== false) {
				$search = '_list.php';
			}
			$link = str_replace($search, '_edit.php', $_SERVER['SCRIPT_NAME']);
		}
		return $link.'?'.http_build_query($params);
	}

	public function isHiddenParam($id)
	{
		return in_array($id, $this->hiddenParams);
	}

	public function doGroupActions()
	{
		if (($arID = $this->list->GroupAction())) {
			if ($_REQUEST['action_target']=='selected') {
				$arID = [];
				$rs = $this->getDataSource([], [], [$this->idKey]);
				while ($ar = $rs->fetch()) {
					$arID[] = $ar[$this->idKey];
				}
			}
			foreach ((array)$arID as $ID) {
				if (empty($ID)) {
					continue;
				}
				$action = $_REQUEST['action'];
				if (empty($action)) {
					$action = $_REQUEST['action_button'];
				}
				switch ($action) {
					case 'delete':
						if (false !== $this->onHandler('beforeGroupDelete', $this, $ID)) {
							$this->datas->delete($this->idKey, $ID);
							$this->onHandler('afterGroupDelete', $this, $ID);
						}
						break;
				}
			}
			$this->onHandler('doGroupActions', $arID, $_REQUEST['action'], $this);
		}
	}

	public function doEditAction()
	{
		if ($this->list->EditAction()) {
			foreach ((array)$_REQUEST['FIELDS'] as $id => $arField) {
				$arField[$this->idKey] = $id;
				$this->datas->saveValues($arField);
			}
		}
	}

	public function setSort($args)
	{
		if (isset($args['isSort']) && !$args['isSort']) {
			$this->sort = false;
		} else {
			if (isset($args['sortDefault'])) {
				$sBy = key($args['sortDefault']);
				$sOrder = current($args['sortDefault']);
				if (!$sBy) {
					$sBy = $sOrder;
					$sOrder = 'ASC';
				}
			} else {
				$sBy = $this->idKey;
				$sOrder = 'ASC';
			}
			$this->sortBy = $sBy;
			$this->sortOrder = $sOrder;
			$this->sort = new CAdminSorting($this->sTableID, $sBy, $sOrder);
		}
	}

	public function getHeaders()
	{
		$arHeaders = [];
		foreach ((array)$this->params as $id => $param) {
			$sort = $param->sortKey;
			if (empty($sort)) {
				$sort = (strpos($id, '[') === false ? $param->id : false);
			}
			$arHeaders[] = [
				'id' => $param->id,
				'content' => $param->title,
				'sort' => $sort,
				// 'align' => $param->info['align'],
				'default' => !$this->isHiddenParam($param->id),
			];
		}
		return $arHeaders;
	}

	public function getSelectedFields()
	{
		$arSelectedFields = $this->list->GetVisibleHeaderColumns();
		if (!is_array($arSelectedFields) || empty($arSelectedFields)) {
			$arSelectedFields = [];
			foreach ((array)$this->params as $id => $param) {
				if ($this->isHiddenParam($id)) {
					$arSelectedFields[] = $id;
				}
			}
		}
		return $arSelectedFields;
	}

	public function getDataSource($arOrder=[], $arFilter=[], $arSelect=[])
	{
		$params = [];
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
					return $data->getList($params);
				}
			}
		}
		return null;
	}

	public function getOrder()
	{
		global $by, $order;
		return [$by => $order];
	}

	public function getFilter()
	{
		global $find, $find_type;

		$arFilter = [];
		foreach ((array)$this->params as $param) {
			$find_name = 'find_'.$param->id;
			if (!empty($find) && $find_type == $find_name) {
				$arFilter[$param->getFilterId()] = $find;
			} elseif (isset($GLOBALS[$find_name])) {
				$arFilter[$param->getFilterId()] = $GLOBALS[$find_name];
			}
		}

		foreach ((array)$arFilter as $key => $value) {
			if ($value == "") {
				unset($arFilter[$key]);
			}
		}
		return $arFilter;
	}

	public function getActions($row)
	{
		$arActions = [];
		foreach ($this->actions as $act) {
			if ($act == 'edit') {
				$arActions['edit'] = [
					'ICON' => 'edit',
					'DEFAULT' => true,
					'TEXT' => GetMessage('VDF_LIST_EDIT'),
					'ACTION' => $this->list->ActionRedirect($this->getLinkEdit([$this->idKey => $row->arRes[$this->idKey]])),
				];
			} elseif ($act == 'copy') {
				$arActions['copy'] = [
					'ICON' => 'copy',
					'TEXT' => GetMessage('VDF_LIST_COPY'),
					'ACTION' => $this->list->ActionRedirect($this->getLinkEdit(['FROM_'.$this->idKey => $row->arRes[$this->idKey]])),
				];
			} elseif ($act == 'delete') {
				$arActions['delete'] = [
					'ICON' => 'delete',
					'TEXT' => GetMessage('VDF_LIST_DELETE'),
					'ACTION' => 'if(confirm("'
						.(GetMessage('VDF_LIST_DELETE_CONFIRM', ['#NAME#' => $row->arRes['NAME']])).'")) '
						.$this->list->ActionDoGroup($row->arRes[$this->idKey], 'delete'),
				];
			}
		}
		$arActionsBuild = $this->onHandler('actionsBuild', $this, $row, $arActions);
		if ($arActionsBuild != null) {
			$arActions = $arActionsBuild;
		}
		return $arActions;
	}

	public function getFooter()
	{
		return [];
	}

	public function getContextMenu()
	{
		$arResult = [];
		foreach ((array)$this->buttons as $button) {
			$arResult[] = [
				'HTML' => $button->render(),
			];
		}
		return $arResult;
	}

	public function displayFilter()
	{
		if ($this->hideFilters) {
			return;
		}
		global $APPLICATION, $find, $find_type;

		$findFilter = [
			'reference' => [],
			'reference_id' => [],
		];
		$listFilter = [];
		$filterRows = [];
		foreach ((array)$this->params as $param) {
			$listFilter[$param->id] = $param->title;
			$findFilter['reference'][] = $param->title;
			$findFilter['reference_id'][] = 'find_'.$param->id;
		}

		if (!empty($listFilter)) {
			$filter = new CAdminFilter($this->sTableID.'_filter', $listFilter); ?>
			<form name="find_form" method="get" action="<?php echo $APPLICATION->GetCurPage(); ?>">
				<?php $filter->Begin(); ?>
				<?php if (!empty($findFilter['reference'])): ?>
					<tr>
						<td><b><?=GetMessage('PERFMON_HIT_FIND')?>:</b></td>
						<td><input
							type="text" size="25" name="find"
							value="<?php echo htmlspecialcharsbx($find) ?>"><?php echo SelectBoxFromArray('find_type', $findFilter, $find_type, '', ''); ?>
						</td>
					</tr>
				<?php endif; ?>
				<?php
				foreach ((array)$this->params as $param) {
					?><tr>
						<td><?php echo $param->title ?></td>
						<td><?php echo $param->renderTemplate('{content}', ['{name}' => 'find_'.$param->id]) ?></td>
					</tr><?php
				}
			$filter->Buttons([
					'table_id' => $this->sTableID,
					'url' => $APPLICATION->GetCurPage(),
					'form' => 'find_form',
				]);
			$filter->End(); ?>
			</form>
		<?php
		}
	}

	/**
	* show page on display
	* @global $APPLICATION
	*/
	public function render()
	{
		$this->renderBegin();
		$select = $this->getSelectedFields();
		$dataSource = $this->getDataSource($this->getOrder(), $this->getFilter(), $select);
		$data = new CAdminResult($dataSource, $this->sTableID);
		$data->NavStart();
		$this->list->NavText($data->GetNavPrint($this->navLabel));
		while ($arRes = $data->NavNext(false)) {
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
					$prekey = substr($param->id, 0, $pos);
					$postkey = substr($param->id, $pos);
					$name = "FIELDS[{$arRes[$this->idKey]}][$prekey]$postkey";
				} else {
					$name = "FIELDS[{$arRes[$this->idKey]}][{$param->id}]";
				}
				$edit = $param->renderTemplate('{content}', [
					'{id}' => 'FIELDS-'.$arRes[$this->idKey].'-'.str_replace(['][', ']', '['], ['-', '', '-'], $param->id),
					'{value}' => self::arrayChain($arRes, self::strToChain($param->id)),
					'{name}' => $name,
				]);
				$row->AddEditField($param->id, $edit);
			}
			$arActions = $this->getActions($row);
			$row->AddActions($arActions);
			$this->onHandler('afterRow', $this, $row);
		}
		$this->renderEnd();
	}

	private function renderBegin()
	{
		\CJSCore::Init(['ajax']);
		\CJSCore::Init(['jquery']);
		$GLOBALS['APPLICATION']->AddHeadScript('/bitrix/js/vettich.devform/script.js');
		$GLOBALS['APPLICATION']->SetAdditionalCSS('/bitrix/css/vettich.devform/style.css');
		$this->list->addHeaders($this->getHeaders());
	}

	private function renderEnd()
	{
		$this->list->AddFooter($this->getFooter());
		$this->list->AddAdminContextMenu($this->getContextMenu());
		$this->list->AddGroupActionTable(['delete'=>true]);
		$this->list->CheckListMode();
		if (!!$this->pageTitle) {
			$GLOBALS['APPLICATION']->SetTitle($this->pageTitle);
		}
		global $adminPage, $adminMenu, $adminChain, $USER, $APPLICATION;
		require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');
		$this->displayFilter();
		$this->list->DisplayList();
	}
}
