<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\HttpResponse;
use Tests\Support\TestDatabase;

final class CatValidationTest extends HttpTestCase
{
    #[DataProvider('invalidAgeProvider')]
    public function testInvalidAgeIsRejected(string $age): void
    {
        $response = $this->submitCatForm([
            'name' => 'Проверка возраста',
            'age' => $age,
        ]);

        self::assertSame(200, $response->status);
        self::assertStringContainsString(
            'Возраст должен быть целым числом от 0 до 40 лет.',
            $response->body
        );
        self::assertNull(TestDatabase::findCatByName('Проверка возраста'));
    }

    public static function invalidAgeProvider(): array
    {
        return [
            'too old' => ['99'],
            'negative' => ['-1'],
            'text' => ['abc'],
            'float' => ['1.5'],
        ];
    }

    public function testEmptyNameIsRejected(): void
    {
        $response = $this->submitCatForm([
            'name' => '   ',
            'age' => '2',
        ]);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Укажите кличку кошки.', $response->body);
        self::assertSame(6, TestDatabase::countRows('cats'));
    }

    public function testTooLongNameIsRejected(): void
    {
        $name = str_repeat('А', 101);

        $response = $this->submitCatForm([
            'name' => $name,
            'age' => '2',
        ]);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Кличка не должна превышать 100 символов.', $response->body);
        self::assertSame(6, TestDatabase::countRows('cats'));
    }

    public function testUnknownSexIsRejected(): void
    {
        $response = $this->submitCatForm([
            'name' => 'Неизвестный пол',
            'sex' => 'unknown',
            'age' => '2',
        ]);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Выберите пол кошки.', $response->body);
        self::assertNull(TestDatabase::findCatByName('Неизвестный пол'));
    }

    public function testMaleCatCannotBeSelectedAsMother(): void
    {
        $response = $this->submitCatForm([
            'name' => 'Мать-кот',
            'age' => '2',
            'mother_id' => '3',
        ]);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('В качестве матери можно выбрать только кошку.', $response->body);
        self::assertNull(TestDatabase::findCatByName('Мать-кот'));
    }

    public function testUnknownMotherIsRejected(): void
    {
        $response = $this->submitCatForm([
            'name' => 'Потерянная мать',
            'age' => '2',
            'mother_id' => '999999',
        ]);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Выбранная мать не найдена.', $response->body);
        self::assertNull(TestDatabase::findCatByName('Потерянная мать'));
    }

    public function testCatCannotBeItsOwnMother(): void
    {
        $response = $this->submitCatForm([
            'name' => 'Мурка',
            'sex' => 'female',
            'age' => '5',
            'mother_id' => '1',
        ], 1);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Кошка не может быть собственной матерью.', $response->body);
        self::assertNull(TestDatabase::findCat(1)['mother_id']);
    }

    public function testMotherCycleIsRejected(): void
    {
        $response = $this->submitCatForm([
            'name' => 'Луна',
            'sex' => 'female',
            'age' => '3',
            'mother_id' => '5',
        ], 2);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('получится замкнутая цепочка родства', $response->body);
        self::assertNull(TestDatabase::findCat(2)['mother_id']);
    }

    public function testFemaleWithChildrenCannotBecomeMale(): void
    {
        $response = $this->submitCatForm([
            'name' => 'Мурка',
            'sex' => 'male',
            'age' => '5',
        ], 1);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('уже отмечена матерью других кошек', $response->body);
        self::assertSame('female', TestDatabase::findCat(1)['sex']);
    }

    public function testUnknownFatherIsRejected(): void
    {
        $response = $this->submitCatForm([
            'name' => 'Отец-призрак',
            'age' => '2',
            'father_ids' => ['999999'],
        ]);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Некоторые отцы не найдены в справочнике.', $response->body);
        self::assertNull(TestDatabase::findCatByName('Отец-призрак'));
    }

    public function testValuesArePreservedAfterValidationError(): void
    {
        $response = $this->submitCatForm([
            'name' => 'Сохранённая кличка',
            'sex' => 'male',
            'age' => '99',
            'father_ids' => ['4'],
        ]);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('value="Сохранённая кличка"', $response->body);
        self::assertStringContainsString('value="99"', $response->body);
        self::assertMatchesRegularExpression('/name="father_ids\[\]"\s+value="4"\s+checked/', $response->body);
    }

    public function testMissingCatReturns404(): void
    {
        $response = $this->client->get('/cat_form.php?id=999999');

        self::assertSame(404, $response->status);
        self::assertStringContainsString('Кошка не найдена.', $response->body);
    }

    public function testNonIntegerCatIdReturns404(): void
    {
        $response = $this->client->get('/cat_form.php?id=abc');

        self::assertSame(404, $response->status);
        self::assertStringContainsString('Кошка не найдена.', $response->body);
    }

    private function submitCatForm(array $fields, ?int $id = null): HttpResponse
    {
        $path = $id === null ? '/cat_form.php' : '/cat_form.php?id=' . $id;
        $form = $this->client->get($path);

        $fields['csrf_token'] = $form->csrfToken();
        $fields['sex'] ??= 'female';
        $fields['age'] ??= '2';

        if ($id !== null) {
            $fields['id'] = (string) $id;
        }

        return $this->client->post($path, $fields);
    }
}
