<?php
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Module;
use vettich\sp3\View;

$APPLICATION->SetTitle(Module::m('TARIFFS_PAGE_TITLE'));
View::embed_front('tariffs');

require(__DIR__.'/../include/epilog_authorized_page.php');
