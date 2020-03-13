<?php

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
ini_set('display_errors', false);

CModule::IncludeModule('vettich.sp3');

use vettich\sp3\Module;
use vettich\sp3\Api;
use vettich\sp3\TemplateHelpers;

function _result($data)
{
	echo json_encode($data);
	exit;
}

switch ($_GET['method']) {
	case 'login':
		$username = $_GET['username'];
		$password = $_GET['password'];
		$res = Api::login($username, $password);
		_result($res);
		break;

	case 'signup':
		$username = $_GET['username'];
		$password = $_GET['password'];
		$res = Api::signup($username, $password);
		_result($res);
		break;

	case 'forgotPassword':
		$username = $_GET['username'];
		$callback_url = $_GET['callback_url'];
		$res = Api::forgotPassword($username, $callback_url);
		_result($res);
		break;

	case 'resetPassword':
		$token = $_GET['token'];
		$password = $_GET['password'];
		$res = Api::resetPassword($token, $password);
		_result($res);
		break;

	case 'logout':
		$res = Api::logout();
		_result($res);
		break;

	case 'getConnectUrl':
		$type = $_GET['type'];
		$callback = $_GET['callback'];
		$res = Api::connectUrl($type, $callback);
		_result($res);
		break;

	case 'connect':
		_result(Api::connect($_GET['type'], $_GET['fields']));
		break;

	case 'connectInsta':
		_result(Api::connectInsta($_GET['username'], $_GET['password'], $_GET['proxy'], $_GET['code']));
		break;

	case 'listTemplates':
		$res = TemplateHelpers::listTemplates($_GET['IBLOCK_ID']);
		$res = Module::convertToUtf8($res);
		_result($res);
		break;

	case 'publishWithTemplate':
		$arFilter = ['IBLOCK_ID' => $_GET['IBLOCK_ID']];
		$arFilterLogic = ['LOGIC' => 'OR'];
		if (!empty($_GET['ELEMS'])) {
			$arFilterLogic[] = ['ID' => $_GET['ELEMS']];
		}
		if (!empty($_GET['SECTIONS'])) {
			$arFilterLogic[] = [
				'SECTION_ID' => $_GET['SECTIONS'],
				'INCLUDE_SUBSECTIONS' => true
			];
		}
		if (count($arFilterLogic) == 1) {
			_result(['error' => ['msg' => 'ELEMS and SECTIONS are empty']]);
			break;
		}
		$arFilter[] = $arFilterLogic;
		Module::log([$arFilter, $_GET['TEMPLATES']]);
		$res = TemplateHelpers::publishWithTemplate($arFilter, $_GET['TEMPLATES']);
		_result($res);
		break;

	default:
		_result(['error' => ['msg' => 'method not found']]);
}
