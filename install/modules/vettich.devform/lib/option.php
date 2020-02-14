<?
namespace vettich\devform;

use vettich\devform\db\optionTable;

/**
* @author Vettich
*/
class Option extends Module
{
	private static $_options = array();

	private static function initFor($module_id)
	{
		$options = array();
		try{
			$rs = optionTable::getList(array(
				'filter' => array(
					'MODULE_ID' => $module_id,
				),
				'select' => array('ID', 'MODULE_ID', 'NAME', 'VALUE'),
			));
			while ($ar = $rs->fetch()) {
				$options[$ar['NAME']] = array(
					'ID' => $ar['ID'],
					'VALUE' => $ar['VALUE'],
				);
			}
		} catch (Exception $e) {}
		self::$_options[$module_id] = $options;
	}

	private static function save($module_id, $name, $value)
	{
		try {
			$arFields = array(
				'MODULE_ID' => $module_id,
				'NAME' => $name,
				'VALUE' => $value,
			);
			if(!empty(self::$_options[$module_id][$name]['ID'])) {
				$rs = optionTable::update(self::$_options[$module_id][$name]['ID'], $arFields);
			} else {
				$rs = optionTable::add($arFields);
			}
		} catch (Exception $e) {
			return false;
		}
		return true;
	}

	public static function GetOptionInt($module_id, $name, $def=0)
	{
		if(!isset(self::$_options[$module_id])) {
			self::initFor($module_id);
		}
		if(!isset(self::$_options[$module_id][$name])) {
			return $def;
		}
		return intval(self::$_options[$module_id][$name]['VALUE']);
	}

	public static function GetOptionString($module_id, $name, $def='')
	{
		if(!isset(self::$_options[$module_id])) {
			self::initFor($module_id);
		}
		if(!isset(self::$_options[$module_id][$name])) {
			return $def;
		}
		return self::$_options[$module_id][$name]['VALUE'];
	}

	public static function GetOptionArray($module_id, $name, $def=array())
	{
		if(!isset(self::$_options[$module_id])) {
			self::initFor($module_id);
		}
		if(!isset(self::$_options[$module_id][$name])) {
			return $def;
		}
		return unserialize(self::$_options[$module_id][$name]['VALUE']);
	}

	public static function SetOptionInt($module_id, $name, $value)
	{
		return self::save($module_id, $name, intval($value));
	}

	public static function SetOptionString($module_id, $name, $value)
	{
		if(is_string($value)) {
			return self::save($module_id, $name, $value);
		}
		return false;
	}

	public static function SetOptionArray($module_id, $name, $value)
	{
		if(is_array($value) or is_object($value)) {
			return self::save($module_id, $name, serialize($value));
		}
		return false;
	}
}