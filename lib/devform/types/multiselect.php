<?php
namespace vettich\sp3\devform\types;

/**
* @author Oleg Lenshin (Vettich)
*/
class multiselect extends _type
{
	public $content = '<select name="{name}[]" id="{id}" multiple="" {params}>{options}</select>';
	public $options = [];
	public $option_template = '<option {selected} value="{value}">{name}</option>';

	public function __construct($id, $args)
	{
		parent::__construct($id, $args);

		if (isset($args['options'])) {
			$this->options = $args['options'];
		}
		if (isset($args['option_template'])) {
			$this->option_template = $args['option_template'];
		}
	}

	public function renderTemplate($template='', $replaces=[])
	{
		if (isset($replaces['{value}'])) {
			$value = $replaces['{value}'];
		} else {
			$value = $this->getValue($this->data);
		}
		if (empty($value)) {
			$value = $this->default_value;
		}
		if (!is_array($value)) {
			$value = unserialize($value);
			if (!is_array($value)) {
				$value = [];
			}
		}
		$html_options = '';
		foreach ($this->options as $key => $opt) {
			$repls = [
				'{selected}' => in_array($key, $value) ? 'selected' : '',
				'{name}' => $opt,
				'{value}' => $key,
			];
			$html_options .= str_replace(
				array_keys($repls),
				array_values($repls),
				$this->option_template
			);
		}
		$replaces['{options}'] = $html_options;

		return parent::renderTemplate($template, $replaces);
	}

	public function renderView($value='', $arRes=[])
	{
		$result = [];
		foreach ($value as $key) {
			$result[] = $this->options[$key];
		}
		return parent::renderView(implode('<br>', $result));
	}
}
