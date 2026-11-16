<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? SITE_NAME;
$activeNav = $activeNav ?? '';
$flashes = take_flashes();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6efe5">
    <meta name="description" content="Учёт кошек, картотека питомцев и родословные связи">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='18' fill='%23d4633b'/%3E%3Cg fill='%23fff8f0'%3E%3Cellipse cx='18' cy='26' rx='6' ry='8' transform='rotate(-20 18 26)'/%3E%3Cellipse cx='32' cy='19' rx='6' ry='8'/%3E%3Cellipse cx='46' cy='26' rx='6' ry='8' transform='rotate(20 46 26)'/%3E%3Cpath d='M32 32c10 0 19 7 19 15 0 6-5 9-10 8-3-.5-6-1.5-9-1.5s-6 1-9 1.5c-5 1-10-2-10-8 0-8 9-15 19-15z'/%3E%3C/g%3E%3C/svg%3E">
    <title><?= e($pageTitle) ?> — <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="index.php" aria-label="<?= e(SITE_NAME) ?>, на главную">
            <span class="brand-mark"><?= icon('paw') ?></span>
            <span class="brand-text">
                <strong><?= e(SITE_NAME) ?></strong>
                <small>реестр кошек</small>
            </span>
        </a>

        <nav class="main-nav" aria-label="Основная навигация">
            <a class="nav-link <?= $activeNav === 'cats' ? 'is-active' : '' ?>" href="index.php">Кошки</a>
            <a class="nav-link <?= $activeNav === 'fathers' ? 'is-active' : '' ?>" href="fathers.php">Отцы</a>
        </nav>

        <a class="btn btn-primary header-cta" href="cat_form.php">
            <?= icon('plus', 'icon icon-sm') ?>
            <span>Добавить кошку</span>
        </a>
    </div>
</header>

<main class="container main-content">
    <?php if ($flashes !== []): ?>
        <div class="flash-stack">
            <?php foreach ($flashes as $flash): ?>
                <?php $type = $flash['type'] === 'error' ? 'error' : 'success'; ?>
                <div class="flash flash--<?= e($type) ?>" role="status">
                    <span class="flash-icon">
                        <?= icon($type === 'error' ? 'alert' : 'check') ?>
                    </span>
                    <span class="flash-text"><?= e($flash['message']) ?></span>
                    <button class="flash-close" type="button" data-dismiss-flash aria-label="Закрыть уведомление">&times;</button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
