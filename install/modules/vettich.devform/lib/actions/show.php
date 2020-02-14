<?
namespace vettich\devform\actions;

use vettich\devform\conditions\_condition;

/**
* @author Oleg Lenshin (Vettich)
*/
class show extends _action
{
	public function run($args = array())
	{
		// if(!$this->runConditions())
		if(!_condition::execConditions($this->conditions))
		{
			$style = '<style>#'.$this->type->id.'-wrap{display:none}</style>';
			// $style = '<script>alert("test")</script>';
			\Bitrix\Main\Page\Asset::getInstance()->addString($style);
		}
	}
}