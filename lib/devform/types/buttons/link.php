<?php
namespace vettich\sp3\devform\types\buttons;

use vettich\sp3\devform\types\_type;
use vettich\sp3\devform\data\_data;

/**
*
*/
class link extends _type
{
	public $template = '<a {params} id="{id}" href="{default_value}">{title}</a>';
	public $params = ['class' => 'adm-btn'];
}
