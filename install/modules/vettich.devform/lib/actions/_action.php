<?
namespace vettich\devform\actions;

use vettich\devform\conditions\_condition;
use vettich\devform\types\type;

/**
* @author Oleg Lenshin (Vettich)
*/
abstract class _action extends vettich\devform\Module
{
	public $conditions = array();
	public $params = array();
	public $type = null;

	/**
	* @param array $args
	* @param vettich\devform\types\type $type
	*/
	function __construct($args=array(), $type=null)
	{
		$this->type = $type;
		if(isset($args['conditions'])) $this->conditions = _condition::createConditions($args['conditions'], $type);
		if(isset($args['params'])) $this->params = $args['params'];
	}

	public static function createActions($type, $params)
	{
		$result = array();
		foreach($params as $param)
		{
			$r = null;
			if(is_object($param))
				$r = $param;
			elseif(is_array($param))
			{
				$r = self::createFromArray($type, $param);
			}
			elseif(is_string($param))
			{
				$r = self::createFromString($type, $param);
			}

			if(!empty($r))
				$result[] = $r;
		}
		return $result;
	}

	public static function createObject($type, $name, $params)
	{
		if(class_exists($cl_name = 'vettich\devform\actions\\'.$name)
			or class_exists($cl_name = $name))
		{
			return new $cl_name($params, $type);
		}
		return null;
	}

	public static function createFromString($type, $params)
	{
		$exp = explode(':', $params);
		$params = array();
		if(isset($exp[1]))
		{
			$params['conditions'] = $exp[1];
		}

		return self::createObject($type, $exp[0], $params);
	}

	public static function createFromArray($type, $params)
	{
		$name = array_shift($params);
		$_params = array();
		if(!empty($params))
		{
			$_params['conditions'] = $params;
		}

		return self::createObject($type, $name, $_params);
	}

	public abstract function run($args = array());
}