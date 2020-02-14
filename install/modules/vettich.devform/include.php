<?
IncludeModuleLangFile(__FILE__);

if(!function_exists('devdebug')) {
	function devdebug($mess, $filename=null, $once=false, $isVardump=false)
	{
		if(!defined('VETTICH_DEBUG') or !VETTICH_DEBUG) {
			return;
		}
		static $ar = array();
		if($once) {
			if(in_array($once, $ar)) {
				return;
			} else {
				$ar[] = $once;
			}
		}
		if(is_array($mess)) {
			array_walk_recursive($mess, function(&$mess) {
				$mess = htmlspecialchars($mess);
			});
		}
		if($isVardump) {
			ob_start();
			var_dump($mess);
			$mess = ob_get_contents();
			ob_clean();
		} else {
			$mess = print_r($mess, true);
		}
		if($filename !== null) {
			if(!is_dir($debugPath = __DIR__.'/debug/'))
				mkdir($debugPath, 0775);
			error_log('<pre>'.date('Y/m/d H:i:s')."\n".$mess.'</pre>'."\n", 3, $debugPath.$filename.'.html');
		} else {
			echo '<pre>';
			echo $mess;
			echo '</pre>';
		}
	}
}
