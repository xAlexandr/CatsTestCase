<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use Tests\Support\TestDatabase;

final class CatFormTest extends HttpTestCase
{
    public function testCreateFormListsOnlyFemaleCatsAsPossibleMothers(): void
    {
        $response = $this->client->get('/cat_form.php');
        $options = $response->selectHtml('cat-mother');

        self::assertStringContainsString('Мурка', $options);
        self::assertStringContainsString('Луна', $options);
        self::assertStringContainsString('Марта', $options);
        self::assertStringNotContainsString('Тимка', $options);
        self::assertStringNotContainsString('Симба', $options);
        self::assertStringNotContainsString('Персик', $options);
    }

    public function testEditFormExcludesEditedCatFromMotherOptions(): void
    {
        $response = $this->client->get('/cat_form.php?id=1');
        $options = $response->selectHtml('cat-mother');

        self::assertStringNotContainsString('value="1"', $options);
        self::assertStringContainsString('value="2"', $options);
        self::assertStringContainsString('value="5"', $options);
    }

    public function testEditFormShowsCurrentValuesAndParents(): void
    {
        $response = $this->client->get('/cat_form.php?id=4');
        $body = $response->body;

        self::assertSame(200, $response->status);
        self::assertStringContainsString('value="Симба"', $body);
        self::assertStringContainsString('value="2"', $body);
        self::assertMatchesRegularExpression('/name="sex"\s+value="male"\s+checked/', $body);
        self::assertMatchesRegularExpression('/<option\s+value="1"\s+selected\s*>/', $body);
        self::assertMatchesRegularExpression('/name="father_ids\[\]"\s+value="1"\s+checked/', $body);
        self::assertMatchesRegularExpression('/name="father_ids\[\]"\s+value="2"\s+checked/', $body);
        self::assertDoesNotMatchRegularExpression('/name="father_ids\[\]"\s+value="3"\s+checked/', $body);
    }

    public function testFormOffersToCreateFatherWhenDirectoryIsEmpty(): void
    {
        TestDatabase::reset();

        $response = $this->client->get('/cat_form.php');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('В справочнике пока нет отцов.', $response->body);
        self::assertStringContainsString('Добавить первого отца', $response->body);
        self::assertStringContainsString('<option value="">Не указана</option>', $response->body);
    }

    public function testFormRendersAllFathersAsCheckboxes(): void
    {
        $response = $this->client->get('/cat_form.php');

        foreach (['Барсик', 'Снежок', 'Мурзик', 'Огонёк'] as $fatherName) {
            self::assertStringContainsString($fatherName, $response->body);
        }

        self::assertSame(4, substr_count($response->body, 'name="father_ids[]"'));
    }
}
