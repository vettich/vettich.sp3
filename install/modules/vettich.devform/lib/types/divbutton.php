<?
namespace vettich\devform\types;

use vettich\devform\types\_type;

/**
* 
*/
class divbutton extends _type
{
	public $content = '<div id="{id}" {params}>{value}</div>';
	public $params = array('class' => 'adm-btn');
	public $onclick = '';

	public function __construct($id, $args)
	{
		parent::__construct($id, $args);

		if(isset($args['onclick'])) {
			$this->onclick = $args['onclick'];
			$this->params['onclick'] = $args['onclick'];
		}
		if(isset($args['text'])) {
			$this->value = self::mess($args['text']);
		}
	}
}
