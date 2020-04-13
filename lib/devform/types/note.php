<?php
namespace vettich\sp3\devform\types;

/**
*
*/
class note extends _type
{
	public $template = '<tr><td colspan="2"><div id="{id}" {params}>{title}</div></td></tr>';
	public $templateView = '<div {params}>{title}</div>';
	public $params = ['style' => 'display:block;', 'class' => 'adm-info-message'];
}
