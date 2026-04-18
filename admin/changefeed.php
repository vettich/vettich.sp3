<?php
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);

use vettich\sp3\Module;
use vettich\sp3\View;

$APPLICATION->SetTitle(Module::m('CHANGEFEED_PAGE_TITLE'));
View::embed_front('changefeed');

require(__DIR__.'/../include/epilog_authorized_page.php');
