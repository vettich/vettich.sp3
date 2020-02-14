<?

CModule::IncludeModule('vettich.devform');
$start = microtime(true);

(new vettich\devform\AdminForm('devform', array(
	'tabs' => array(
		new vettich\devform\Tab(array(
			'name' => 'Примеры опций',
			'title' => 'Примеры',
			'params' => array(
				'DB_NAME' => 'text:#NAME#:default',
				'DB_IS_ENABLE' => 'checkbox:DB Is enable:N',
				'CO_IS_ENABLE' => 'checkbox:COptions Is enable',
				'CO_note' => 'note:This is note #SECOND#',
				'link' => 'link:Options:link=/bitrix/admin/vettich.devform.list.php:text=link',
			),
		)),
	),
	'buttons' => array(
		'button_id' => 'buttons\saveSubmit:#SAVE#',
		'button_id2' => 'buttons\submit:#APPLY#',
	),
	'data' => 'coption:module_id=vettich.devform',
)))->render();

echo 'Script time: '.(microtime(true) - $start);
