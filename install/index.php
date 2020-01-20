<?php
IncludeModuleLangFile(__FILE__);

class vettich_sp3 extends CModule
{
	public $MODULE_ID = 'vettich.sp3';
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME;
	public $MODULE_DESCRIPTION;
	public $PARTNER_NAME;
	public $PARTNER_URI;
	public $MODULE_GROUP_RIGHTS = 'Y';
	public $MODULE_ROOT_DIR = '';

	public function vettich_sp3()
	{
		$arModuleVersion = [];
		include(__DIR__.'/version.php');
		if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion)) {
			$this->MODULE_VERSION = $arModuleVersion['VERSION'];
			$this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
		}
		$this->MODULE_ROOT_DIR = dirname(__DIR__);
		$this->MODULE_NAME = GetMessage('VETTICH_SP3_MODULE_NAME');
		$this->MODULE_DESCRIPTION = GetMessage('VETTICH_SP3_MODULE_DESCRIPTION');
		$this->PARTNER_NAME = GetMessage('VETTICH_SP3_PARTNER_NAME');
		$this->PARTNER_URI = GetMessage('VETTICH_SP3_PARTNER_URI');
	}

	public function DoInstall()
	{
		global $DOCUMENT_ROOT, $APPLICATION, $errors, $ver, $GLOBALS;
		$GLOBALS['CACHE_MANAGER']->CleanAll();
		// $this->InstallDevform();
		if ($this->InstallDB()
			&& $this->InstallFiles()
			&& $this->InstallEvents()) {
			RegisterModule($this->MODULE_ID);
			$APPLICATION->IncludeAdminFile(GetMessage('VETTICH_SP3_INSTALL_TITLE'), $this->MODULE_ROOT_DIR.'/install/step1.php');
			return true;
		}
		return false;
	}

	public function InstallDevform()
	{
		if (CModule::IncludeModule('vettich.devform')) {
			return;
		}
		if (!file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/vettich.devform/install/index.php')) {
			CopyDirFiles($this->MODULE_ROOT_DIR.'/install/bitrix/modules', $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules', true, true);
		}
		include $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/vettich.devform/install/index.php';
		if (class_exists('vettich_devform')) {
			$cl = new vettich_devform();
			if (!$cl->IsInstalled()) {
				$cl->DoInstall();
			}
		}
	}

	public function DoUninstall()
	{
		global $DOCUMENT_ROOT, $APPLICATION, $step;
		$step = IntVal($step);
		if ($step<2) {
			$APPLICATION->IncludeAdminFile(GetMessage('VETTICH_SP3_UNINSTALL_TITLE'), $this->MODULE_ROOT_DIR.'/install/unstep1.php');
		} elseif ($step==2) {
			if ($this->UnInstallDB([
					'savedata' => $_REQUEST['savedata'],
				])
				&& $this->UnInstallFiles()
				&& $this->UnInstallEvents()) {
				UnRegisterModule($this->MODULE_ID);
				return true;
			}
			return false;
		}
	}

	public function InstallDB($arModuleParams = [])
	{
		$def_options = [
			// posts
			'is_enable' => 'Y',
		];
		foreach ($def_options as $k => $v) {
			COption::SetOptionString($this->MODULE_ID, $k, $v);
		}
		return true;
	}

	public function UnInstallDB($arParams = [])
	{
		COption::RemoveOption($this->MODULE_ID);
		return true;
	}

	public function InstallEvents()
	{
		return true;
	}

	public function UnInstallEvents()
	{
		return true;
	}

	public function InstallFiles()
	{
		CopyDirFiles($this->MODULE_ROOT_DIR.'/install/bitrix', $_SERVER['DOCUMENT_ROOT'].'/bitrix', true, true);
		return true;
	}

	public function UnInstallFiles()
	{
		DeleteDirFiles($this->MODULE_ROOT_DIR.'/install/bitrix/admin', $_SERVER['DOCUMENT_ROOT'].'/bitrix/admin');
		return true;
	}
}
