<?
namespace vettich\devform\types;

/**
* @author Oleg Lenshin (Vettich)
*/
class radio extends _type
{
	public $content = '<div id="{id}" {params}> {options} </div>';
	public $option_template = '<input type="radio" name="{name}" {checked} id="{id}-{value}" value="{value}"> <label for="{id}-{value}">{label}</label><br>';
	public $label = '';
	public $options = null;

	public function __construct($id, $args=array())
	{
		parent::__construct($id, $args);
		if(isset($args['label'])) {
			$this->label = $args['label'];
		}
		if(isset($args['options'])) {
			$this->options = $args['options'];
		}
	}

	public function renderTemplate($template='', $replaces=array())
	{
		if(isset($replaces['{value}'])) {
			$value = $replaces['{value}'];
		} else {
			$value = $this->getValue($this->data);
		}
		if(empty($value)) {
			$value = $this->default_value;
		}
		$html_options = '';
		foreach ($this->options as $key => $opt) {
			$repls = array(
				'{checked}' => ($key == $value) ? 'checked' : '',
				'{label}' => $opt,
				'{value}' => $key,
			);
			$html_options .= str_replace(
				array_keys($repls),
				array_values($repls),
				$this->option_template
			);
		}
		$replaces['{options}'] = $html_options;
		$replaces['{id}'] = $this->id;
		$replaces['{name}'] = $this->name;

		return parent::renderTemplate($template, $replaces);
	}

	public function renderView($value='')
	{
		if(isset($this->options[$value])) {
			$value = $this->options[$value];
		}
		return parent::renderView($value);
	}
}