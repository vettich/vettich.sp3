<?php

\vettich\devform\Module::popMessPrefix();

if ($_GET['ajax'] != 'Y') {
	require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
}
