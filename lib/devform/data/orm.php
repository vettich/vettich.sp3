<?php
namespace vettich\sp3\devform\data;

use vettich\sp3\devform\exceptions\DataException;

/**
* @author Oleg Lenshin (Vettich)
*/
class orm extends _data
{
	public $filter = [];
	public $ID = null;

	/** @var string db class */
	private $db = null;
	private $arValues = null;

	public function __construct($args=[])
	{
		if (isset($args['filter'])) {
			$this->filter = $args['filter'];
		} elseif (!empty($_GET['ID'])) {
			$this->filter = ['ID' => $_GET['ID']];
		}

		if (isset($args['db'])) {
			$this->db = $args['db'];
			if (!$this->isClass()) {
				throw new DataException("\"$this->db\" not found");
			}
		} elseif (isset($args['dbClass'])) {
			$this->db = $args['dbClass'];
			if (!$this->isClass()) {
				throw new DataException("\"$this->dbClass\" not found");
			}
		} elseif (isset($args['values'])) {
			$this->arValues = $args['values'];
		} else {
			throw new DataException("\"db\" or \"values\" param not found");
		}

		parent::__construct($args);
	}

	private function isClass()
	{
		if (class_exists($this->db)) {
			return true;
		}
		if (class_exists($this->db.'Table')) {
			$this->db .= 'Table';
			return true;
		}
		return false;
	}

	public function save(&$arValues=[])
	{
		$isChange = false;
		foreach ($arValues as $key => $value) {
			if (!$this->exists($key)) {
				continue;
			}
			if ($this->trimPrefix) {
				$key = $this->trim($key);
			}
			/*if($this->arValues[$key] != $value)*/ {
				self::valueTo($this->arValues, $key, $value);
				$isChange = true;
			}
		}
		if ($isChange && !empty($this->db)) {
			$cl = $this->db;
			$arV = $this->arValues;
			unset($arV['ID']);
			if (is_callable($this->beforeSave)) {
				call_user_func($this->beforeSave, $this, $arValues);
			}
			if ($this->arValues['ID'] > 0) {
				$result = $cl::update($this->arValues['ID'], $arV);
			} else {
				$result = $cl::add($arV);
			}
			if (is_callable($this->afterSave)) {
				call_user_func($this->afterSave, $this, $result);
			}
		}
		if (empty($result)) {
			return null;
		}

		$key = 'ID';
		if ($this->trimPrefix && !empty($this->paramPrefix)) {
			$key = $this->paramPrefix.'ID';
		}
		$arValues[$key] = $this->arValues['ID'] = $result->getId();
		return $arValues[$key];
	}

	public function getList($params=[])
	{
		if (isset($params['select'])) {
			foreach ($params['select'] as $key => $value) {
				if (false !== ($pos = strpos($value, '['))) {
					$params['select'][$key] = substr($value, 0, $pos);
				}
			}
		}
		if (empty($this->db)) {
			if (!empty($this->arValues)) {
				return $this->arValues;
			}
			return null;
		}
		$cl = $this->db;
		if (is_array($params['filter'])) {
			$params['filter'] = array_merge((array)$this->filter, (array)$params['filter']);
		} else {
			$params['filter'] = $this->filter;
		}
		try {
			return $cl::getList($params);
		} catch (\Exception $e) {
		}
		return null;
	}

	public function get($name, $default=null)
	{
		if (!$this->exists($name)) {
			return $default;
		}
		return $this->_value($name, $default);
	}

	public function set($name, $val)
	{
		return $this->arValues[$name] = $val;
	}

	public function value($name, $val=null)
	{
		if ($val === null) {
			return $this->get($name);
		} else {
			return $this->set($name, $val);
		}
	}

	private function _value($name, $default=null)
	{
		if ($this->trimPrefix) {
			$name = $this->trim($name);
		}
		if ($this->arValues) {
			// if($name == 'CONDITIONS') {
			// devdebug($name);
			// }
			// devdebug($this->arValues, 'post');
			return self::arrayChain($this->arValues, self::strToChain($name), $default);
		}
		if (!$this->filter['ID']) {
			return $default;
		}
		if (empty($this->db)) {
			return $default;
		}
		$cl = $this->db;
		$rs = $cl::getList([
			'filter' => $this->filter,
			'limit' => 1,
		]);
		if ($ar = $rs->fetch()) {
			$this->arValues = $ar;
		}
		return $this->arValues[$name] ?: $default;
	}

	public function delete($name, $value)
	{
		if (!$this->exists($name)) {
			return null;
		}

		$cl = $this->db;
		return $cl::delete($value)->isSuccess();
	}
}
