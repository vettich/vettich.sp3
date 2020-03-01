<?php
require(__DIR__.'/../include/prolog_authorized_page.php');
use vettich\sp3\Module;

$APPLICATION->SetTitle(Module::m('PAYMENT_SUCCESS_TITLE'));
?>

	<a href="/bitrix/admin/vettich.sp3.user.php"><?=Module::m('GOTO_USER_PAGE')?></a>

<?php
require(__DIR__.'/../include/epilog_authorized_page.php');
