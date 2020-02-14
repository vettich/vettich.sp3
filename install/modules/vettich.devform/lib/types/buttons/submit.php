<?
namespace vettich\devform\types\buttons;

/**
* 
*/
class submit extends simple
{
	public $template = '<input {params} name="{name}" value="{title}">';
	public $params = array('type' => 'submit');
}