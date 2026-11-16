<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use Tests\Support\TestDatabase;

final class SecurityTest extends HttpTestCase
{
    public function testCatDeleteWithoutCsrfTokenIsRejected(): void
    {
        $response = $this->client->post('/cat_delete.php', ['id' => '4']);

        self::assertSame(419, $response->status);
        self::assertNotNull(TestDatabase::findCat(4));
    }

    public function testCatSaveWithoutCsrfTokenIsRejected(): void
    {
        $response = $this->client->post('/cat_form.php', [
            'name' => 'Без токена',
            'sex' => 'female',
            'age' => '2',
        ]);

        self::assertSame(419, $response->status);
        self::assertNull(TestDatabase::findCatByName('Без токена'));
    }

    public function testFatherAddWithoutCsrfTokenIsRejected(): void
    {
        $response = $this->client->post('/fathers.php', [
            'action' => 'add',
            'name' => 'Без токена',
        ]);

        self::assertSame(419, $response->status);
        self::assertSame(4, TestDatabase::countRows('fathers'));
    }

    public function testCatDeleteEndpointRejectsGetRequests(): void
    {
        $response = $this->client->get('/cat_delete.php');

        self::assertSame(405, $response->status);
        self::assertSame('POST', $response->header('allow'));
        self::assertStringContainsString('Метод не поддерживается.', $response->body);
    }

    public function testHtmlInCatNameIsEscapedOnHomePage(): void
    {
        $form = $this->client->get('/cat_form.php');
        $token = $form->csrfToken();
        $name = '<script>alert(1)</script>';

        $response = $this->client->post('/cat_form.php', [
            'csrf_token' => $token,
            'name' => $name,
            'sex' => 'female',
            'age' => '1',
        ]);

        self::assertSame(303, $response->status);
        self::assertNotNull(TestDatabase::findCatByName($name));

        $page = $this->client->get('/index.php');

        self::assertStringNotContainsString($name, $page->body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $page->body);
    }

    public function testHtmlInFatherNameIsEscaped(): void
    {
        $page = $this->client->get('/fathers.php');
        $token = $page->csrfToken();
        $name = '<b>Отец</b>';

        $response = $this->client->post('/fathers.php', [
            'csrf_token' => $token,
            'action' => 'add',
            'name' => $name,
        ]);

        self::assertSame(303, $response->status);
        self::assertNotNull(TestDatabase::findFatherByName($name));

        $followUp = $this->client->get('/fathers.php');

        self::assertStringNotContainsString($name, $followUp->body);
        self::assertStringContainsString('&lt;b&gt;Отец&lt;/b&gt;', $followUp->body);
    }
}
