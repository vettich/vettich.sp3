<?
namespace vettich\devform\conditions;

/**
* @author Oleg Lenshin (Vettich)
*/
class cmp extends _condition
{
	public function run($args = array())
	{
		$result = true;
		if(count($this->params) == 2)
		{
			$arg1 = $this->getArg($this->params[0]);
			$arg2 = $this->getArg($this->params[1]);
			$result = ($arg1 == $arg2);
		}
		elseif(count($this->params) == 3)
		{
			$eq = $this->params[0];
			$arg1 = $this->getArg($this->params[1]);
			$arg2 = $this->getArg($this->params[2]);
			eval('$result=($arg1'.$eq.'$arg2);');
		}
		return $result;
	}
}
