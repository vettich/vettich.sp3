<?php

/**
 * Предупреждение в админке при несоответствии окружения (PHP, curl, json).
 * Общая логика с install/requirements_check.php (установка / updater.php).
 */
require_once dirname(__DIR__).'/install/requirements_check.php';

$vettichSp3RequirementsErr = vettich_sp3_requirements_error_message();
if ($vettichSp3RequirementsErr !== '') {
	require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
	?>
	<div class="adm-info-message" style="display:block">
		<?=htmlspecialcharsbx($vettichSp3RequirementsErr)?>
	</div>
	<?php
	require_once $_SERVER['DOCUMENT_ROOT'].BX_ROOT.'/modules/main/include/epilog_admin.php';
}
