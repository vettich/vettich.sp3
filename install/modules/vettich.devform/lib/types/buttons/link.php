<?
namespace vettich\devform\types\buttons;

use vettich\devform\types\_type;
use vettich\devform\data\_data;

/**
* 
*/
class link extends _type
{
	public $template = '<a {params} id="{id}" href="{default_value}">{title}</a>';
	public $params = array('class' => 'adm-btn');
}
