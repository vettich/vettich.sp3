<?php
namespace vettich\sp3\devform\types;

/**
*
*/
class hidden extends _type
{
	public $template = '<tr id="{id}-wrap" style="display:none"><td>{content}</td></tr>';
	public $content = '<input type="hidden" name="{name}" id="{id}" value="{value}" {params}>';
}
