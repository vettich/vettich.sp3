<?php
namespace vettich\devform\types;

/**
* @author Oleg Lenshin (Vettich)
*/
class select extends _type
{
	public $content = '<select name="{name}" id="{id}" {params}>{options}</select>';
	public $options = [];
	public $option_group_template = '<optgroup label="{label}">{options}</optgroup>';
	public $option_template = '<option {selected} value="{value}" {option_params}>{name}</option>';
	public $textOption = false;

	public function __construct($id, $args)
	{
		parent::__construct($id, $args);

		if (isset($args['textOption'])) {
			$this->textOption = $args['textOption'];
		}
		if (isset($args['options'])) {
			$this->options = $args['options'];
		}
		if (isset($args['option_template'])) {
			$this->option_template = $args['option_template'];
		}
	}

	public function renderTemplate($template='', $replaces=[])
	{
		$this->onHandler('renderTemplate', $this, $template, $replaces);
		if (isset($replaces['{value}'])) {
			$value = $replaces['{value}'];
		} else {
			$value = $this->getValue($this->data);
		}
		if ($value === null) {
			$value = $this->default_value;
		}

		$html_options = self::renderOptions($this->options, $value);
		if ($this->textOption) {
			$repls = [
				'{selected}' => '',
				'{name}' => self::mess('#VDF_TEXT_OPTION#'),
				'{value}' => '',
				'{option_params}' => 'data-text-option="true"',
			];
			$html_options .= str_replace(
				array_keys($repls),
				array_values($repls),
				$this->option_template
			);
			$this->params['class'] .= ' js-text-option';
		}
		$replaces['{options}'] = $html_options;

		return parent::renderTemplate($template, $replaces);
	}

	public function renderOptions($options, $value)
	{
		$html_options = '';
		if (!is_array($options)) {
			return '';
		}
		foreach ($options as $key => $opt) {
			if (is_array($opt)) {
				$repls = [
					'{label}' => self::mess($opt['label'] ?: $opt['name']),
					'{options}' => self::renderOptions($opt['options'] ?: $opt['items'], $value),
				];
				if (empty($repls['{options}'])) {
					continue;
				}
				$html_options .= str_replace(
					array_keys($repls),
					array_values($repls),
					$this->option_group_template
				);
				continue;
			}
			$repls = [
				'{selected}' => ($value == $key) ? 'selected' : '',
				'{name}' => self::mess($opt),
				'{value}' => $key,
				'{option_params}' => '',
			];
			$html_options .= str_replace(
				array_keys($repls),
				array_values($repls),
				$this->option_template
			);
		}
		return $html_options;
	}

	public function renderView($value='', $arRes=[])
	{
		$value = self::mess($this->options[$value]);
		return parent::renderView($value);
	}
}
