<?php
namespace vettich\sp3;

class Config
{
	private $_data            = [];
	private $_path            = "";
	private static $_instance = null;

	public function __construct()
	{
		if (file_exists($p = VETTICH_SP3_DIR.'/local_config.json')) {
			$this->_path = $p;
		} else {
			$this->_path = VETTICH_SP3_DIR.'/config.json';
		}
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
		$data = file_get_contents($this->_path);
		$conf = json_decode($data, true);
		if (!empty($conf)) {
			$this->_data = $conf;
		}
	}

	private function saveConfig()
	{
		$data = json_encode($this->_data, JSON_PRETTY_PRINT);
		file_put_contents($this->_path, $data);
	}

	public static function setConfig($data)
	{
		if (empty($data) || !is_array($data)) {
			return false;
		}
		$i        = self::instance();
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
		$i              = self::instance();
		$i->_data[$key] = $value;
		$i->saveConfig();
	}

	public static function get($key)
	{
		$i     = self::instance();
		$value = $i->_data[$key];
		return $value;
	}
}
