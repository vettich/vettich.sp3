<?php
namespace vettich\devform\types;

/**
* @author Oleg Lenshin (Vettich)
*/
class datetime extends _type
{
	public $content = '<div class="adm-input-wrap adm-input-wrap-calendar">
		<input class="adm-input adm-input-calendar" type="text" name="{id}" size="22" value="{value}">
		<span class="adm-calendar-icon" title="Select date" onclick="BX.calendar({node:this, field:\'{id}\', form: \'\', bTime: true, bHideTime: false});"></span>
	</div>';
	public $filterMask = '%';

	public function __construct($id, $args = [])
	{
		parent::__construct($id, $args);
		$this->default_value = date('d.m.Y H:i:s');
	}

	public function renderView($value='', $arRes=[])
	{
		return self::formatDate($value);
	}

	public function getValue($data=null)
	{
		$value = parent::getValue($data);
		if (!empty($value)) {
			$value = self::formatDate($value);
		}
		return $value;
	}

	public static function formatDate($sdate)
	{
		return date('d.m.Y H:i:s', strtotime($sdate));
	}
}
