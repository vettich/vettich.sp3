<?php
namespace vettich\sp3\db;

use vettich\sp3\Module;

class Accounts extends \vettich\devform\data\ArrayList
{
	public function __construct($args = [])
	{
		parent::__construct($args);
	}

	public function getList()
	{
		$res = Module::api()->accountsList();
		$res = Module::convertToSiteCharset($res);
		$accounts = $res['accounts'];
		return $accounts;
	}

	public function delete($name, $value)
	{
		Module::api()->deleteAccount($id=$value);
	}
}
