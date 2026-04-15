<?php
$file = substr(basename($_SERVER['SCRIPT_NAME']), strlen('vettich.sp3.'));
$path = '/modules/vettich.sp3/admin/';
$base = $_SERVER['DOCUMENT_ROOT'].'/bitrix';
if (!file_exists($base.$path.$file)) {
	$base = $_SERVER['DOCUMENT_ROOT'].'/local';
}
require $base.$path.$file;
