<?php

$MESS['VETTICH_SP3_SUCCESS']                   = 'Успешно';
$MESS['VETTICH_SP3_REDIRECTING']               = 'Перенаправляем...';
$MESS['VETTICH_SP3_FILL_ALL_FIELDS']           = 'Заполните все поля';
$MESS['VETTICH_SP3_EMAIL_INCORRECT']           = 'Email заполнен некорректно';
$MESS['VETTICH_SP3_PASS_NOT_MATCH']            = 'Пароли не совпадают';
$MESS['VETTICH_SP3_PASS_MIN_LEN']              = 'Минимальная длинна пароля 6 символов';
$MESS['VETTICH_SP3_LIST_TEMPLATES']            = 'Список шаблонов';
$MESS['VETTICH_SP3_RESULT']                    = 'Результат';
$MESS['VETTICH_SP3_TEMPLATES_NOT_FOUND']       = 'Подходящих шаблонов не найдено.';
$MESS['VETTICH_SP3_CHOOSE_TEMPLATE']           = 'Выберите шаблон, с помощью которого нужно опубликовать';
$MESS['VETTICH_SP3_SOME_ERROR']                = 'Произошла какая-то ошибка при получении списка шаблонов...';
$MESS['VETTICH_SP3_SOME_ERROR2']               = 'Произошла какая-то ошибка';
$MESS['VETTICH_SP3_WITHOUT_TEMPLATE']          = 'Или опубликовать НЕ используя шаблон';
$MESS['VETTICH_SP3_PUBLISH']                   = 'Опубликовать';
$MESS['VETTICH_SP3_CHOOSE_TEMPLATE_FROM_LIST'] = 'Выберите шаблон из списка';
$MESS['VETTICH_SP3_ADDED_N_POST']              = 'Было добавлено постов: ';
$MESS['VETTICH_SP3_FORGOT_PASS_SENT']          = 'На вашу почту было отправлено письмо со ссылкой. Перейдите по ссылке из письма, чтобы ввести новый пароль';
$MESS['VETTICH_SP3_POLITIKA_NEED_CONFIRM']     = 'Подтвердите свое согласие с политикой конфиденциальности';

$MESS['VETTICH_SP3_INSTA_ENTER_CODE'] = 'Введите код подтверждения';
$MESS['VETTICH_SP3_TG_FIELDS_EMPTY'] = 'Заполните все поля';

$MESS['VETTICH_SP3_IFRAME_LOAD_ERROR_HTML'] = '<div class="vettich-sp3-iframe-load-error">
<p><b>Не удалось загрузить интерфейс ParrotPoster.</b></p>
<p>Возможные причины: временная недоступность сервиса, ограничения сети или политика безопасности страницы <b>Content-Security-Policy (CSP)</b>, запрещающая встраивание внешних страниц во фрейм.</p>
<p>Если на сайте настроен CSP, администратору нужно добавить в директиву <code>frame-src</code> домены сервиса ParrotPoster (как в списке зеркал модуля), при необходимости разрешить обращения к API в <code>connect-src</code>.</p>
</div>';

$MESS['VETTICH_SP3_IFRAME_LOAD_ERROR_CSP_HTML'] = '<div class="vettich-sp3-iframe-load-error vettich-sp3-iframe-csp-detected">
<p><b>Браузер зафиксировал нарушение Content-Security-Policy (CSP), связанное с ParrotPoster</b> — с высокой вероятностью политика запрещает встраивание интерфейса во фрейм и/или запросы к сервису.</p>
<p><b>Что сделать администратору сайта:</b></p>
<p>В директиву <code>frame-src</code> добавьте источники (полные URL с <code>https://</code>), откуда загружается интерфейс:</p>
#HOSTS#
<p>При ограничении сетевых запросов добавьте те же источники в <code>connect-src</code> (проверка доступности и работа API).</p>
<p>Где править: заголовок <code>Content-Security-Policy</code> или мета-тег — настройки веб-сервера (nginx, Apache), WAF, модулей безопасности или Bitrix, если CSP задаётся для административного раздела.</p>
<p><small>Если сообщение показано ошибочно (ложное срабатывание), всё равно проверьте консоль браузера и список зеркал в настройках модуля.</small></p>
</div>';
