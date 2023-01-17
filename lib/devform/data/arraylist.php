<?php
namespace vettich\sp3\devform\data;

/**
* events:
*    on get: function($thisObj, $key)
*    on beforeSave: function($thisObj, $arValues)
*    on afterSave: function($thisObj, $arValues)
*
* @author Oleg Lenshin (Vettich)
*/
class ArrayList extends _data
{
	public $values = [];

	public function __construct($args = [])
	{
		if (isset($args['values'])) {
			$this->values = $args['values'];
		}

		parent::__construct($args);
	}

	public function save(&$arValues=[])
	{
		$res = $this->onHandler('beforeSave', $this, $arValues);
		if ($res === false or isset($res['error'])) {
			return $res;
		}
		foreach ($arValues as $key => $value) {
			$this->set($key, $value);
		}
		$res = $this->onHandler('afterFillValues', $this, $arValues);
		if ($res === false or isset($res['error'])) {
			return $res;
		}
		return $this->onHandler('afterSave', $this, $arValues);
	}

	public function get($name, $default=null)
	{
		if (!$this->exists($name)) {
			return $default;
		}
		if ($this->trimPrefix) {
			$name = $this->trim($name);
		}
		return self::arrayChain($this->values, self::strToChain($name), $default);
	}


	public function set($name, $value)
	{
		if (!$this->exists($name)) {
			return;
		}
		if ($this->trimPrefix) {
			$name = $this->trim($name);
		}
		self::arrayChainSet($this->values, self::strToChain($name), $value);
		/* $this->values[$name] = $value; */
	}

	public function getList($params=[])
	{
		if (!empty($values)) {
			return $this->values;
		}
		$this->values = $this->onHandler('list', $this, $params);
		return $this->values;
	}

	public function delete($name, $value)
	{
		$this->onHandler('delete', $this, $name, $value);
	}
}
