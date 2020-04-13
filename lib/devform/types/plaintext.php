<?php
namespace vettich\sp3\devform\types;

/**
* @author Oleg Lenshin (Vettich)
*/
class plaintext extends _type
{
	public $content = '<span id="{id}" {params}>{value}</span>';
	public $filterMask = '%';
}
