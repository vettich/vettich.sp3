<?php
namespace vettich\sp3\devform\types;

/**
* @author Oleg Lenshin (Vettich)
*/
class group extends _type
{
	public $template = '<tr><td colspan=2><table width="100%"><tr id="{id}-wrap" {params}><td width="20%"><label for="{id}">{title}{help}</label></td><td>{content}</td></tr></table></td></tr>';
	public $inlineTemplateBegin = '<table><tr>';
	public $inlineTemplateEnd = '</tr></table>';
	public $inlineTemplateItemBegin = '<td>';
	public $inlineTemplateItemEnd = '</td>';
	public $label = '';
	public $inline = true;
	public $options = [];

	public function __construct($id, $args=[])
	{
		parent::__construct($id, $args);
		if (isset($args['label'])) {
			$this->label = $args['label'];
		}
		if (isset($args['options'])) {
			foreach ($args['options'] as $key => $param) {
				if (false === strpos($key, '[')
					or false === strpos($key, ']')) {
					$key = '['.$key.']';
				}
				$this->options[$key] = $param;
			}
		}
		if (isset($args['inline'])) {
			$this->inline = $args['inline'];
		}
		$this->params['data-id'] = $id;
		$this->params['class'] = $this->params['class'].' adm-detail-content-cell-l';
	}

	public function render($data=null)
	{
		$this->data = $data;
		$values = $this->getValue($this->data);
		if (empty($values)) {
			$values = $this->default_value;
		}
		$isAddButton = false;
		if (isset($values['_add'])) {
			if ($values['_add'] == 'Y') {
				$isAddButton = true;
			}
			unset($values['_add']);
		}
		$value = '';
		if ($this->inline) {
			$key = null;
			$i = 0;
			$val_count = count((array)$values);
			if (is_array($values)) {
				foreach ($values as $key => $val) {
					$deleteButton = false;
					$i++;
					if ($i < $val_count
						or ($i == $val_count
							&& (empty($_POST) or $isAddButton))) {
						$deleteButton = true;
					}
					$value .= $this->renderItems($key, $data, $deleteButton);
				}
			}
			if (empty($_POST)
				or $isAddButton) {
				if ($key !== null) {
					$key++;
				} else {
					$key = 0;
				}
				$value .= $this->renderItems($key, $data, false);
			}
			$value .= $this->renderAddButton();
		}
		$this->value = $value;
		return parent::render($data);
	}

	public function renderItems($key, $data, $deleteButton=true)
	{
		$value = $this->inlineTemplateBegin;
		foreach ($this->options as $id => $param) {
			$value .= $this->inlineTemplateItemBegin;
			$value .= self::renderType(
				$this->id.'['.$key.']'.$id,
				self::paramAttr($param, 'template', '{content}'),
				$data
			);
			$value .= $this->inlineTemplateItemEnd;
		}
		if ($deleteButton) {
			$value .= $this->inlineTemplateItemBegin;
			$value .= self::renderType(
				'',
				'divbutton::text=X:onclick=VettichSP3.Devform.GroupDelete(this);:template={content}:params=[title='.self::mess('#VDF_DELETE#').']'
			);
			$value .= $this->inlineTemplateItemEnd;
		}
		$value .= $this->inlineTemplateEnd;
		return $value;
	}

	public function renderAddButton()
	{
		$value = $this->inlineTemplateBegin;
		$value .= $this->inlineTemplateItemBegin;
		$value .= self::renderType(
			'',
			'divbutton::text=#VDF_ADD#:onclick=VettichSP3.Devform.GroupAdd(this);:template={content}'
		);
		$value .= $this->inlineTemplateItemEnd;
		$value .= $this->inlineTemplateEnd;
		return $value;
	}

	public static function paramAttr(&$param, $attr, $value=null)
	{
		if ($value === null) {
			return isset($param[$attr]) ? $param[$attr] : null;
		}
		if (is_string($param)) {
			if (false === strpos($param, $attr)) {
				$param .= ':'.$attr.'='.$value;
			} else {
				// nothing
			}
		} elseif (is_array($param)) {
			if (!isset($param[$attr])) {
				$param[$attr] = $value;
			}
		} elseif (is_object($param)) {
			$param->$attr = $value;
		}
		return $param;
	}
}
