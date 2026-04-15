<?php
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Module;
use vettich\sp3\devform\Module as DevModule;
use vettich\sp3\Api;
use vettich\sp3\View;

$APPLICATION->SetTitle(Module::m('ACCOUNTS_LIST_PAGE'));
View::embed_front('accounts');
