<?php
namespace vettich\sp3\devform\types;

/**
* @author Oleg Lenshin (Vettich)
*/
class password extends _type
{
	public $content = '<input type=password value="{value}" name="{name}" id="{id}" {params}>';
	public $filterMask = '%';
	public $params = ['size' => '50%'];
}
