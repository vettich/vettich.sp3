<?php

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
ini_set('display_errors', false);

CModule::IncludeModule('vettich.sp3');

use vettich\sp3\Module;
use vettich\sp3\Api;
use vettich\sp3\TemplateHelpers;
use vettich\sp3\Events;

function _result($data)
{
	// header('Content-Type: application/json');
	echo json_encode($data);
	exit;
}

switch ($_GET['method']) {
	case 'login':
		$res = Api::login($_GET['username'], $_GET['password']);
		if (empty($res['error'])) {
			Api::createCron();
		}
		_result($res);
		break;

	case 'signup':
		$res = Api::signup($_GET['username'], $_GET['password']);
		if (empty($res['error'])) {
			Api::createCron();
		}
		_result($res);
		break;

	case 'forgotPassword':
		$res = Api::forgotPassword($_GET['username'], $_GET['callback_url']);
		_result($res);
		break;

	case 'resetPassword':
		$res = Api::resetPassword($_GET['token'], $_GET['password']);
		_result($res);
		break;

	case 'logout':
		$res = Api::logout();
		if (empty($res['error'])) {
			Api::deleteCron();
		}
		_result($res);
		break;

	case 'getConnectUrl':
		$res = Api::connectUrl($_GET['type'], $_GET['callback']);
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
		$arFilter      = ['IBLOCK_ID' => $_GET['IBLOCK_ID']];
		$arFilterLogic = ['LOGIC' => 'OR'];
		if (!empty($_GET['ELEMS'])) {
			$arFilterLogic[] = ['ID' => $_GET['ELEMS']];
		}
		if (!empty($_GET['SECTIONS'])) {
			$arFilterLogic[] = [
				'SECTION_ID'          => $_GET['SECTIONS'],
				'INCLUDE_SUBSECTIONS' => true
			];
		}
		if (count($arFilterLogic) == 1) {
			_result(['error' => ['msg' => 'ELEMS and SECTIONS are empty']]);
			break;
		}
		$arFilter[] = $arFilterLogic;
		$res        = TemplateHelpers::publishWithTemplate($arFilter, $_GET['TEMPLATES']);
		_result($res);
		break;

	case 'unload':
		_result(TemplateHelpers::unload());

	case 'postFromQueue':
		if ($_GET['token'] !== Api::token()) {
			_result(['error' => ['msg' => 'unauthorized']]);
			break;
		}
		$arFields = ['ID' => $_GET['ID'], 'IBLOCK_ID' => $_GET['IBLOCK_ID']];
		$res = TemplateHelpers::publish($arFields, ['event' => Events::POP_ADD]);
		_result($res);
		break;

		// no break
	default:
		_result(['error' => ['msg' => 'method not found']]);
}
