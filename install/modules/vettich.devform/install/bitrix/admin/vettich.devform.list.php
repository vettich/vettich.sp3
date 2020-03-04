<?
$file = substr(basename($_SERVER['SCRIPT_NAME']), 16/*strlen(vettich.devform.)*/);
$dir = $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/vettich.devform/admin/';
if(!file_exists($dir.$file)) {
	$dir = $_SERVER['DOCUMENT_ROOT'].'/local/modules/vettich.devform/admin/';
}
require $dir.$file;
