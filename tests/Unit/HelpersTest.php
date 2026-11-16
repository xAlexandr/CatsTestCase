<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
        $_SESSION = [];
    }

    public function testEscapesHtmlSpecialCharacters(): void
    {
        $escaped = e('<script>"кот" & \'пёс\'</script>');

        self::assertStringNotContainsString('<script>', $escaped);
        self::assertStringContainsString('&lt;script&gt;', $escaped);
        self::assertStringContainsString('&quot;кот&quot;', $escaped);
        self::assertStringContainsString('&amp;', $escaped);
        self::assertStringContainsString('&#039;пёс&#039;', $escaped);
    }

    public function testEscapesNullAsEmptyString(): void
    {
        self::assertSame('', e(null));
    }

    #[DataProvider('pluralProvider')]
    public function testPluralSelectsCorrectRussianForm(int $number, string $expected): void
    {
        self::assertSame($expected, plural($number, 'год', 'года', 'лет'));
    }

    public static function pluralProvider(): array
    {
        return [
            [1, 'год'],
            [2, 'года'],
            [4, 'года'],
            [5, 'лет'],
            [11, 'лет'],
            [12, 'лет'],
            [14, 'лет'],
            [21, 'год'],
            [22, 'года'],
            [25, 'лет'],
            [101, 'год'],
            [111, 'лет'],
        ];
    }

    public function testFormatsAge(): void
    {
        self::assertSame('0 лет', format_age(0));
        self::assertSame('1 год', format_age(1));
        self::assertSame('3 года', format_age(3));
        self::assertSame('5 лет', format_age(5));
        self::assertSame('21 год', format_age(21));
    }

    public function testFormatsCatAndFatherCounts(): void
    {
        self::assertSame('1 кошка', cats_count(1));
        self::assertSame('3 кошки', cats_count(3));
        self::assertSame('5 кошек', cats_count(5));

        self::assertSame('1 отец', fathers_count(1));
        self::assertSame('2 отца', fathers_count(2));
        self::assertSame('5 отцов', fathers_count(5));
    }

    public function testReturnsSexLabels(): void
    {
        self::assertSame('Кот', sex_label('male'));
        self::assertSame('Кошка', sex_label('female'));
        self::assertSame('Кошка', sex_label(null));
    }

    public function testBuildsInitials(): void
    {
        self::assertSame('М', initials('Мурка'));
        self::assertSame('М', initials('  мурка  '));
        self::assertSame('Ё', initials('ёжик'));
        self::assertSame('J', initials('john'));
        self::assertSame('?', initials(''));
        self::assertSame('?', initials('   '));
    }

    public function testReadsIntegerQueryParameter(): void
    {
        $_GET = ['age' => '3'];
        self::assertSame(3, int_query('age', 0, 40));

        $_GET = ['age' => '0'];
        self::assertSame(0, int_query('age', 0, 40));

        $_GET = ['age' => '40'];
        self::assertSame(40, int_query('age', 0, 40));
    }

    #[DataProvider('invalidIntegerQueryProvider')]
    public function testRejectsInvalidIntegerQueryParameter(mixed $value, ?int $expected): void
    {
        $_GET = ['age' => $value];

        self::assertSame($expected, int_query('age', 0, 40));
    }

    public static function invalidIntegerQueryProvider(): array
    {
        return [
            'negative' => ['-1', null],
            'too large' => ['41', null],
            'text' => ['abc', null],
            'float' => ['1.5', null],
            'empty' => ['', null],
            'array' => [['3'], null],
        ];
    }

    public function testReturnsNullForMissingQueryParameter(): void
    {
        self::assertNull(int_query('missing', 0, 40));
    }

    public function testGeneratesAndReusesCsrfToken(): void
    {
        $token = csrf_token();

        self::assertSame(64, strlen($token));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        self::assertSame($token, csrf_token());

        $field = csrf_field();
        self::assertStringContainsString('name="csrf_token"', $field);
        self::assertStringContainsString('value="' . $token . '"', $field);
    }

    public function testVerifyCsrfAcceptsValidToken(): void
    {
        $_POST = ['csrf_token' => csrf_token()];
        verify_csrf();

        $this->addToAssertionCount(1);
    }

    public function testStoresAndConsumesFlashMessages(): void
    {
        self::assertSame([], take_flashes());

        flash('success', 'Готово');
        flash('error', 'Ошибка');

        self::assertSame([
            ['type' => 'success', 'message' => 'Готово'],
            ['type' => 'error', 'message' => 'Ошибка'],
        ], take_flashes());

        self::assertSame([], take_flashes());
    }

    public function testRendersSvgIcons(): void
    {
        $icon = icon('plus');

        self::assertStringContainsString('<svg class="icon"', $icon);
        self::assertStringContainsString('aria-hidden="true"', $icon);
        self::assertStringContainsString('M12 5v14M5 12h14', $icon);

        $custom = icon('cat', 'icon icon-sm');
        self::assertStringContainsString('class="icon icon-sm"', $custom);

        $fallback = icon('unknown-icon');
        self::assertStringContainsString('<ellipse', $fallback);
    }
}
