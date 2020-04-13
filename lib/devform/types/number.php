<?php
namespace vettich\sp3\devform\types;

/**
* @author Oleg Lenshin (Vettich)
*/
class number extends _type
{
	public $content = '<input type=number value="{value}" name="{name}" id="{id}" {params}>';
	public $templateView = '<span style="float:right">{value}</span>';
}
