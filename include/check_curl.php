<?php

use vettich\sp3\Module;

if (!in_array('curl', get_loaded_extensions())) {
	require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
	?>
	<div class="adm-info-message" style="display:block">
		<?=Module::m('CURL_NOT_FOUND')?>
	</div>
	<?php
	require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
}

