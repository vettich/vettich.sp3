<?
IncludeModuleLangFile(__FILE__);

class vettich_devform extends CModule{
	var $MODULE_ID = 'vettich.devform';
	var $MODULE_VERSION;
	var $MODULE_VERSION_DATE;
	var $MODULE_NAME;
	var $MODULE_DESCRIPTION;
	var $MODULE_GROUP_RIGHTS = 'Y';
	var $MODULE_ROOT_DIR = '';

	function vettich_devform(){
		$arModuleVersion = array();

		include(__DIR__.'/version.php');

		if(is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion)){
			$this->MODULE_VERSION = $arModuleVersion['VERSION'];
			$this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
		}

		$this->MODULE_ROOT_DIR = dirname(__DIR__);
		$this->MODULE_NAME = GetMessage('vettich.devform_MODULE_NAME');
		$this->MODULE_DESCRIPTION = GetMessage('vettich.devform_MODULE_DESCRIPTION');
		$this->PARTNER_NAME = GetMessage('vettich.devform_PARTNER_NAME'); 
		$this->PARTNER_URI = GetMessage('vettich.devform_PARTNER_URI');
	}

	function DoInstall(){
		global $DOCUMENT_ROOT, $APPLICATION, $errors, $ver, $GLOBALS;
		$GLOBALS["CACHE_MANAGER"]->CleanAll();

		if($this->InstallDB())
		{
			if($this->InstallFiles() && $this->InstallEvents())
			{
				RegisterModule($this->MODULE_ID);
				return true;
			}
			else
				$APPLICATION->IncludeAdminFile(GetMessage('VPOSTING_INSTALL_TITLE'), $DOCUMENT_ROOT.'/bitrix/modules/'.$this->MODULE_ID.'/install/install_error_files.php');
		}
		else
			$APPLICATION->IncludeAdminFile(GetMessage('VPOSTING_INSTALL_TITLE'), $DOCUMENT_ROOT.'/bitrix/modules/'.$this->MODULE_ID.'/install/install_error_db.php');
	}

	function DoUninstall(){
		if($this->UnInstallDB(array(
				'savedata' => $_REQUEST['savedata'],
			))
			&& $this->UnInstallFiles()
			&& $this->UnInstallEvents())
		{
			UnRegisterModule($this->MODULE_ID);
			return true;
		}
		return false;
	}

	function InstallDB($arModuleParams = array())
	{
		include $this->MODULE_ROOT_DIR.'/lib/db/option.php';
		vettich\devform\db\optionTable::createTable();
		return true;
	}

	function UnInstallDB($arParams = array())
	{
		include $this->MODULE_ROOT_DIR.'/lib/db/option.php';
		vettich\devform\db\optionTable::dropTable();
		return true;
	}

	function InstallEvents()
	{
		return true;
	}

	function UnInstallEvents()
	{
		return true;
	}

	function InstallFiles()
	{
		CopyDirFiles($this->MODULE_ROOT_DIR."/install/bitrix",$_SERVER["DOCUMENT_ROOT"]."/bitrix", true, true);
		return true;
	}

	function UnInstallFiles()
	{
		DeleteDirFiles($this->MODULE_ROOT_DIR."/install/bitrix/admin", $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin");
		DeleteDirFiles($this->MODULE_ROOT_DIR."/install/bitrix/js/vettich.devform", $_SERVER["DOCUMENT_ROOT"]."/bitrix/js/vettich.devform");
		DeleteDirFiles($this->MODULE_ROOT_DIR."/install/bitrix/css/vettich.devform", $_SERVER["DOCUMENT_ROOT"]."/bitrix/css/vettich.devform");
		DeleteDirFiles($this->MODULE_ROOT_DIR."/install/bitrix/images/vettich.devform", $_SERVER["DOCUMENT_ROOT"]."/bitrix/images/vettich.devform");
		DeleteDirFiles($this->MODULE_ROOT_DIR."/install/bitrix/js", $_SERVER["DOCUMENT_ROOT"]."/bitrix/js");
		DeleteDirFiles($this->MODULE_ROOT_DIR."/install/bitrix/css", $_SERVER["DOCUMENT_ROOT"]."/bitrix/css");
		DeleteDirFiles($this->MODULE_ROOT_DIR."/install/bitrix/images", $_SERVER["DOCUMENT_ROOT"]."/bitrix/images");
		return true;
	}
}
