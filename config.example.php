<?php

return [
	/**
	 * Параметры подключения к базе
	 */
	'db' => [
		'database_type' => 'mysql',
		'database_name' => 'astronetics',
		'username' => 'user',
		'password' => 'password',
		'server' => '127.0.0.1',
		'port' => 3306,
		'charset' => 'utf8mb4',
		'collation' => 'utf8mb4_general_ci',
		'option' => [
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
			PDO::ATTR_STRINGIFY_FETCHES => false
		]
	],

	/**
	 * Список id категорий
	 */
	'categoriesId' => [1, 2, 3, 4, 5],

	/**
	 * Тайм-аут между прохождениями одного теста
	 */
	'testRePassDelay' => 2 * 60 * 60,

	/**
	 * Количество вопросов в тесте
	 */
	'questionsInTest' => 10,

	/**
	 * Проходной балл в тестах
	 */
	'passingScore' => 6,

	/**
	 * Откуда скачивать сертификат
	 */
	'certDownloadUrl' => 'https://astronetics.local/cert.php?id=',

	/**
	 * Откуда скачивать последнюю версию приложения
	 */
	'updateDownloadUrl' => 'https://astronetics.local/release/astronetics.apk?cv=',

	/**
	 * Время между запросами к getTests от нового пользователя (сек)
	 */
	'requestTimeInterval' => 30,

	/**
	 * Максимальное количество запросов за requestTimeInterval от одного пользователя
	 */
	'requestMax' => 2,

	/**
	 * id приложения ВК для входа
	 */
	'vk_app_id' => 00000000,

	/**
	 * Защищенный ключ приложения ВК
	 */
	'vk_app_secret' => 'xxxxxxxxxxxxxxxxxxxx'
];
