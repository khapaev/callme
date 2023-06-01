<?php

$config = [
	'CallMeDEBUG' => true, // Дебаг сообщения в логе: true - пишем, false - не пишем
	'bitrixApiUrl' => 'https://_type_your_url_here', // URL к API Битрикс24 (входящий вебхук)
	'asterisk' => [ // Настройки для подключения к Asterisk
		'host' => '127.0.0.1',
		'scheme' => 'tcp://',
		'port' => 5038,
		'username' => 'admin',
		'secret' => 'MVGuslitSTBT',
		'connect_timeout' => 10000,
		'read_timeout' => 10000
	],
	'listener_timeout' => 300, // Скорость обработки событий от Asterisk
];

return $config;
