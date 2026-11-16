<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$testDatabase = getenv('TEST_DB_NAME');

if ($testDatabase === false || $testDatabase === '') {
    $testDatabase = 'cats_db_test';
}

if (!str_ends_with($testDatabase, '_test')) {
    fwrite(STDERR, "Имя тестовой базы данных должно заканчиваться на _test.\n");
    exit(1);
}

putenv('DB_NAME=' . $testDatabase);
putenv('APP_ENV=test');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/helpers.php';
