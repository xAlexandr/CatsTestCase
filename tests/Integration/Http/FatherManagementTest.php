<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use Tests\Support\TestDatabase;

final class FatherManagementTest extends HttpTestCase
{
    public function testFatherPageShowsAllFathersAndRelationCounts(): void
    {
        $response = $this->client->get('/fathers.php');

        self::assertSame(200, $response->status);
        self::assertSame(4, substr_count($response->body, 'class="father-row"'));
        self::assertStringContainsString('Барсик', $response->body);
        self::assertStringContainsString('Снежок', $response->body);
        self::assertStringContainsString('Указан как отец: 2 кошки', $response->body);
        self::assertStringContainsString('Указан как отец: 1 кошка', $response->body);
        self::assertStringContainsString('Всего: 4 отца в справочнике', $response->body);
    }

    public function testAddsFatherToDirectory(): void
    {
        $page = $this->client->get('/fathers.php');
        $token = $page->csrfToken();

        $response = $this->client->post('/fathers.php', [
            'csrf_token' => $token,
            'action' => 'add',
            'name' => 'Лео',
        ]);

        self::assertSame(303, $response->status);
        self::assertSame('fathers.php', $response->location());
        self::assertNotNull(TestDatabase::findFatherByName('Лео'));
        self::assertSame(5, TestDatabase::countRows('fathers'));

        $followUp = $this->client->get('/fathers.php');

        self::assertStringContainsString('Отец «Лео» добавлен в справочник.', $followUp->body);
        self::assertStringContainsString('Лео', $followUp->body);
    }

    public function testDuplicateFatherNameIsRejected(): void
    {
        $page = $this->client->get('/fathers.php');
        $token = $page->csrfToken();

        $response = $this->client->post('/fathers.php', [
            'csrf_token' => $token,
            'action' => 'add',
            'name' => 'Барсик',
        ]);

        self::assertSame(303, $response->status);
        self::assertSame(4, TestDatabase::countRows('fathers'));
        self::assertSame(1, TestDatabase::countRows('fathers', 'name = ?', ['Барсик']));

        $followUp = $this->client->get('/fathers.php');

        self::assertStringContainsString('Отец с такой кличкой уже есть в справочнике.', $followUp->body);
    }

    public function testEmptyFatherNameIsRejected(): void
    {
        $page = $this->client->get('/fathers.php');
        $token = $page->csrfToken();

        $response = $this->client->post('/fathers.php', [
            'csrf_token' => $token,
            'action' => 'add',
            'name' => '   ',
        ]);

        self::assertSame(303, $response->status);
        self::assertSame(4, TestDatabase::countRows('fathers'));

        $followUp = $this->client->get('/fathers.php');

        self::assertStringContainsString('Укажите кличку отца.', $followUp->body);
    }

    public function testTooLongFatherNameIsRejected(): void
    {
        $page = $this->client->get('/fathers.php');
        $token = $page->csrfToken();

        $response = $this->client->post('/fathers.php', [
            'csrf_token' => $token,
            'action' => 'add',
            'name' => str_repeat('О', 101),
        ]);

        self::assertSame(303, $response->status);
        self::assertSame(4, TestDatabase::countRows('fathers'));

        $followUp = $this->client->get('/fathers.php');

        self::assertStringContainsString('Кличка не должна превышать 100 символов.', $followUp->body);
    }

    public function testDeletingFatherRemovesLinksFromCats(): void
    {
        $father = TestDatabase::findFatherByName('Снежок');
        self::assertNotNull($father);

        $page = $this->client->get('/fathers.php');
        $token = $page->csrfToken();

        $response = $this->client->post('/fathers.php', [
            'csrf_token' => $token,
            'action' => 'delete',
            'id' => (string) $father['id'],
        ]);

        self::assertSame(303, $response->status);
        self::assertSame(0, TestDatabase::countRows('fathers', 'id = ?', [(int) $father['id']]));
        self::assertSame(0, TestDatabase::countRows('cat_fathers', 'father_id = ?', [(int) $father['id']]));
        self::assertSame(6, TestDatabase::countRows('cats'));

        $followUp = $this->client->get('/fathers.php');

        self::assertStringContainsString('Отец «Снежок» удалён из справочника.', $followUp->body);
        self::assertStringNotContainsString('Указан как отец: 2 кошки', $followUp->body);
    }

    public function testUnknownActionIsRejected(): void
    {
        $page = $this->client->get('/fathers.php');
        $token = $page->csrfToken();

        $response = $this->client->post('/fathers.php', [
            'csrf_token' => $token,
            'action' => 'unknown',
        ]);

        self::assertSame(303, $response->status);

        $followUp = $this->client->get('/fathers.php');

        self::assertStringContainsString('Неизвестное действие.', $followUp->body);
        self::assertSame(4, TestDatabase::countRows('fathers'));
    }
}
