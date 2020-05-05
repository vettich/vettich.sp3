<?php
namespace vettich\sp3\db;

use vettich\sp3\Module;
use vettich\sp3\Api;

class Accounts extends \vettich\sp3\devform\data\ArrayList
{
	private static $_accs = [];

	public function __construct($args = [])
	{
		parent::__construct($args);
		if (isset($args['filter'])) {
			$this->filter = $args['filter'];
		}
	}

	public function getList($params=[])
	{
		return self::getAccs($this->filter);
	}

	private static function getAccs($filter=null)
	{
		if (empty(self::$_accs)) {
			$res = Api::accountsList($filter);
			if (!empty($res['error'])) {
				return [];
			}
			$res = Module::convertToSiteCharset($res);
			$accounts = $res['response']['accounts'];
			foreach ($accounts as $acc) {
				self::$_accs[$acc['id']] = $acc;
			}
		}
		return self::$_accs;
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
		$accs = (array)self::getAccs();
		if (isset($accs[$id])) {
			return $accs[$id];
		}
		return [];
	}

	public static function getByIds($ids)
	{
		if (empty($ids)) {
			return [];
		}
		$res = [];
		$accs = self::getAccs();
		if (empty($accs)) {
			return [];
		}
		foreach ($ids as $id) {
			if (isset($accs[$id])) {
				$res[$id] = $accs[$id];
			}
		}
		return $res;
	}

	public function delete($name, $value)
	{
		Api::deleteAccount($id=$value);
	}
}
