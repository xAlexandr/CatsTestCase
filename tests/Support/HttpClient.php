<?php

declare(strict_types=1);

namespace Tests\Support;

use CurlHandle;
use RuntimeException;

final class HttpClient
{
    private CurlHandle $handle;
    private string $cookieFile;

    public function __construct(private readonly string $baseUrl)
    {
        $cookieFile = tempnam(sys_get_temp_dir(), 'cats-cookies-');

        if ($cookieFile === false) {
            throw new RuntimeException('Не удалось создать файл для cookies.');
        }

        $this->cookieFile = $cookieFile;
        $this->handle = curl_init();

        curl_setopt_array($this->handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_ENCODING => '',
        ]);
    }

    public function __destruct()
    {
        unset($this->handle);
        @unlink($this->cookieFile);
    }

    public function get(string $path): HttpResponse
    {
        return $this->send('GET', $path);
    }

    public function post(string $path, array $fields): HttpResponse
    {
        return $this->send('POST', $path, $fields);
    }

    private function send(string $method, string $path, array $fields = []): HttpResponse
    {
        $headers = [];

        curl_setopt($this->handle, CURLOPT_URL, $this->baseUrl . $path);
        curl_setopt($this->handle, CURLOPT_HEADERFUNCTION, static function ($curl, string $line) use (&$headers): int {
            $length = strlen($line);
            $trimmed = trim($line);

            if ($trimmed === '') {
                return $length;
            }

            if (str_starts_with($trimmed, 'HTTP/')) {
                $headers = [];
                return $length;
            }

            $parts = explode(':', $trimmed, 2);
            $name = strtolower(trim($parts[0]));
            $headers[$name][] = trim($parts[1] ?? '');

            return $length;
        });

        if ($method === 'POST') {
            curl_setopt($this->handle, CURLOPT_POST, true);
            curl_setopt($this->handle, CURLOPT_POSTFIELDS, http_build_query($fields));
            curl_setopt($this->handle, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: text/html',
            ]);
        } else {
            curl_setopt($this->handle, CURLOPT_HTTPGET, true);
            curl_setopt($this->handle, CURLOPT_HTTPHEADER, ['Accept: text/html']);
        }

        $body = curl_exec($this->handle);

        if ($body === false) {
            throw new RuntimeException('HTTP-запрос не удался: ' . curl_error($this->handle));
        }

        $status = (int) curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE);

        return new HttpResponse($status, $headers, $body);
    }
}
