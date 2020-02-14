<?
namespace vettich\devform\types;

use vettich\devform\types\_type;

/**
* 
*/
class link extends _type
{
	public $content = '<a {params} id="{id}" href="{link}">{text}</a>';
	public $link = '';
	public $text = '';

	public function __construct($id, $args)
	{
		parent::__construct($id, $args);
		if(isset($args['link'])) {
			$this->link = $args['link'];
		}
		if(isset($args['text'])) {
			$this->text = $args['text'];
		}
	}

	public function renderTemplate($p1='', $repl=array())
	{
		$repl['{link}'] = $this->link;
		$repl['{text}'] = $this->text;
		return parent::renderTemplate($p1, $repl);
	}
}