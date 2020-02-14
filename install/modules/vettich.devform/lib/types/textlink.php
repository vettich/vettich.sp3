<?
namespace vettich\devform\types;

use vettich\devform\types\_type;

/**
* 
*/
class textlink extends text
{
	// public $content = '<a {params} id="{id}" href="{default_value}">{value}</a>';
	public $templateView = '<a {params} id="{id}" href="{href}">{value}</a>';
	public $href = '';

	public function __construct($id, $args)
	{
		parent::__construct($id, $args);

		if(isset($args['href'])) {
			$this->href = $args['href'];
		}
	}

	public function renderView($value='')
	{
		return $this->renderTemplate($this->templateView, array(
			'{value}' => $value,
			'{href}' => $this->href,
		));
	}
}