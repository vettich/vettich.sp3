<?php
namespace vettich\sp3\devform;

use vettich\sp3\devform\types\_type;

/**
* show tabs for admin form
*
* @author Oleg Lenshin (Vettich)
* @var string $name
* @var string $title
* @var array $params
* @var boolean $enable
*/
class Tab extends Module
{
	public $name = 'Tab';
	public $title = '';
	public $params = null;
	public $enable = true;

	public function __construct($args = [])
	{
		parent::__construct($args);
		if (isset($args['name'])) {
			$this->name = self::mess($args['name']);
		}
		if (isset($args['title'])) {
			$this->title = self::mess($args['title']);
		}
		if (isset($args['params'])) {
			$this->params = $args['params'];
		}
		if (isset($args['enable'])) {
			$this->enable = $args['enable'];
		}
	}

	public function render($data=null)
	{
		echo _type::renderTypes($this->params, $data);
	}

	public static function createTab($params)
	{
		$result = false;
		if (is_object($params)) {
			$result = $params;
		} elseif (is_array($params)) {
			$tabClass = get_class();
			if (isset($params['class'])) {
				$tabClass = $params['class'];
				unset($params['class']);
			}
			$result = new $tabClass($params);
		} else {
			throw new exceptions\TabException("The parameter must be an object or an array");
		}
		return $result;
	}
}
