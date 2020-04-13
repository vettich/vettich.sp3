<?php
namespace vettich\sp3\devform\types;

use vettich\sp3\devform\actions\_action;
use vettich\sp3\devform\exceptions\TypeException;
use vettich\sp3\devform\data\_data;

/**
*
*/
abstract class _type extends \vettich\sp3\devform\Module
{
	public $id = '';
	public $filterMask = '=';
	public $title = '';
	public $titleOrigin = '';
	public $name = '';
	public $value = null;
	public $default_value = '';
	public $template = '<tr id="{id}-wrap"><td width="40%"><label for="{id}">{title}{help}</label></td><td width="60%">{content}</td></tr>';
	public $templateView = '{value}';
	public $content = '{value}';
	public $templateHelp = '
		<div class="voptions-help">
			<div class="voptions-help-btn"></div>
			<div class="voptions-help-text">{help_text}</div>
		</div>';
	public $help = '';
	public $params = [];
	public $sort = 500;
	public $sortKey = '';
	public $is_saved = true;
	public $actions = [];
	public $data = null;

	public function __construct($id, $args = [])
	{
		parent::__construct($args);
		$this->id = $id;

		if (!isset($args['title'])) {
			$args['title'] = $id;
		}
		$this->title = self::mess($args['title']);
		$this->titleOrigin = $args['title'];

		if (isset($args['name'])) {
			$this->name = $args['name'];
		}
		if (isset($args['value'])) {
			$this->value = $args['value'];
		}
		if (isset($args['default_value'])) {
			$this->default_value = $args['default_value'];
		}
		if (isset($args['template'])) {
			$this->template = $args['template'];
		}
		if (isset($args['params'])) {
			$this->params = array_merge($this->params, $args['params']);
		}
		if (isset($args['sort'])) {
			$this->sort = $args['sort'];
		}
		if (isset($args['sortKey'])) {
			$this->sortKey = $args['sortKey'];
		}
		if (isset($args['help'])) {
			$this->help = $args['help'];
		}
		if (isset($args['actions'])) {
			$this->actions = _action::createActions($this, $args['actions']);
		}

		if (isset($args['refresh']) && ($args['refresh'] or $args['refresh'] == 'Y')) {
			$tmp = 'VettichSP3.Devform.Refresh(this);';
			$event_name = 'onclick';
			if (isset($this->params[$event_name])) {
				$tmp .= $this->params[$event_name];
			}
			$this->params[$event_name] = $tmp;
		}
		if (empty($this->name)) {
			$this->name = $id;
		}
		if (isset($this->params['placeholder'])) {
			$this->params['placeholder'] = self::mess($this->params['placeholder']);
		}

		if (empty($this->value)) {
			if (($t = $this->getValueFromPost()) !== null) {
				$this->value = $t;
			}
		}
	}

	public function getValue($data=null)
	{
		if ($this->value === null) {
			if (is_object($data)) {
				$this->value = $data->getValue($this->name);
			} else {
				$this->value = _data::getValue($data, $this->name);
			}
		}
		return $this->value;
	}

	public function getFilterId()
	{
		return $this->filterMask.$this->id;
	}

	public function render($data=null)
	{
		$this->data = $data;
		$this->runActions();
		return $this->renderTemplate();
	}

	public function renderView($value='', $arRes=[])
	{
		if ($value === null) {
			$value = '';
		}
		$this->onHandler('renderView', $this, $value, $arRes);
		return $this->renderTemplate($this->templateView, ['{value}' => $value]);
	}

	public function renderTemplate($template='', $replaces=[])
	{
		$this->onHandler('renderTemplate', $this, $template, $replaces);
		if (empty($template)) {
			$template = $this->template;
		}

		if (isset($replaces['{value}'])) {
			$value = $replaces['{value}'];
		} else {
			$value = $this->getValue($this->data);
		}
		if (empty($value)) {
			$value = $this->default_value;
		}
		if (is_string($value)) {
			$value = self::mess($value);
		}

		$replaces_def = [
			'{content}' => $this->content,
			// '{title}' => $this->title . (isset($this->params['required']) ? ' <font color="red">*</font>' : ''),
			'{params}' => $this->renderParams(),
			'{help}' => $this->renderHelp(),
			'{title}' => $this->renderTitle($this->title, $this->params),
			'{default_value}' => $this->default_value,
			'{value}' => $value,
			'{name}' => $this->name,
			'{id}' => str_replace(['][', ']', '['], ['-', '', '-'], $this->id),
		];
		foreach ($replaces_def as $key => $value) {
			if (isset($replaces[$key])) {
				unset($replaces_def[$key]);
			}
		}
		$replaces = array_merge($replaces_def, $replaces);
		return str_replace(
			array_keys($replaces),
			array_values($replaces),
			$template
		);
	}

	protected function renderTitle($title, $params=[])
	{
		return isset($params['required']) ?
			"<b>$title</b> " :
			$title;
	}

	protected function renderParams()
	{
		$value = '';
		foreach ($this->params as $k => $v) {
			$v = str_replace('"', '&quot;', $v);
			$value .= " $k=\"$v\"";
		}
		return $value;
	}

	/**
	* @param array $types of type class
	* @return string
	*/
	public static function renderTypes($types, $data=null)
	{
		$result = [];
		$sort = 0;
		foreach ($types as $id => $param) {
			if ($param = self::createType($id, $param)) {
				$result[$param->sort.($sort++)] = $param->render($data);
			}
		}
		ksort($result);
		return implode('', $result);
	}

	public static function renderType($id, $param, $data=null)
	{
		if ($param = self::createType($id, $param)) {
			return $param->render($data);
		}
	}

	public function renderHelp()
	{
		if (empty($this->help)) {
			return '';
		}
		return str_replace(
			'{help_text}',
			self::mess($this->help),
			$this->templateHelp
		);
	}

	/**
	* create types
	* @param array $params
	* @param string $prefix
	* @return array _type objects
	*/
	public static function createTypes($params, $prefix='')
	{
		$result = [];
		foreach ($params as $id => $param) {
			if ($param = self::createType($prefix.$id, $param)) {
				$result[$param->id] = $param;
			}
		}
		return $result;
	}

	/**
	* @param string|int $id
	* @param array|object|string $param
	* @return object|null type class
	*/
	public static function createType($id, $param)
	{
		if (empty($param)) {
			return null;
		}
		if (is_object($param)) {
			// if($param->id == $param->name) {
			// 	$param->name = $id;
			// }
			// $param->id = $id;
			return $param;
		} elseif (is_array($param)) {
			return self::_createObject($param['type'], $id, $param);
		} elseif (is_string($param)) {
			// $arParam = explode(':', $param);
			$arParam = self::explode(':', $param);
			self::changeKey(0, 'type', $arParam);
			self::changeKey(1, 'title', $arParam);
			self::changeKey(2, 'default_value', $arParam);

			// $param = array(
			// 	'type' => $arParam[0],
			// 	'title' => $arParam[1],
			// 	'default_value' => $arParam[2],
			// 	'value' => $arParam[3],
			// );
			return self::_createObject($arParam['type'], $id, $arParam);
		} else {
			throw new TypeException("The type must be object, array or string.");
		}
		return null;
	}

	public static function _createObject($name, $id, $params)
	{
		$name = str_replace(['.', '/'], '\\', $name);
		if (class_exists($cl_name = 'vettich\sp3\devform\types\\'.$name)
				or class_exists($cl_name = $name)) {
			return new $cl_name($id, $params);
		}
		throw new TypeException("\"$name\" type not found");
	}

	public static function getValuesFromPost($types)
	{
		$result = [];
		foreach ($types as $id => $param) {
			if (($param = self::createType($id, $param)) && $param->is_saved) {
				$result[$param->name] = $param->getValueFromPost();
			}
		}
		return $result;
	}

	public function getValueFromPost()
	{
		return self::post($this->name);
	}

	public static function post($name)
	{
		if (($pos = strpos($name, '[')) !== false) {
			$res = self::arrayChain($_POST, self::strToChain($name));
		} else {
			$res = isset($_POST[$name]) ? $_POST[$name] : null;
		}
		return $res;
	}

	public function runActions()
	{
		// TODO
	}
}
