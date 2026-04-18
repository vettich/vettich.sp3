<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';
ini_set('display_errors', false);

CModule::IncludeModule('vettich.sp3');

use vettich\sp3\Api;
use vettich\sp3\Config;
use vettich\sp3\Events;
use vettich\sp3\LocalQueue;
use vettich\sp3\Module;
use vettich\sp3\TemplateHelpers;

function _result($data)
{
	echo json_encode($data);
	exit;
}

function ajax_deny($msg, $code = 'FORBIDDEN')
{
	_result(['error' => ['msg' => $msg, 'code' => $code]]);
}

function require_bitrix_sessid()
{
	if (!check_bitrix_sessid()) {
		ajax_deny('sessid', 'SESSID');
	}
}

function require_bitrix_user()
{
	global $USER;
	if (!$USER->IsAuthorized()) {
		ajax_deny('bitrix auth required', 'BITRIX_AUTH');
	}
}

function require_module_read()
{
	require_bitrix_sessid();
	require_bitrix_user();
	if (!Module::hasGroupRead()) {
		ajax_deny('module read access denied', 'MODULE_ACCESS');
	}
}

function require_module_write()
{
	require_bitrix_sessid();
	require_bitrix_user();
	if (!Module::hasGroupWrite()) {
		ajax_deny('module write access denied', 'MODULE_ACCESS');
	}
}

function require_parrotposter_hook()
{
	if (!Api::checkHookToken()) {
		_result(['error' => ['msg' => 'unauthorized', 'code' => 'REMOTE_AUTH']]);
	}
}

$method = $_GET['method'] ?? $_POST['method'] ?? '';

switch ($method) {
	case 'auth':
		require_module_write();
		Config::setToken(trim((string)($_POST['token'] ?? '')));
		$validateTokenRes = Api::validateToken();
		header('Content-Type: text/plain; charset=UTF-8');
		echo $validateTokenRes['response'] ? 'ok' : 'validate token error';
		exit;

	case 'logout':
		require_module_write();
		$res = Api::logout();
		if (empty($res['error'])) {
			Api::deleteCron();
		}
		_result($res);
		break;

	case 'listTemplates':
		require_module_read();
		$res = TemplateHelpers::listTemplates($_GET['IBLOCK_ID']);
		$res = Module::convertToUtf8($res);
		_result($res);
		break;

	case 'publishWithTemplate':
		require_module_write();
		$arFilter      = ['IBLOCK_ID' => $_GET['IBLOCK_ID']];
		$arFilterLogic = ['LOGIC' => 'OR'];
		if (!empty($_GET['ELEMS'])) {
			$arFilterLogic[] = ['ID' => $_GET['ELEMS']];
		}
		if (!empty($_GET['SECTIONS'])) {
			$arFilterLogic[] = [
				'SECTION_ID'          => $_GET['SECTIONS'],
				'INCLUDE_SUBSECTIONS' => true,
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
		require_parrotposter_hook();
		_result(TemplateHelpers::unload());

	// deprecated from v3.1.2
	case 'postFromQueue':
		require_parrotposter_hook();
		$arFields = [
			'ID'        => (int)$_GET['ID'],
			'IBLOCK_ID' => (int)$_GET['IBLOCK_ID'],
		];
		$res      = TemplateHelpers::publish($arFields, ['event' => Events::POP_ADD]);
		_result($res);
		break;

	case 'processLocalQueue':
		require_parrotposter_hook();
		_result(LocalQueue::handleHttpProcess());
		break;

	default:
		_result(['error' => ['msg' => 'method not found']]);
}
