<?php

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('vettich.sp3');

use vettich\sp3\Module;

function _result($data)
{
	//$data = Module::convert($data);
	echo json_encode($data);
	exit;
}

switch ($_GET['method']) {
	case 'login':
		$username = $_GET['username'];
		$password = $_GET['password'];
		$res = Module::api()->login($username, $password);
		_result($res);
		break;

	case 'signup':
		$username = $_GET['username'];
		$password = $_GET['password'];
		$res = Module::api()->signup($username, $password);
		_result($res);
		break;

	case 'logout':
		$res = Module::api()->logout();
		_result($res);
		break;

	case 'vkLogin':
		$type = 'VK';
		$callback = $_GET['callback'];
		$res = Module::api()->connectUrl($type, $callback);
		_result($res);
		break;

	default:
		_result(['success' => false, 'error' => 'method not found']);
}
