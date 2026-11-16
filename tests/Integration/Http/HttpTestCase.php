<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\HttpClient;
use Tests\Support\TestDatabase;
use Tests\Support\TestServer;

abstract class HttpTestCase extends TestCase
{
    protected HttpClient $client;
    protected PDO $pdo;

    protected function setUp(): void
    {
        TestDatabase::reset(true);
        TestServer::start();

        $this->client = new HttpClient(TestServer::baseUrl());
        $this->pdo = TestDatabase::pdo();
    }
}
