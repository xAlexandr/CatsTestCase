<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Сессия истекла. Обновите страницу и попробуйте снова.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $flashes;
}

function sex_label(?string $sex): string
{
    return $sex === 'male' ? 'Кот' : 'Кошка';
}

function plural(int $number, string $one, string $few, string $many): string
{
    $mod100 = $number % 100;

    if ($mod100 >= 11 && $mod100 <= 14) {
        return $many;
    }

    return match ($number % 10) {
        1 => $one,
        2, 3, 4 => $few,
        default => $many,
    };
}

function format_age(int $age): string
{
    return $age . ' ' . plural($age, 'год', 'года', 'лет');
}

function cats_count(int $count): string
{
    return $count . ' ' . plural($count, 'кошка', 'кошки', 'кошек');
}

function fathers_count(int $count): string
{
    return $count . ' ' . plural($count, 'отец', 'отца', 'отцов');
}

function initials(string $name): string
{
    $name = trim($name);

    return $name === '' ? '?' : mb_strtoupper(mb_substr($name, 0, 1));
}

function int_query(string $key, int $min, int $max): ?int
{
    $raw = $_GET[$key] ?? null;

    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }

    $value = filter_var($raw, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $min, 'max_range' => $max],
    ]);

    return $value === false ? null : $value;
}

function icon(string $name, string $class = 'icon'): string
{
    $paths = [
        'paw' => '<ellipse cx="5.5" cy="10.5" rx="2" ry="2.6" transform="rotate(-22 5.5 10.5)"/><ellipse cx="10" cy="5.8" rx="2" ry="2.7" transform="rotate(-8 10 5.8)"/><ellipse cx="17.4" cy="6.2" rx="2" ry="2.7" transform="rotate(14 17.4 6.2)"/><ellipse cx="20.8" cy="11.6" rx="2" ry="2.6" transform="rotate(30 20.8 11.6)"/><path d="M8.1 15.1c1-1.7 2.5-2.6 3.9-2.6s2.9.9 3.9 2.6c1.2 1.9.6 4.3-1.2 5.2-1.2.6-2.1-.3-3.4-.3s-2.3.9-3.4.3c-1.8-.9-2.4-3.3 0.2-5.2z"/>',
        'cat' => '<path d="M4.5 10.5 5.5 4l4 2.7a9.5 9.5 0 0 1 5 0L18.5 4l1 6.5v4.6a6.5 6.5 0 0 1-6.5 6.5h-2a6.5 6.5 0 0 1-6.5-6.5z"/><path d="M9.2 14.5h.01M15 14.5h.01M11 17.4h2"/>',
        'female' => '<circle cx="12" cy="9" r="5"/><path d="M12 14v7M9 18h6"/>',
        'male' => '<circle cx="10" cy="14" r="5"/><path d="m14.5 9.5 5-5M15.5 4h4.5v4.5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.6 3.4a2.1 2.1 0 0 1 3 3L7.1 18.9l-4 1 1-4z"/>',
        'trash' => '<path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="m19 6-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/>',
        'arrow-left' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'alert' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
    ];

    $content = $paths[$name] ?? $paths['paw'];

    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $content . '</svg>';
}
