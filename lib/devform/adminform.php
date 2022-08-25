<?php
namespace vettich\sp3\devform;

use vettich\sp3\devform\types\_type;
use vettich\sp3\devform\data\_data;
use CAdminContextMenu;

/**
* @author Oleg Lenshin (Vettich)
*
* 'on beforeSave' callback
* @param array $arValue
* @param array $args
* @param object this
* @return boolean
*
* 'on afterSave' callback
* @param array $arValue
* @param array $args
* @param object this
* @return boolean
*/
class AdminForm extends Module
{
	public $id = 'ID'; // unique
	public $pageTitle = false;
	public $tabs = null;
	public $buttons = null;
	public $headerButtons = null;
	public $datas = null;
	public $idKey = 'ID';
	public $getID = 'ID';
	public $containerTemplate = '<div class="js-vform" style="display:none">
			<form method="post" action="" id="{form-id}" enctype="multipart/form-data">
				{content}
			</form>
		</div>';
	public $js = '';
	public $css = '';
	public $groupRightIsWrite = true;

	public $errorMessage = '';
	public $errorTemplate = '<div class="adm-info-message">{errors}</div>';

	public function __construct($id, $args = [])
	{
		parent::__construct($args);
		$this->id = $id;
		if (isset($args['idKey'])) {
			$this->idKey = $args['idKey'];
		}
		if (isset($args['getID'])) {
			$this->getID = $args['getID'];
		}
		if (isset($args['js'])) {
			$this->js = $args['js'];
		}
		if (isset($args['css'])) {
			$this->css = $args['css'];
		}
		if (isset($args['errorMessage'])) {
			$this->errorMessage = $args['errorMessage'];
		}
		if (isset($args['pageTitle'])) {
			$this->pageTitle = self::mess($args['pageTitle']);
		}
		if (isset($args['tabs'])) {
			$this->tabs = $this->initTabs($args['tabs']);
		}
		if (isset($args['buttons'])) {
			$this->buttons = _type::createTypes($args['buttons']);
		}
		if (isset($args['headerButtons'])) {
			$this->headerButtons = _type::createTypes($args['headerButtons']);
		}
		if (isset($args['data'])) {
			$this->datas = _data::createDatas($args['data']);
		}
		if (isset($args['groupRightIsWrite'])) {
			$this->groupRightIsWrite = $args['groupRightIsWrite'];
		}

		$this->onHandler('tabsCreate', $this, $this->tabs);
		$this->save($args);
	}

	public static function initTabs($tabs)
	{
		if (empty($tabs)) {
			return [];
		}

		$tabClass = 'vettich\sp3\devform\Tab';
		$result = [];
		foreach ((array)$tabs as $tab) {
			if ($res = $tabClass::createTab($tab)) {
				$result[] = $res;
			}
		}

		return $result;
	}

	public function save($args)
	{
		if (!empty($_POST)) {
			$_POST = self::convertEncodingToCurrent($_POST);
		}
		if ($_REQUEST['ajax'] == 'Y'
			&& (!isset($args['save_ajax']) or $args['save_ajax'] != true)) {
			return;
		}
		$isDontSave = (isset($args['dont_save']) && $args['dont_save'] == true);
		if ($isDontSave or empty($_POST) or empty($this->datas)) {
			return;
		}
		$arValues = [];
		foreach ((array)$this->tabs as $tab) {
			$arValues = array_merge($arValues, _type::getValuesFromPost($tab->params));
		}
		/**
		* on beforeSave callback
		*/
		$beforeSave = $this->onHandler('beforeSave', $arValues, $args, $this);
		if ($beforeSave === false or isset($beforeSave['error'])) {
			if (isset($beforeSave['error'])) {
				$this->errorMessage = $beforeSave['error'];
			}
			return;
		}
		$res = $this->datas->saveValues($arValues);
		if ($res === false or isset($res['error'])) {
			if (isset($res['error'])) {
				$this->errorMessage = $res['error'];
			}
			return;
		}
		/**
		* on afterSave callback
		*/
		$this->onHandler('afterSave', $arValues, $args, $this);
		if ((isset($_POST['save']) or isset($_POST['_save'])) && !empty($_GET['back_url'])) {
			LocalRedirect($_GET['back_url']);
			exit;
		} elseif (empty($_GET[$this->getID]) && !empty($arValues[$this->idKey])) {
			$urlParams = [
				$this->getID => $arValues[$this->idKey],
				'TAB_CONTROL_devform_active_tab' => $_POST['TAB_CONTROL_devform_active_tab'],
			];
			$url = $_SERVER['REQUEST_URI'];
			$url = Module::setUrlParams($url, $urlParams, ['FROM_'.$this->getID]);
			LocalRedirect($url);
			exit;
		} else {
			LocalRedirect($_SERVER['REQUEST_URI']);
			exit;
		}
	}

	public function getContextMenu()
	{
		$arResult = [];

		if (isset($_GET['back_url'])) {
			$arResult['back'] = [
				'TEXT'  => GetMessage('VDF_BACK_LIST'),
				'TITLE' => GetMessage('VDF_BACK_LIST_TITLE'),
				'LINK'  => $_GET['back_url'],
				'ICON'  => 'btn_list',
			];
		}
		if (isset($_GET[$this->getID]) && $_GET[$this->getID] > 0 && $this->groupRightIsWrite) {
			$get = $_GET;
			unset($get[$this->getID]);
			$arResult['add'] = [
				'TEXT'  => GetMessage('VDF_ADD'),
				'TITLE' => GetMessage('VDF_ADD_TITLE'),
				'LINK'  => $_SERVER['SCRIPT_NAME'].'?'.http_build_query($get),
				'ICON'  => 'btn_new',
			];
			if (isset($_GET['back_url'])) {
				unset($get['ID']);
				unset($get['action']);
				unset($get['action_button']);
				/* unset($get['sessid']); */
				$get = [
					$this->getID    => $_GET[$this->getID],
					'ID'            => $_GET[$this->getID],
					'action'        => 'delete',
					'action_button' => 'delete',
					/* 'sessid'        => bitrix_sessid(), */
				];
				$url = $_GET['back_url'];
				$url .= (strpos($url, '?') ? '&' : '?').http_build_query($get);
				$arResult['delete'] = [
					'TEXT' => GetMessage('VDF_LIST_DELETE'),
					'TITLE' => GetMessage('VDF_LIST_DELETE_TITLE'),
					'LINK' => 'javascript:if(confirm("'
						.GetMessage('VDF_LIST_DELETE_CONFIRM2')
						.'")) window.location="'.$url.'";',
					'ICON' => 'btn_delete',
				];
			}
		}
		if (is_array($this->headerButtons)) {
			foreach ((array)$this->headerButtons as $id=>$button) {
				$arResult[$id] = [
					'HTML' => $button->render(),
				];
			}
		}
		return $arResult;
	}

	public function renderErrors($errors)
	{
		if (!empty($errors)) {
			if (!is_array($errors)) {
				$errors = [$errors];
			}
			$errors = '<ul style="margin:0"><li class="errortext">'
				.implode('</li><li class="errortext">', $errors)
				.'</li></ul>';
			echo(str_replace(
				['{errors}'],
				[$errors],
				$this->errorTemplate
			));
		}
	}

	public static function initRequires()
	{
		\CJSCore::Init(['ajax']);
		\CJSCore::Init(['jquery']);
		$GLOBALS['APPLICATION']->AddHeadScript('/bitrix/js/vettich.sp3/devform.js');
		$GLOBALS['APPLICATION']->SetAdditionalCSS('/bitrix/css/vettich.sp3/style.css');
	}

	public function getTabs()
	{
		$arTabs = [];
		foreach ((array)$this->tabs as $k => $tab) {
			$arTabs[] = [
				'DIV' => 'DIV_'.$k,
				'TAB' => $tab->name,
				'TITLE' => $tab->title,
			];
		}
		return $arTabs;
	}

	/**
	* возвращает дополнительные JS код и CSS стили
	* @return string js and css
	*/
	public function getJsCss()
	{
		$result = '';
		if (!empty($this->js)) {
			$result .= '<script>'.$this->js.'</script>';
		}
		if (!empty($this->css)) {
			$result .= '<style>'.$this->css.'</style>';
		}
		return $result;
	}

	public function getContent()
	{
		ob_start();
		$tabControl = new \CAdminTabControl('TAB_CONTROL_'.$this->id, $this->getTabs(), true, true);
		$tabControl->Begin();

		foreach ((array)$this->tabs as $tab) {
			$tabControl->BeginNextTab();
			$tab->render($this->datas);
		}
		if (!empty($this->buttons)) {
			$tabControl->Buttons();
			echo _type::renderTypes($this->buttons);
		}

		echo bitrix_sessid_post();
		$tabControl->End();
		echo $this->getJsCss();

		$ob_content = ob_get_contents();
		ob_end_clean();

		return $ob_content;
	}

	public function renderContextMenu()
	{
		$context = new CAdminContextMenu($this->getContextMenu());
		$context->Show();
	}

	public function restartBufferIfAjax()
	{
		if ($_REQUEST['ajax'] == 'Y' && $_REQUEST['ajax_formid'] == $this->id) {
			$GLOBALS['APPLICATION']->RestartBuffer();
		}
	}

	public static function setTitle($title)
	{
		if (!!$title) {
			$GLOBALS['APPLICATION']->SetTitle($title);
		}
	}

	public function renderTemplate()
	{
		echo str_replace(
			['{form-id}',        '{content}'],
			['FORM_'.$this->id,  $this->getContent()],
			$this->containerTemplate
		);
	}

	public function render()
	{
		self::initRequires();
		$this->renderContextMenu();
		$this->restartBufferIfAjax();
		$this->renderErrors($this->errorMessage);
		self::setTitle($this->pageTitle);
		$this->renderTemplate();
	}
}
