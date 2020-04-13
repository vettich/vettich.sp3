<?php
namespace vettich\sp3\devform;

/**
* useful functions
*
* @author Oleg Lenshin (Vettich)
*/
class Module
{
	const MODULE_ID = 'vettich.sp3';

	private static $messPrefix = '';
	private static $messStack = [];

	public $_handlers = [];
	public function __construct($args=[])
	{
		$this->_handlers = self::getOnHandler($args);
	}

	/**
	* create object from $params
	* @param array $params - keys: class - php class (full name with namespace or just class name)
	*						[namaspace - base namespace for php class]
	* @return object
	*/
	public static function createObject($params)
	{
		$namespaces = (array) $params['namespace'];
		$namespaces[] = '';

		$clName = (string) $params['class'];

		unset($params['namespace']);
		unset($params['class']);

		foreach ((array)(array)$namespaces as $namespace) {
			$cl = $clName;
			if (!empty($namespace)) {
				$cl = $namespace.'\\'.$clName;
			}
			if (class_exists($cl)) {
				return new $cl($params);
			}
		}

		return null;
	}

	/**
	* replace #macros# to GetMessage(macros) in $text
	* @param string $text
	* @return string
	*/
	public static function mess($text)
	{
		$len = strlen($text);
		$start = -1;
		$search = [];
		for ($i=0; $i < $len; ++$i) {
			if ($text[$i] == '#') {
				if ($start >= 0) {
					$search[] = substr($text, $start, $i - $start + 1);
					$start = -1;
				} else {
					$start = $i;
				}
			}
		}
		foreach ((array)$search as $macros) {
			$mess = substr($macros, 1, -1);
			if ($mess[0] == '.') {
				$mess = self::$messPrefix.substr($mess, 1);
			}
			$mess = GetMessage($mess);
			if (!empty($mess)) {
				$text = str_replace($macros, $mess, $text);
			}
		}
		return $text;
	}

	public function pushMessPrefix($prefix)
	{
		self::$messStack[] = self::$messPrefix;
		self::$messPrefix = $prefix;
	}

	public static function popMessPrefix()
	{
		if (empty(self::$messStack)) {
			return;
		}
		self::$messPrefix = array_pop(self::$messStack);
	}

	/**
	* explode $string with help $delimiter
	* example - "text:This is name:param1=value1:param2=[value2:key1=value3:key2=[value4:value5]]"
	* array(
	*     [0] => text,
	*     [1] => This is name,
	*     [param1] => value1,
	*     [param2] => array(
	*         [0] => value2,
	*         [key1] => value3,
	*         [key2] => array(
	*             [0] => value4,
	*             [1] => value5,
	*         )
	*     )
	* )
	* @param string|char $delimiter
	* @param string $string
	* @return array
	*/
	public static function explode($delimiter, $string, $eq='=', $ar=['[', ']'], &$i=0)
	{
		$arParam = [];
		$len = strlen($string);
		$sParam = '';
		$aParam = [];
		$sKey = '';
		$prevChar = '';
		for ($i=0; $i<$len; $i++) {
			$ch = $string[$i];
			if ($ch == $delimiter) {
				if ($prevChar != '\\') {
					if (empty($sKey)) {
						$arParam[] = trim($sParam);
					} else {
						$arParam[trim($sKey)] = trim($sParam);
					}
					$sParam = '';
				} else {
					$sParam = substr($sParam, 0, -1).$ch;
				}
			} elseif ($ch == $eq) {
				if ($prevChar != '\\') {
					if (!empty($sParam)) {
						$sKey = $sParam;
						$sParam = '';
					}
				} else {
					$sParam = substr($sParam, 0, -1).$ch;
				}
			} elseif ($ch == $ar[0]) {
				if ($prevChar != '\\') {
					$sParam = self::explode($delimiter, substr($string, $i+1), $eq, $ar, $ii);
					if (empty($sKey)) {
						$arParam[] = $sParam;
					} else {
						$arParam[trim($sKey)] = $sParam;
					}
					$sParam = '';
					$i += $ii+2;
				} else {
					$sParam = substr($sParam, 0, -1).$ch;
				}
			} elseif ($ch == $ar[1]) {
				if ($prevChar != '\\') {
					if (empty($sKey)) {
						$arParam[] = trim($sParam);
					} else {
						$arParam[trim($sKey)] = trim($sParam);
					}
					$sParam = '';
					return $arParam;
				} else {
					$sParam = substr($sParam, 0, -1).$ch;
				}
			} else {
				$sParam .= $ch;
			}
			$prevChar = $ch;
		}
		if (!empty($sParam)) {
			if (empty($sKey)) {
				$arParam[] = trim($sParam);
			} else {
				$arParam[trim($sKey)] = trim($sParam);
			}
		}

		return $arParam;
	}

	/**
	* изменить ключ массива
	* @param integer|string $key - old key of array
	* @param integer|string $newKey - new key of array
	* @param array $arr - array
	* @param boolean $rewrite - rewrite key if one exists
	*/
	public function changeKey($key, $newKey, &$arr, $rewrite=true)
	{
		if ($key !== $newKey
			&& ($rewrite || !array_key_exists($newKey, $arr))) {
			$arr[$newKey] = $arr[$key];
			unset($arr[$key]);
			return true;
		}
		return false;
	}

	/**
	* convert encoding $data to current
	* @param array|string $data
	* @return array|string|mixed
	*/
	public static function convertEncodingToCurrent($data)
	{
		if (is_array($data)) {
			foreach ((array)$data as $key => $value) {
				$newKey = \Bitrix\Main\Text\Encoding::convertEncodingToCurrent($key);
				$newValue = self::convertEncodingToCurrent($value);
				$data[$newKey] = $newValue;
				if ($newKey != $key) {
					unset($data[$key]);
				}
			}
			return $data;
		} elseif (is_string($data)) {
			if ($data == '') {
				return '';
			}
			return \Bitrix\Main\Text\Encoding::convertEncodingToCurrent($data);
		}
		return $data;
	}


	/**
	* parse 'on handler' from config $args
	* @param array $args
	* @return array handlers
	*/
	public static function getOnHandler($args)
	{
		$arResult = [];
		foreach ((array)$args as $key => $arg) {
			if (strpos($key, 'on ') === 0) {
				$arResult[substr($key, 3)] = $arg;
			}
		}
		return $arResult;
	}

	/**
	* call handler function with args
	* @param array $handlers
	* @param string $name
	* @param mixed $arg1...$arg7
	* @return mixed
	*/
	public static function onHandlerStatic($handlers, $name, &$arg1=null, &$arg2=null, &$arg3=null, &$arg4=null, &$arg5=null, &$arg6=null, &$arg7=null)
	{
		if (!isset($handlers[$name])) {
			return null;
		}

		$args = [&$arg1, &$arg2, &$arg3, &$arg4, &$arg5, &$arg6, &$arg7];
		$len = count($args);
		for ($i=count($args)-1; $i >= 0; $i--) {
			if ($args[$i] === null) {
				unset($args[$i]);
			} else {
				break;
			}
		}
		return call_user_func_array($handlers[$name], $args);
	}

	/**
	* non-static
	*/
	public function onHandler($name, &$arg1=null, &$arg2=null, &$arg3=null, &$arg4=null, &$arg5=null, &$arg6=null, &$arg7=null)
	{
		if (empty($this->_handlers[$name])) {
			return null;
		}
		$args = [&$arg1, &$arg2, &$arg3, &$arg4, &$arg5, &$arg6, &$arg7];
		$len = count($args);
		for ($i=count($args)-1; $i >= 0; $i--) {
			if ($args[$i] === null) {
				unset($args[$i]);
			} else {
				break;
			}
		}
		return call_user_func_array($this->_handlers[$name], $args);
	}

	public static function valueFrom($arr, $key, $default=null)
	{
		if (($pos = strpos($key, '[')) !== false) {
			$_key = substr($key, 0, $pos);
			$_postkey = substr($key, $pos);
			$_postkey = str_replace(['[', ']'], ['["', '"]'], $_postkey);
			eval('$ret = isset($arr["'.$_key.'"]'.$_postkey.') ? $arr["'.$_key.'"]'.$_postkey.' : $default;');
			return $ret;
		} else {
			return isset($arr[$key]) ? $arr[$key] : $default;
		}
	}

	public static function valueTo(&$arr, $key, $value)
	{
		if (($pos = strpos($key, '[')) !== false) {
			$prekey = substr($key, 0, $pos);
			$postkey = substr($key, $pos);
			$postkey = str_replace(['[', ']'], ['["', '"]'], $postkey);
			eval('$arr["'.$prekey.'"]'.$postkey.' = $value;');
		} else {
			$arr[$key] = $value;
		}
	}

	public static function strToChain($str)
	{
		$chain = str_replace(['][', ']', '['], ['|', '', '|'], $str);
		$chain = explode('|', $chain);
		return $chain;
	}

	public static function arrayChain($arr, $columns)
	{
		if (empty($columns)) {
			return $arr;
		}
		if (!is_array($arr)) {
			return null;
		}
		if (array_key_exists($columns[0], $arr)) {
			$key = array_shift($columns);
			return self::arrayChain($arr[$key], $columns);
		}
		return null;
	}

	public static function arrayChainSet(&$arr, $columns, $value)
	{
		if (empty($columns)) {
			return null;
		}
		if (!is_array($arr)) {
			$arr = [];
		}
		$key = array_shift($columns);
		if (!array_key_exists($key, $arr)) {
			$arr[$key] = '';
		}
		$res = self::arrayChainSet($arr[$key], $columns, $value);
		if ($res === true) {
			return true;
		}
		if ($res == null) {
			$arr[$key] = $value;
			return true;
		}
		return null;
	}

	public static function getCurlFilename($fn)
	{
		if (version_compare(PHP_VERSION, '5.6.0', '<')) {
			return '@'.$fn;
		}
		return new \CURLFile($fn);
	}

	public static function curlPost($url, $data, $on=null)
	{
		$result = false;
		if (function_exists('curl_init') && $curl = curl_init()) {
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
			if (is_callable($on)) {
				call_user_func_array($on, [&$curl, $url, $data]);
			}
			$result = curl_exec($curl);
			curl_close($curl);
		}
		return $result;
	}

	public static function curlGet($url, $on=null)
	{
		$result = false;
		if (function_exists('curl_init') && $curl = curl_init()) {
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
			if (is_callable($on)) {
				call_user_func_array($on, [&$curl, $url]);
			}
			$result = curl_exec($curl);
			curl_close($curl);
		}
		return $result;
	}

	public static function GetOptionString($name, $def='')
	{
		return \COption::GetOptionString(static::MODULE_ID, $name, $def);
	}

	public static function SetOptionString($name, $val)
	{
		\COption::SetOptionString(static::MODULE_ID, $name, $val);
	}

	public static function substr($str, $start, $length=false, $encoding='UTF-8', $wordwrap=true, $postfix='...')
	{
		$orig_len = self::mb_strlen($str);
		if ($orig_len <= $length) {
			return $str;
		}
		$postfix_len = self::mb_strlen($postfix);
		if ($length===false) {
			$length = $orig_len;
		}
		if ($postfix && $length>$postfix_len) {
			$length -= $postfix_len;
		}
		$res = self::mb_substr($str, $start, $length, $encoding);
		if ($wordwrap) {
			$res = self::mb_substr($res, 0, self::mb_strripos($res, ' ', 0, $encoding), $encoding);
		}
		if ($postfix && $length>$postfix_len && self::mb_strlen($res) < $orig_len) {
			$res .= $postfix;
		}
		return $res;
	}

	public static function mb_substr($str, $start, $len=null, $encoding=null)
	{
		if (function_exists('mb_substr')) {
			return mb_substr($str, $start, $len, $encoding ?: 'UTF-8');
		}
		return substr($str, $start, $len);
	}

	public static function mb_strripos($haystack, $needle, $offset=0, $encoding=null)
	{
		if (function_exists('mb_strripos')) {
			return mb_strripos($haystack, $needle, $offset, $encoding ?: 'UTF-8');
		}
		return strripos($haystack, $needle, $offset);
	}

	public static function mb_strlen($str, $encoding=null)
	{
		if (function_exists('mb_strlen')) {
			return mb_strlen($str, $encoding ?: 'UTF-8');
		}
		return strlen($str);
	}

	public static function selfValue($value)
	{
		return str_replace(':', '\:', $value);
	}
}
