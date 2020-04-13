<?
namespace vettich\sp3\devform\types;

/**
* @author Oleg Lenshin (Vettich)
*/
class list extends _type
{
	public $content = '<select name="{name}[]" id="{id}" multiple="" {params}>{options}</select>';
	public $content_multiple = '<select name="{name}[]" id="{id}" multiple="" {params}>{options}</select>';
	public $options = array();
	public $option_template = '<option {selected} value="{value}">{name}</option>';
	public $option_template_multiple = '<input type="checkbox" name="{name}[{value}]" {checked} id="{id}-{value}"> <label for="{id}-{value}">{name}</label>';

	public function __construct($id, $args)
	{
		parent::__construct($id, $args);

		if(isset($args['options'])) {
			$this->options = $args['options'];
		}
		if(isset($args['option_template'])) {
			$this->option_template = $args['option_template'];
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
		if(!is_array($value)) {
			$value = unserialize($value);
		}
		$html_options = '';
		foreach ($this->options as $key => $opt) {
			$repls = array(
				'{selected}' => in_array($key, $value) ? 'selected' : '',
				'{name}' => $opt,
				'{value}' => $key,
			);
			$html_options .= str_replace(
				array_keys($repls),
				array_values($repls),
				$this->option_template
			);
		}
		$replaces['{options}'] = $html_options;

		return parent::renderTemplate($template, $replaces);
	}

	public function renderView($value='')
	{
		$result = array();
		foreach ($value as $key) {
			$result[] = $this->options[$key];
		}
		return parent::renderView(implode('<br>', $result));
	}
}
