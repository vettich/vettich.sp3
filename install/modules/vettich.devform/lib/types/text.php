<?
namespace vettich\devform\types;

/**
* @author Oleg Lenshin (Vettich)
*/
class text extends _type
{
	public $content = '<input type=text value="{value}" name="{name}" id="{id}" {params}>';
	public $filterMask = '%';
	public $params = array('size' => '50%');
}