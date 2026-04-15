<?php
/**
 * После prolog_admin_before: доступ к разделам модуля не ниже «чтение».
 *
 * @global CMain $APPLICATION
 */
global $APPLICATION;
if ($APPLICATION->GetGroupRight('vettich.sp3') < 'R') {
	$APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}
