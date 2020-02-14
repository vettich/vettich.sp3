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
		$this->InstallDevform();
		if ($this->InstallDB()
			&& $this->InstallFiles()
			&& $this->InstallEvents()) {
			RegisterModule($this->MODULE_ID);
			$APPLICATION->IncludeAdminFile(GetMessage('VETTICH_SP3_INSTALL_TITLE'), $this->MODULE_ROOT_DIR.'/install/step.php');
			return true;
		}
		return false;
	}

	public function DoUninstall()
	{
		global $DOCUMENT_ROOT, $APPLICATION, $step;
		$step = IntVal($step);
		if ($step<2) {
			$APPLICATION->IncludeAdminFile(GetMessage('VETTICH_SP3_UNINSTALL_TITLE'), $this->MODULE_ROOT_DIR.'/install/unstep.php');
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
		$lib = $this->MODULE_ROOT_DIR.'/lib';
		include $lib.'/db/ormbase.php';
		include $lib.'/db/postiblock.php';
		include $lib.'/db/template.php';
		if (!vettich\sp3\db\PostIBlockTable::createTable()) {
			return false;
		}
		if (!vettich\sp3\db\TemplateTable::createTable()) {
			return false;
		}

		COption::SetOptionString($this->MODULE_ID, 'is_enable', 'Y');
		return true;
	}

	public function UnInstallDB($arParams = [])
	{
		COption::RemoveOption($this->MODULE_ID);
		if (!$arParams['savedata'] && \CModule::IncludeModule($this->MODULE_ID)) {
			if (!vettich\sp3\db\TemplateTable::dropTable()) {
				return false;
			}
			if (!vettich\sp3\db\PostIBlockTable::dropTable()) {
				return false;
			}
		}
		return true;
	}

	public function InstallEvents()
	{
		RegisterModuleDependences('main', 'OnBeforeProlog', 'vettich.sp3', '\vettich\sp3\Events', 'beforePrologHandler');
		RegisterModuleDependences('main', 'OnAdminListDisplay', 'vettich.sp3', '\vettich\sp3\Events', 'adminListDisplayHandler');
		RegisterModuleDependences('iblock', 'OnAfterIblockElementAdd', 'vettich.sp3', '\vettich\sp3\Events', 'afterIblockElementAddHandler');
		RegisterModuleDependences('iblock', 'OnAfterIBlockElementUpdate', 'vettich.sp3', '\vettich\sp3\Events', 'afterIBlockElementUpdateHandler');
		RegisterModuleDependences('iblock', 'OnAfterIBlockElementDelete', 'vettich.sp3', '\vettich\sp3\Events', 'afterIBlockElementDeleteHandler');
		return true;
	}

	public function UnInstallEvents()
	{
		UnRegisterModuleDependences('main', 'OnBeforeProlog', 'vettich.sp3', '\vettich\sp3\Events', 'beforePrologHandler');
		UnRegisterModuleDependences('main', 'OnAdminListDisplay', 'vettich.sp3', '\vettich\sp3\Events', 'adminListDisplayHandler');
		UnRegisterModuleDependences('iblock', 'OnAfterIblockElementAdd', 'vettich.sp3', '\vettich\sp3\Events', 'afterIblockElementAddHandler');
		UnRegisterModuleDependences('iblock', 'OnAfterIBlockElementUpdate', 'vettich.sp3', '\vettich\sp3\Events', 'afterIBlockElementUpdateHandler');
		UnRegisterModuleDependences('iblock', 'OnAfterIBlockElementDelete', 'vettich.sp3', '\vettich\sp3\Events', 'afterIBlockElementDeleteHandler');
		return true;
	}

	public function InstallFiles()
	{
		CopyDirFiles($this->MODULE_ROOT_DIR.'/install/bitrix', $_SERVER['DOCUMENT_ROOT'].'/bitrix', true, true);
		return true;
	}

	public function UnInstallFiles()
	{
		$thisPath = $this->MODULE_ROOT_DIR.'/install/bitrix';
		$installedPath = $_SERVER['DOCUMENT_ROOT'].'/bitrix';
		DeleteDirFiles($thisPath.'/admin', $installedPath.'/admin');
		DeleteDirFiles($thisPath.'/js/vettich.sp3', $installedPath.'/js/vettich.sp3');
		DeleteDirFiles($thisPath.'/images/vettich.sp3', $installedPath.'/images/vettich.sp3');
		DeleteDirFiles($thisPath.'/themes/.default', $installedPath.'/themes/.default');
		DeleteDirFiles($thisPath.'/themes/.default/icons/vettich.sp3', $installedPath.'/themes/.default/icons/vettich.sp3');
		return true;
	}

	public function InstallDevform()
	{
		$vdfInstalledPath = '/bitrix/modules/vettich.devform';
		$vdfVersionFile = $_SERVER['DOCUMENT_ROOT'].$vdfInstalledPath.'/install/version.php';
		$needCopyFiles = true;
		if (file_exists($vdfVersionFile)) {
			$needCopyFiles = false;
			include($vdfVersionFile);
			$v1 = $arModuleVersion;
			include(__DIR__.'/modules/vettich.devform/install/version.php');
			$v2 = $arModuleVersion;
			if (!CheckVersion($v1['VERSION'], $v2['VERSION'])) { // $v1 < $v2
				$needCopyFiles = true;
			}
		}
		if ($needCopyFiles) {
			$dirFrom = $this->MODULE_ROOT_DIR.'/install/modules';
			$dirTo = $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules';
			CopyDirFiles($dirFrom, $dirTo, true, true);
		}
		if (CModule::IncludeModule('vettich.devform')) {
			if ($needCopyFiles) {
				$dirFrom = $this->MODULE_ROOT_DIR.'/install/modules/vettich.devform/install/bitrix';
				$dirTo = $_SERVER['DOCUMENT_ROOT'].'/bitrix';
				CopyDirFiles($dirFrom, $dirTo, true, true);
			}
			return;
		}
		include $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/vettich.devform/install/index.php';
		if (class_exists('vettich_devform')) {
			$cl = new vettich_devform();
			if (!$cl->IsInstalled()) {
				$cl->DoInstall();
			}
		}
	}
}
