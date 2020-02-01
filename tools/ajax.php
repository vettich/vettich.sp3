<?php

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('vettich.sp3');

use vettich\sp3\Module;
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
		$type = 'vk';
		$callback = $_GET['callback'];
		$res = Module::api()->connectUrl($type, $callback);
		_result($res);
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
