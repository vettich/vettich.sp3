<?php
namespace vettich\devform\data;

use vettich\devform\exceptions\DataException;

/**
* @author Oleg Lenshin (Vettich)
*/
class COption extends _data
{
	public $module_id = '';

	public function __construct($args = [])
	{
		if (isset($args['module_id'])) {
			$this->module_id = $args['module_id'];
		} elseif (isset($args['moduleId'])) {
			$this->module_id = $args['moduleId'];
		} else {
			throw new DataException('"module_id" param required');
		}

		if (isset($args['paramPrefix'])) {
			$this->paramPrefix = $args['paramPrefix'];
		}

		parent::__construct($args);
	}

	public static function createFromString($arData)
	{
		if (!isset($arData[1])) {
			throw new DataException('"module_id" param required');
		}
		return new self([
			'module_id' => $arData[1],
			'paramPrefix' => $arData[2] ?: '',
		]);
	}

	public function save(&$arValues=[])
	{
		$this->onHandler('beforeSave', $this, $arValues);
		foreach ($arValues as $key => $value) {
			self::set($key, $value);
		}
		$this->onHandler('afterSave', $this, $arValues);
	}

	public function get($name, $default=null)
	{
		if ($this->trimPrefix) {
			$name = $this->trim($name);
		}

		return \COption::GetOptionString($this->module_id, $name, $default);
	}

	public function set($name, $value)
	{
		if (!$this->exists($name)) {
			return;
		}
		if ($this->trimPrefix) {
			$name = $this->trim($name);
		}
		if (is_array($value) or is_object($value)) {
			$value = serialize($value);
		}

		\COption::SetOptionString($this->module_id, $name, $value);
	}
}
