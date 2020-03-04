<?php
namespace vettich\devform\types;

use CFile;

/**
* @author Oleg Lenshin (Vettich)
*/
class image extends _type
{
	public $content = '<input name="{name}" value="{value}" {params}>';
	public $params = ['type' => 'file'];
	public $module_id = 'vettich.devform';
	public $maxCount = 1;
	public $multiple = false;
	public $raw = false;

	public function __construct($id, $args)
	{
		parent::__construct($id, $args);

		if (isset($args['module_id'])) {
			$this->module_id = $args['module_id'];
		}
		if (isset($args['maxCount'])) {
			$this->maxCount = $args['maxCount'];
		}
		if (isset($args['multiple'])) {
			$this->multiple = $args['multiple'];
		}
		if (isset($args['raw'])) {
			$this->raw = $args['raw'];
		}
		if ($this->maxCount > 1) {
			$this->multiple = true;
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
		if (class_exists('\Bitrix\Main\UI\FileInput')) {
			$inputName = $this->name;
			if ($this->multiple) {
				$inputName .= '[n#IND#]';
			}
			$this->content = \Bitrix\Main\UI\FileInput::createInstance([
					"name" => $inputName,
					"description" => false,
					"upload" => true,
					"allowUpload" => "I",
					"medialib" => false,
					"fileDialog" => false,
					"cloud" => false,
					"delete" => true,
					"maxCount" => $this->maxCount,
				])->show($value);
		} else {
			$this->content = CFile::InputFile($this->name, 20, $value);
			if ($value > 0) {
				$this->content .= '<br>'.CFile::ShowImage($value, 200, 200, "border=0", "", true);
				$this->content .= '<input type="hidden" name="'.$this->name.'_old" value="'.$value.'">';
			}
		}
		if ($value > 0) {
			$this->content .= '<input type="hidden" name="'.$this->name.'_old" value="'.$value.'">';
		}


		return parent::renderTemplate($template, $replaces);
	}

	public function renderView($value='', $arRes=[])
	{
		return CFile::ShowImage($value, 60, 60, "border=0", "", true);
	}

	public function getValueFromPost()
	{
		if ($_SERVER['REQUEST_METHOD'] != 'POST') {
			return 0;
		}
		$arIMAGE = self::post($this->name);
		if ($this->raw) {
			return $arIMAGE;
		}
		if (!$this->multiple) {
			$arIMAGE = [$arIMAGE];
		}
		$results = [];
		foreach ($arIMAGE as $key => $img) {
			$pathinfo = \Bitrix\Main\UI\Uploader\Uploader::getPaths($img["tmp_name"]);
			$img['tmp_name'] = $pathinfo['tmp_name'];
			$img['tmp_url'] = $pathinfo['tmp_url'];
			$img['old_file'] = self::post($this->name.'_old');
			$img['del'] = self::post($this->name.'_del');
			$img['MODULE_ID'] = $this->module_id;
			if (!empty($img['name']) || !empty($img['del'])) {
				$fid = CFile::SaveFile($img, $this->module_id);
				$this->value = $fid;
				$results[] = $fid;
			}
		}
		if ($this->multiple) {
			return $results;
		}
		if (empty($results)) {
			return 0;
		}
		return $results[0];
	}
}
