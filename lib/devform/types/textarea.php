<?php
namespace vettich\sp3\devform\types;

/**
* @author Oleg Lenshin (Vettich)
*/
class textarea extends _type
{
	public $content = '<textarea name="{name}" id="{id}" {params}>{value}</textarea>
		<div class="textarea_select">
			<div class="adm-btn">...</div>
			<div class="items">{items}</div>
		</div>';
	public $filterMask = '%';
	public $items = [];
	public $params = ['style' => 'width:70%'];

	public function __construct($id, $args = [])
	{
		parent::__construct($id, $args);
		if (isset($args['items'])) {
			$this->items = $args['items'];
		}
	}

	public function renderTemplate($template='', $replaces=[])
	{
		$replaces['{items}'] = self::renderItems($this->items);
		return parent::renderTemplate($template, $replaces);
	}

	public static function renderItems($items)
	{
		$res = '';
		foreach ($items as $key => $value) {
			if (is_array($value)) {
				if (empty($value['items'])) {
					continue;
				}
				$res .= "<span data-value=\"$key\">$value[label]</span>";
				$res .= self::renderItems($value['items']);
				continue;
			}
			$res .= "<div data-value=\"$key\">$value</div>";
		}
		return $res;
	}
}
