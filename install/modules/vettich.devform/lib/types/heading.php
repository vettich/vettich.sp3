<?
namespace vettich\devform\types;

/**
* 
*/
class heading extends _type
{
	public $template = '<tr id="{id}" title="{help}" class="heading" onclick="Vettich.Devform.Heading(this);"><td colspan="2"><b>{title}</b></td></tr>';
	public $help;

	public function __construct($id, $args)
	{
		parent::__construct($id, $args);

		if(isset($args['help'])) {
			$this->help = $args['help'];
		} else {
			$this->help = GetMessage('VDF_HEADING_HELP_TITLE');
		}
	}

	public function renderTemplate($template='', $replaces=array())
	{
		$replaces['{help}'] = $this->help;
		return parent::renderTemplate($template, $replaces);
	}
}
