<?php

declare(strict_types=1);

$config = [
    'host' => 'localhost',
    'name' => 'cats_db',
    'user' => 'cat_app',
    'pass' => '',
    'site_name' => 'Мурлыка',
];

$localConfig = __DIR__ . '/config.local.php';

if (is_file($localConfig)) {
    $local = require $localConfig;

    if (is_array($local)) {
        $config = array_replace($config, $local);
    }
}

$environment = [
    'DB_HOST' => 'host',
    'DB_NAME' => 'name',
    'DB_USER' => 'user',
    'DB_PASS' => 'pass',
];

foreach ($environment as $variable => $key) {
    $value = getenv($variable);

    if ($value !== false) {
        $config[$key] = $value;
    }
}

define('DB_HOST', (string) $config['host']);
define('DB_NAME', (string) $config['name']);
define('DB_USER', (string) $config['user']);
define('DB_PASS', (string) $config['pass']);
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', (string) $config['site_name']);
