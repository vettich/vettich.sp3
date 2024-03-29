<?php
namespace vettich\sp3\devform\data;

/**
* @author Oleg Lenshin (Vettich)
*/
class _data extends \vettich\sp3\devform\Module
{
	public $datas = null;
	
	protected $paramPrefix = '';
	protected $trimPrefix = true;

	public function __construct($args=[])
	{
		if (isset($args['paramPrefix'])) {
			$this->paramPrefix = $args['paramPrefix'];
		}
		if (isset($args['prefix'])) {
			$this->paramPrefix = $args['prefix'];
		}
		if (isset($args['trimPrefix'])) {
			$this->trimPrefix = $args['trimPrefix'];
		}
		parent::__construct($args);
	}

	public function save(&$arValues=[])
	{
	}
	public function get($valueName, $default=null)
	{
	}

	public function delete($name, $value)
	{
		if (empty($this->datas)) {
			return false;
		}
		foreach ($this->datas as $data) {
			$data->delete($name, $value);
		}
		return true;
	}

	public static function createDatas($datas)
	{
		$_this = new self;
		$_this->datas = self::initData($datas);
		if (!is_array($_this->datas)) {
			$_this->datas = [$_this->datas];
		}
		return $_this;
	}

	private static function initData($data)
	{
		if (is_object($data)) {
			return $data;
		}
		if (is_string($data)) {
			$data = self::explode(':', $data);
			self::changeKey(0, 'class', $data);
		}
		if (is_array($data)) {
			if (isset($data['class'])) {
				$def = ['namespace' => 'vettich\sp3\devform\data'];
				return self::createObject($def + $data);
			}
			$result = [];
			foreach ($data as $d) {
				$result[] = self::initData($d);
			}
			return $result;
		}
		return null;
	}

	public function getValue($valueName)
	{
		return self::getValueFromDatas($this->datas, $valueName);
	}

	public static function getValueFromDatas($datas, $valueName)
	{
		if (is_array($datas)) {
			foreach ($datas as $data) {
				$r = $data->get($valueName);
				if ($r !== null) {
					return $r;
				}
			}
		}
		return null;
	}

	public function saveValues(&$arValues)
	{
		foreach ($this->datas as $data) {
			$res = $data->save($arValues);
			if ($res === false or isset($res['error'])) {
				return $res;
			}
		}
		return true;
	}

	protected function exists($paramName)
	{
		if (empty($this->paramPrefix)) {
			return true;
		}
		return strpos($paramName, $this->paramPrefix) === 0;
	}

	protected function trim($paramName)
	{
		if ($this->trimPrefix
			&& !empty($this->paramPrefix)
			&& strpos($paramName, $this->paramPrefix) === 0) {
			return substr($paramName, strlen($this->paramPrefix));
		}
		return $paramName;
	}

	public function prefix($value=null)
	{
		if ($value == null) {
			return $this->paramPrefix;
		}
		$this->paramPrefix = $value;
	}
}
