<?
namespace vettich\devform\data;

use vettich\devform\exceptions\DataException;

/**
* @author Oleg Lenshin (Vettich)
*/
class option extends _data
{
	public $module_id = '';
	protected $dbClass = 'vettich\devform\db\optionTable';
	protected $arValues = null;

	function __construct($args = array())
	{
		if(isset($args['module_id'])) {
			$this->module_id = $args['module_id'];
		} elseif (isset($args['moduleId'])) {
			$this->module_id = $args['moduleId'];
		} else {
			throw new DataException('"module_id" param required');
		}

		$this->filter['module_id'] = $this->module_id;

		if(isset($args['paramPrefix'])) {
			$this->paramPrefix = $args['paramPrefix'];
		}
		parent::__construct($args);
	}

	public static function createFromString($arData)
	{
		if(!isset($arData[1])) {
			throw new DataException('"module_id" param required');
		}
		return new self(array(
			'module_id' => $arData[1],
			'paramPrefix' => $arData[2] ?: '',
		));
	}

	public function get($name, $default=null)
	{
		if(!$this->exists($name)) {
			return $default;
		}
		$name = $this->trim($name);
		if($this->arValues === null) {
			$db = $this->dbClass;
			try {
				$rs = $db::GetList(array('filter' => array(
					'MODULE_ID' => $this->module_id,
				)));
				while ($ar = $rs->fetch()) {
					if(!empty($ar['VALUE'])) {
						$value = unserialize($ar['VALUE']);
						if(!empty($value)) {
							$ar['VALUE'] = $value;
						}
					}
					$this->arValues[$ar['NAME']] = $ar;
				}
			} catch(\Exception $e) {}
		}

		if(isset($this->arValues[$name]['VALUE'])) {
			return $this->arValues[$name]['VALUE'];
		}
		return $default;
	}

	public function set($name, $value)
	{
		if(!$this->exists($name)) {
			return;
		}
		$name = $this->trim($name);
		$db = $this->dbClass;
		$_val = $this->get($name);
		if($_val === null && !isset($this->arValues[$name])) {
			if(empty($value)) {
				$value = '';
			}
			try {
				$rs = $db::add(array(
					'MODULE_ID' => $this->module_id,
					'NAME' => $name,
					'VALUE' => (is_array($value) or is_object($value)) ? serialize($value) : $value,
				));
				if($rs->isSuccess()) {
					$this->arValues[$name] = array(
						'ID' => $rs->getId(),
						'VALUE' => $value,
					);
				}
			} catch(\Exception $e) {}
		} elseif($this->arValues[$name]['VALUE'] != $value) {
			try {
				$rs = $db::update($this->arValues[$name]['ID'], array(
					'VALUE' => (is_array($value) or is_object($value)) ? serialize($value) : $value,
				));
				if($rs->isSuccess()) {
					$this->arValues[$name]['VALUE'] = $value;
				}
			} catch(\Exception $e) {}
		}
	}

	public function save(&$arValues=array())
	{
		$this->onHandler('beforeSave', $this, $arValues);
		foreach($arValues as $key => $value)
		{
			$this->set($key, $value);
		}
		$this->onHandler('afterSave', $this, $arValues);
	}
}
