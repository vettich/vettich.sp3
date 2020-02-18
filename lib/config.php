<?php
namespace vettich\sp3;

class Config
{
	const CONFIG_FILENAME = '/config.json';
	private $_data = [];
	private static $_instance = null;

	public function __construct()
	{
		$this->readConfig();
	}

	public static function instance()
	{
		if (self::$_instance == null) {
			self::$_instance = new Config();
		}
		return self::$_instance;
	}

	private function readConfig()
	{
		$data = file_get_contents(VETTICH_SP3_DIR.self::CONFIG_FILENAME);
		$conf = json_decode($data, true);
		if (!empty($conf)) {
			$this->_data = $conf;
		}
	}

	private function saveConfig()
	{
		$data = json_encode($this->_data, JSON_PRETTY_PRINT);
		file_put_contents(VETTICH_SP3_DIR.self::CONFIG_FILENAME, $data);
	}

	public static function setConfig($data)
	{
		if (empty($data) || !is_array($data)) {
			return false;
		}
		$i = self::instance();
		$i->_data = array_merge($i->_data, $data);
		$i->saveConfig();
	}

	public static function getConfig()
	{
		$i = self::instance();
		return $i->_data;
	}

	public static function set($key, $value)
	{
		$i = self::instance();
		$i->_data[$key] = $value;
		$i->saveConfig();
	}

	public static function get($key)
	{
		$i = self::instance();
		$value = $i->_data[$key];
		return $value;
	}
}
