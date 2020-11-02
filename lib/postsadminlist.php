<?php
namespace vettich\sp3;

use Bitrix\Main\DB\ArrayResult;
use CAdminResult;

class PostsAdminList extends devform\AdminList
{
	private $nav;

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
		$this->renderEnd();
	}
}
