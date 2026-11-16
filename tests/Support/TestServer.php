<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class TestServer
{
    private static $process = null;
    private static array $pipes = [];
    private static ?string $baseUrl = null;
    private static bool $shutdownRegistered = false;

    public static function start(): void
    {
        if (self::$baseUrl !== null) {
            return;
        }

        if (!str_ends_with(DB_NAME, '_test')) {
            throw new RuntimeException('Тестовый сервер можно запускать только с тестовой базой данных.');
        }

        $root = dirname(__DIR__, 2);
        $port = self::findFreePort();
        $logFile = sys_get_temp_dir() . '/cats-test-server.log';
        @unlink($logFile);

        $environment = getenv();

        if (!is_array($environment)) {
            $environment = [];
        }

        $environment['DB_HOST'] = DB_HOST;
        $environment['DB_NAME'] = DB_NAME;
        $environment['DB_USER'] = DB_USER;
        $environment['DB_PASS'] = DB_PASS;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $logFile, 'a'],
            2 => ['file', $logFile, 'a'],
        ];

        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root],
            $descriptors,
            $pipes,
            $root,
            $environment
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Не удалось запустить встроенный PHP-сервер для тестов.');
        }

        self::$process = $process;
        self::$pipes = $pipes;
        self::$baseUrl = 'http://127.0.0.1:' . $port;

        if (!self::$shutdownRegistered) {
            register_shutdown_function([self::class, 'stop']);
            self::$shutdownRegistered = true;
        }

        self::waitUntilReady($logFile);
    }

    public static function baseUrl(): string
    {
        if (self::$baseUrl === null) {
            self::start();
        }

        return self::$baseUrl ?? '';
    }

    public static function stop(): void
    {
        foreach (self::$pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        self::$pipes = [];

        if (is_resource(self::$process)) {
            proc_terminate(self::$process);
            proc_close(self::$process);
        }

        self::$process = null;
        self::$baseUrl = null;
    }

    private static function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if ($socket === false) {
            throw new RuntimeException('Не удалось найти свободный порт: ' . $errorMessage);
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        if ($name === false) {
            throw new RuntimeException('Не удалось определить свободный порт.');
        }

        return (int) substr(strrchr($name, ':'), 1);
    }

    private static function waitUntilReady(string $logFile): void
    {
        $port = (int) parse_url(self::$baseUrl, PHP_URL_PORT);

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $connection = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 0.1);

            if (is_resource($connection)) {
                fclose($connection);
                return;
            }

            usleep(50_000);
        }

        $log = is_file($logFile) ? file_get_contents($logFile) : '';
        throw new RuntimeException('Тестовый сервер не запустился. Лог: ' . $log);
    }
}
