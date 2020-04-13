<?php
namespace vettich\sp3\devform\types\buttons;

use vettich\sp3\devform\types\_type;

/**
*
*/
class simple extends _type
{
	private $_template = '<input {params} name="{name}" value="{title}">';
	public $params = ['class' => 'adm-btn', 'type' => 'button'];

	public function __construct($id, $args)
	{
		parent::__construct($id, $args);

		if (!empty($args['isRow'])) {
			$this->content = $this->_template;
			$this->template = str_replace('<label for="{id}">{title}</label>', '', $this->template);
		} else {
			$this->template = $this->_template;
		}
	}
}
