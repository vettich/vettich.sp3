<?
namespace vettich\devform\conditions;

/**
* @author Oleg Lenshin (Vettich)
*/
abstract class _condition
{
	public $params = array();
	public $type = null;

	function __construct($args = array(), $type = null)
	{
		if(isset($args['params'])) $this->params = $args['params'];
		$this->type = $type;
	}

	public static function createConditions($params, $type = null)
	{
		if(is_object($params))
			return $params;
		elseif(is_string($params))
		{
			$res = self::createFromString($params, $type);
			if(!empty($res))
				return array($res);
		}
		elseif(is_array($params))
		{
			return self::createFromArray($params, $type);
		}
		return null;
	}

	public static function getClassName($name)
	{
		if(class_exists($cl_name = 'vettich\devform\conditions\\'.$name)
			or class_exists($cl_name = $name))
			return $cl_name;
		return null;
	}

	public static function createObject($name, $params, $type = null)
	{
		if($cl_name = self::getClassName($name))
		{
			return new $cl_name($params, $type);
		}
		return null;
	}

	public static function createFromString($str, $type = null)
	{
		$result = explode('(', $str);
		if(isset($result[1]))
		{
			$result[1] = array('params' => explode(',', substr($result[1], 0, -1)));
		}

		return self::createObject($result[0], $result[1], $type);
	}

	public static function createFromArray($params, $type=null)
	{
		$result = array();
		$is_list = false;
		foreach($params as $param)
		{
			if(is_object($param))
			{
				$is_list = true;
			}
			elseif(is_array($param))
			{
				$is_list = true;
				$param = self::createFromArray($param, $type);
			}
			elseif(is_string($param))
			{
				if($param == 'OR' or $param == 'AND')
				{
					$is_list = true;
				}
				elseif($cl_name = self::getClassName($param))
				{
					array_shift($params);
					return self::createObject($param, $params, $type);
				}
				else
				{
					$is_list = true;
					$param = self::createFromString($param, $type);
				}
			}

			if($is_list)
			{
				$result[] = $param;
			}
			else
				$break;
		}
		return $result;
	}

	public function getArg($str)
	{
		$str = trim($str);
		$result = $str;
		if($str[0] == '#'
			&& !empty($this->type)
			&& !empty($this->type->data))
		{
			$result = $this->type->data->get(substr($str, 1));
		}
		return $result;
	}

	public static function execConditions($conds = array())
	{
		$result = true;
		$logic = '';
		foreach($conds as $cond)
		{
			if(is_array($cond))
				$r = self::execConditions($cond);
			elseif(is_object($cond))
				$r = $cond->run();
			elseif(is_string($cond))
			{
				if($logic == '')
				{
					if($cond == 'AND')
						$result = true;
					elseif($cond == 'OR')
						$result = false;
				}
				elseif($logic == 'AND' && $cond == 'OR')
					$result |= false;
				elseif($logic == 'OR' && $cond == 'AND')
					$result &= true;

				$logic = $cond;
				continue;
			}
	
			if($logic == 'AND'
				or ($logic == '' && $logic = 'AND'))
			{
				$result &= $r;
				if(!$r)
					break;
			}
			elseif($logic == 'OR')
			{
				$result |= $r;
				if($r)
					break;
			}
		}
		return $result;
	}

	public abstract function run($args);
}
