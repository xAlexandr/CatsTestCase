<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use Tests\Support\TestDatabase;

final class CatCrudTest extends HttpTestCase
{
    public function testHomePageShowsSeedCatsAndStatistics(): void
    {
        $response = $this->client->get('/index.php');

        self::assertSame(200, $response->status);
        self::assertSame(6, $response->catCardCount());
        self::assertEqualsCanonicalizing(
            ['Луна', 'Марта', 'Мурка', 'Персик', 'Симба', 'Тимка'],
            $response->catNames()
        );
        self::assertStringContainsString('6 кошек', $response->body);
        self::assertStringContainsString('2,8', $response->body);
        self::assertStringContainsString('Барсик, Снежок', $response->body);
    }

    public function testCreateCatWithMotherAndSeveralFathers(): void
    {
        $form = $this->client->get('/cat_form.php');
        $token = $form->csrfToken();

        $response = $this->client->post('/cat_form.php', [
            'csrf_token' => $token,
            'name' => 'Нюша',
            'sex' => 'female',
            'age' => '1',
            'mother_id' => '1',
            'father_ids' => ['1', '3'],
        ]);

        self::assertSame(303, $response->status);
        self::assertSame('index.php', $response->location());

        $cat = TestDatabase::findCatByName('Нюша');

        self::assertNotNull($cat);
        self::assertSame('female', $cat['sex']);
        self::assertSame(1, (int) $cat['age']);
        self::assertSame(1, (int) $cat['mother_id']);
        self::assertSame([1, 3], TestDatabase::catFatherIds((int) $cat['id']));

        $page = $this->client->get('/index.php');

        self::assertStringContainsString('Кошка «Нюша» добавлена в картотеку.', $page->body);
        self::assertSame(7, $page->catCardCount());
        self::assertContains('Нюша', $page->catNames());
    }

    public function testEditCatUpdatesFieldsAndReplacesFathers(): void
    {
        $form = $this->client->get('/cat_form.php?id=4');
        $token = $form->csrfToken();

        $response = $this->client->post('/cat_form.php?id=4', [
            'csrf_token' => $token,
            'id' => '4',
            'name' => 'Симба Второй',
            'sex' => 'male',
            'age' => '3',
            'mother_id' => '2',
            'father_ids' => ['4'],
        ]);

        self::assertSame(303, $response->status);
        self::assertSame('index.php', $response->location());

        $cat = TestDatabase::findCat(4);

        self::assertNotNull($cat);
        self::assertSame('Симба Второй', $cat['name']);
        self::assertSame('male', $cat['sex']);
        self::assertSame(3, (int) $cat['age']);
        self::assertSame(2, (int) $cat['mother_id']);
        self::assertSame([4], TestDatabase::catFatherIds(4));

        $page = $this->client->get('/index.php');

        self::assertStringContainsString('Изменения для кошки «Симба Второй» сохранены.', $page->body);
        self::assertContains('Симба Второй', $page->catNames());
    }

    public function testDeleteCatRemovesRecordAndFatherLinks(): void
    {
        $form = $this->client->get('/cat_form.php?id=4');
        $token = $form->csrfToken();

        $response = $this->client->post('/cat_delete.php', [
            'csrf_token' => $token,
            'id' => '4',
        ]);

        self::assertSame(303, $response->status);
        self::assertSame('index.php', $response->location());
        self::assertNull(TestDatabase::findCat(4));
        self::assertSame([], TestDatabase::catFatherIds(4));
        self::assertSame(5, TestDatabase::countRows('cats'));

        $page = $this->client->get('/index.php');

        self::assertStringContainsString('Кошка «Симба» удалена из картотеки.', $page->body);
        self::assertSame(5, $page->catCardCount());
        self::assertNotContains('Симба', $page->catNames());
    }

    public function testDeletingMotherKeepsChildrenAndClearsTheirMotherReference(): void
    {
        $form = $this->client->get('/cat_form.php?id=1');
        $token = $form->csrfToken();

        $response = $this->client->post('/cat_delete.php', [
            'csrf_token' => $token,
            'id' => '1',
        ]);

        self::assertSame(303, $response->status);

        $firstChild = TestDatabase::findCat(4);
        $secondChild = TestDatabase::findCat(6);

        self::assertNotNull($firstChild);
        self::assertNotNull($secondChild);
        self::assertNull($firstChild['mother_id']);
        self::assertNull($secondChild['mother_id']);
        self::assertSame(5, TestDatabase::countRows('cats'));
    }
}
