<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

final class FilteringTest extends HttpTestCase
{
    public function testFiltersBySex(): void
    {
        $malePage = $this->client->get('/index.php?sex=male');
        $femalePage = $this->client->get('/index.php?sex=female');

        self::assertSame(3, $malePage->catCardCount());
        self::assertEqualsCanonicalizing(['Персик', 'Симба', 'Тимка'], $malePage->catNames());

        self::assertSame(3, $femalePage->catCardCount());
        self::assertEqualsCanonicalizing(['Луна', 'Марта', 'Мурка'], $femalePage->catNames());
    }

    public function testFiltersByAgeRange(): void
    {
        $response = $this->client->get('/index.php?age_min=1&age_max=3');

        self::assertSame(200, $response->status);
        self::assertSame(3, $response->catCardCount());
        self::assertEqualsCanonicalizing(['Луна', 'Марта', 'Симба'], $response->catNames());
        self::assertStringContainsString('По выбранным условиям', $response->body);
    }

    public function testSwapsAgeRangeWhenMinimumIsGreaterThanMaximum(): void
    {
        $response = $this->client->get('/index.php?age_min=5&age_max=2');

        self::assertSame(3, $response->catCardCount());
        self::assertEqualsCanonicalizing(['Луна', 'Мурка', 'Симба'], $response->catNames());
    }

    public function testCombinesSexAndAgeFilters(): void
    {
        $response = $this->client->get('/index.php?sex=female&age_min=2&age_max=5');

        self::assertSame(2, $response->catCardCount());
        self::assertEqualsCanonicalizing(['Луна', 'Мурка'], $response->catNames());
    }

    public function testFiltersNewbornCatsWithZeroAge(): void
    {
        $response = $this->client->get('/index.php?age_min=0&age_max=0');

        self::assertSame(1, $response->catCardCount());
        self::assertSame(['Персик'], $response->catNames());
    }

    public function testSearchesByName(): void
    {
        $response = $this->client->get('/index.php?q=' . urlencode('Сим'));

        self::assertSame(1, $response->catCardCount());
        self::assertSame(['Симба'], $response->catNames());
    }

    public function testShowsEmptyStateWhenNothingMatches(): void
    {
        $response = $this->client->get('/index.php?q=' . urlencode('Несуществующая'));

        self::assertSame(200, $response->status);
        self::assertSame(0, $response->catCardCount());
        self::assertSame([], $response->catNames());
        self::assertStringContainsString('Ничего не нашлось', $response->body);
        self::assertStringContainsString('Сбросить фильтры', $response->body);
    }

    public function testInvalidFilterValuesAreIgnored(): void
    {
        $response = $this->client->get('/index.php?sex=unknown&age_min=abc&age_max=999');

        self::assertSame(200, $response->status);
        self::assertSame(6, $response->catCardCount());
        self::assertEqualsCanonicalizing(
            ['Луна', 'Марта', 'Мурка', 'Персик', 'Симба', 'Тимка'],
            $response->catNames()
        );
    }

    public function testSearchIsSafeAgainstSqlInjection(): void
    {
        $response = $this->client->get('/index.php?q=' . urlencode("' OR 1=1 --"));

        self::assertSame(200, $response->status);
        self::assertSame(0, $response->catCardCount());
        self::assertStringContainsString('Ничего не нашлось', $response->body);
    }
}
