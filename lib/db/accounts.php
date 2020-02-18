<?php
namespace vettich\sp3\db;

use vettich\sp3\Module;
use vettich\sp3\Api;

class Accounts extends \vettich\devform\data\ArrayList
{
	private static $_accs = [];

	public function __construct($args = [])
	{
		parent::__construct($args);
		if (isset($args['filter'])) {
			$this->filter = $args['filter'];
		}
	}

	public function getList()
	{
		$res = Api::accountsList($this->filter);
		if (!empty($res['error'])) {
			return [];
		}
		$res = Module::convertToSiteCharset($res);
		$accounts = $res['response']['accounts'];
		self::$_accs = $accounts;
		return $accounts;
	}

	public function getListType()
	{
		$res = $this->getList();
		$list = [];
		foreach ($res as $acc) {
			if (empty($list[$acc['type']])) {
				$list[$acc['type']] = [];
			}
			$list[$acc['type']][] = $acc;
		}
		return $list;
	}

	public static function getById($id)
	{
		if (empty($id)) {
			return [];
		}
		if (isset(self::$_accs[$id])) {
			return self::$_accs[$id];
		}
		$r = Api::getAccount($id);
		if (empty($r['error'])) {
			$r = Module::convertToSiteCharset($r);
			self::$_accs[$id] = $r['response'];
			return $r['response'];
		}
		return [];
	}

	public static function getByIds($ids)
	{
		if (empty($ids)) {
			return [];
		}
		$res = [];
		foreach ($ids as $id) {
			if (isset(self::$_accs[$id])) {
				$res[$id] = self::$_accs[$id];
				continue;
			}
			$r = Api::getAccount($id);
			if (empty($r['error'])) {
				$r = Module::convertToSiteCharset($r);
				$res[$id] = $r['response'];
				self::$_accs[$id] = $r['response'];
			}
		}
		return $res;
	}

	public function delete($name, $value)
	{
		Api::deleteAccount($id=$value);
	}
}
