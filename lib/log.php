<?php
namespace vettich\sp3;

class Log
{
	const DEBUG   = 'DEBUG';
	const INFO    = 'INFO';
	const WARNING = 'WARNING';
	const ERROR   = 'ERROR';

	const LOCAL_FILE = 'log.txt';

	public static function debug($msg, $options=[])
	{
		$options['rm_trace'] = ($options['rm_trace'] ?: 0) + 1;
		self::log(self::DEBUG, $msg, $options);
	}

	public static function info($msg, $options=[])
	{
		$options['rm_trace'] = ($options['rm_trace'] ?: 0) + 1;
		self::log(self::INFO, $msg, $options);
	}

	public static function warning($msg, $options=[])
	{
		$options['rm_trace'] = ($options['rm_trace'] ?: 0) + 1;
		self::log(self::WARNING, $msg, $options);
	}

	public static function error($msg, $options=[])
	{
		$options['rm_trace'] = ($options['rm_trace'] ?: 0) + 1;
		self::log(self::ERROR, $msg, $options);
	}

	public static function log($level, $msg, $options=[])
	{
		if (Config::get('log') != true && Config::get('remote_log') != true) {
			return;
		}
		$options['rm_trace'] = ($options['rm_trace'] ?: 0) + 1;
		$options['trace'] = self::traceFormatted(($options['rm_trace'] ?: 0) + 1);
		if (Config::get('log') == true) {
			self::localWrite($level, $msg, $options);
		}
		if (Config::get('remote_log') == true && self::isRemote($level)) {
			self::remoteWrite($level, $msg, $options);
		}
	}

	private static function isRemote($level)
	{
		return $level == self::INFO ||
			$level == self::WARNING ||
			$level == self::ERROR;
	}

	private static function localWrite($level, $msg, $options=[])
	{
		$text = var_export($msg, true);
		$date = date('Y/m/d H:i:s');
		$trace = $options['trace'];
		$text = "[$level:$date] $trace:\n$text\n";
		error_log($text, 3, VETTICH_SP3_DIR.'/'.self::LOCAL_FILE);
	}

	private static function remoteWrite($level, $msg, $options=[])
	{
		$data = [
			'level' => $level,
			'trace' => $options['trace'],
			'msg' => var_export($msg, true),
			'user_id' => Config::get('user_id'),
		];
		Api::sendLog($data);
	}

	// return format like: <file path>:<line number>:<function name>
	// example:
	//     api.php:23:set > config.php:55:write
	private static function traceFormatted($n_remove)
	{
		$trace = debug_backtrace(2);
		for ($i = 0; $i < $n_remove+1; $i++) {
			array_shift($trace);
		}
		$str = [];
		foreach ($trace as $t) {
			$file = str_replace(VETTICH_SP3_DIR, '', $t['file']);
			$file = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);
			$file = substr($file, 1);
			$str[] = "$file:$t[line]:$t[function]";
		}
		$str = array_reverse($str);
		$str = implode(" > ", $str);
		return $str;
	}
}
