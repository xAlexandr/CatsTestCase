<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Метод не поддерживается.');
}

verify_csrf();

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($id === false) {
    flash('error', 'Кошка не найдена.');
    redirect('index.php');
}

$statement = db()->prepare('SELECT name FROM cats WHERE id = ?');
$statement->execute([$id]);
$cat = $statement->fetch();

if ($cat === false) {
    flash('error', 'Кошка не найдена.');
    redirect('index.php');
}

db()->prepare('DELETE FROM cats WHERE id = ?')->execute([$id]);

flash('success', 'Кошка «' . $cat['name'] . '» удалена из картотеки.');
redirect('index.php');
