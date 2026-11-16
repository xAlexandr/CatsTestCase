<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

$q = trim((string) ($_GET['q'] ?? ''));

$sex = (string) ($_GET['sex'] ?? '');
if (!in_array($sex, ['male', 'female'], true)) {
    $sex = '';
}

$ageMin = int_query('age_min', 0, 40);
$ageMax = int_query('age_max', 0, 40);

if ($ageMin !== null && $ageMax !== null && $ageMin > $ageMax) {
    [$ageMin, $ageMax] = [$ageMax, $ageMin];
}

$where = [];
$params = [];

if ($q !== '') {
    $where[] = 'c.name LIKE :name';
    $params['name'] = '%' . $q . '%';
}

if ($sex !== '') {
    $where[] = 'c.sex = :sex';
    $params['sex'] = $sex;
}

if ($ageMin !== null) {
    $where[] = 'c.age >= :age_min';
    $params['age_min'] = $ageMin;
}

if ($ageMax !== null) {
    $where[] = 'c.age <= :age_max';
    $params['age_max'] = $ageMax;
}

$sql = "
    SELECT
        c.id,
        c.name,
        c.sex,
        c.age,
        c.mother_id,
        m.name AS mother_name,
        (
            SELECT GROUP_CONCAT(f.name ORDER BY f.name SEPARATOR ', ')
            FROM cat_fathers cf
            INNER JOIN fathers f ON f.id = cf.father_id
            WHERE cf.cat_id = c.id
        ) AS fathers_names
    FROM cats c
    LEFT JOIN cats m ON m.id = c.mother_id
";

if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY c.name ASC, c.id ASC';

$statement = db()->prepare($sql);
$statement->execute($params);
$cats = $statement->fetchAll();

$stats = db()->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(sex = 'female'), 0) AS females,
        COALESCE(SUM(sex = 'male'), 0) AS males,
        COALESCE(AVG(age), 0) AS avg_age
     FROM cats"
)->fetch();

$total = (int) $stats['total'];
$hasFilters = $q !== '' || $sex !== '' || $ageMin !== null || $ageMax !== null;
$averageAge = $total > 0 ? number_format((float) $stats['avg_age'], 1, ',', '') : '—';

$pageTitle = 'Кошки';
$activeNav = 'cats';
require __DIR__ . '/partials/header.php';
?>

<section class="hero">
    <div class="hero-copy">
        <span class="eyebrow"><?= e(SITE_NAME) ?> · картотека питомцев</span>
        <h1>Все кошки <em>под одной крышей</em></h1>
        <p>
            Ведите учёт питомцев, храните родственные связи и находите нужных кошек
            за пару секунд с помощью фильтров по полу и возрасту.
        </p>
    </div>
    <div class="hero-art" aria-hidden="true">
        <div class="hero-paw">
            <?= icon('paw', 'hero-paw-icon') ?>
        </div>
        <span class="hero-chip hero-chip--one"><?= e(cats_count($total)) ?> в картотеке</span>
        <span class="hero-chip hero-chip--two">Родословные связи</span>
    </div>
</section>

<section class="stats-grid" aria-label="Статистика">
    <article class="stat-card">
        <span class="stat-icon stat-icon--accent"><?= icon('paw') ?></span>
        <span class="stat-body">
            <span class="stat-value"><?= $total ?></span>
            <span class="stat-label">Всего кошек</span>
        </span>
    </article>
    <article class="stat-card">
        <span class="stat-icon stat-icon--female"><?= icon('female') ?></span>
        <span class="stat-body">
            <span class="stat-value"><?= (int) $stats['females'] ?></span>
            <span class="stat-label">Кошек</span>
        </span>
    </article>
    <article class="stat-card">
        <span class="stat-icon stat-icon--male"><?= icon('male') ?></span>
        <span class="stat-body">
            <span class="stat-value"><?= (int) $stats['males'] ?></span>
            <span class="stat-label">Котов</span>
        </span>
    </article>
    <article class="stat-card">
        <span class="stat-icon stat-icon--sand"><?= icon('clock') ?></span>
        <span class="stat-body">
            <span class="stat-value"><?= e($averageAge) ?></span>
            <span class="stat-label">Средний возраст, лет</span>
        </span>
    </article>
</section>

<section class="panel filter-panel" aria-label="Фильтры">
    <form method="get" action="index.php" class="filter-form">
        <div class="field field--search">
            <label for="filter-q">Поиск по кличке</label>
            <div class="input-with-icon">
                <?= icon('search', 'input-icon') ?>
                <input
                    class="input"
                    type="search"
                    id="filter-q"
                    name="q"
                    value="<?= e($q) ?>"
                    placeholder="Например, Мурка"
                    autocomplete="off"
                >
            </div>
        </div>
        <div class="field">
            <label for="filter-sex">Пол</label>
            <select class="select" id="filter-sex" name="sex">
                <option value="">Любой</option>
                <option value="female" <?= $sex === 'female' ? 'selected' : '' ?>>Кошка</option>
                <option value="male" <?= $sex === 'male' ? 'selected' : '' ?>>Кот</option>
            </select>
        </div>
        <div class="field">
            <label for="filter-age-min">Возраст от</label>
            <input
                class="input"
                type="number"
                id="filter-age-min"
                name="age_min"
                min="0"
                max="40"
                value="<?= $ageMin !== null ? e((string) $ageMin) : '' ?>"
                placeholder="0"
            >
        </div>
        <div class="field">
            <label for="filter-age-max">Возраст до</label>
            <input
                class="input"
                type="number"
                id="filter-age-max"
                name="age_max"
                min="0"
                max="40"
                value="<?= $ageMax !== null ? e((string) $ageMax) : '' ?>"
                placeholder="40"
            >
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit"><?= icon('search', 'icon icon-sm') ?>Показать</button>
            <?php if ($hasFilters): ?>
                <a class="btn btn-ghost" href="index.php">Сбросить</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="cats-section" aria-label="Список кошек">
    <div class="section-head">
        <div>
            <h2><?= $hasFilters ? 'Результаты' : 'Картотека' ?></h2>
            <p class="section-sub">
                <?= $hasFilters
                    ? 'По выбранным условиям: ' . e(cats_count(count($cats)))
                    : 'Всего в реестре: ' . e(cats_count($total)) ?>
            </p>
        </div>
        <span class="count-pill"><?= count($cats) ?></span>
    </div>

    <?php if ($cats === []): ?>
        <div class="empty-state">
            <span class="empty-icon"><?= icon('paw') ?></span>
            <?php if ($hasFilters): ?>
                <h3>Ничего не нашлось</h3>
                <p>Попробуйте изменить условия фильтра или сбросить их.</p>
                <a class="btn btn-primary" href="index.php">Сбросить фильтры</a>
            <?php else: ?>
                <h3>Здесь пока пусто</h3>
                <p>Добавьте первую кошку, чтобы начать вести картотеку.</p>
                <a class="btn btn-primary" href="cat_form.php"><?= icon('plus', 'icon icon-sm') ?>Добавить кошку</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="cats-grid">
            <?php foreach ($cats as $cat): ?>
                <?php
                $catId = (int) $cat['id'];
                $catSex = (string) $cat['sex'];
                $catAge = (int) $cat['age'];
                $confirmText = 'Удалить кошку «' . $cat['name'] . '»? Запись и связи с отцами будут удалены.';
                ?>
                <article class="cat-card cat-card--<?= e($catSex) ?>">
                    <div class="cat-top">
                        <div class="cat-avatar cat-avatar--<?= e($catSex) ?>">
                            <?= e(initials((string) $cat['name'])) ?>
                        </div>
                        <div class="cat-title">
                            <h3><?= e($cat['name']) ?></h3>
                            <div class="badges">
                                <span class="badge badge-sex badge-sex--<?= e($catSex) ?>">
                                    <?= e(sex_label($catSex)) ?>
                                </span>
                                <span class="badge"><?= e(format_age($catAge)) ?></span>
                            </div>
                        </div>
                    </div>

                    <dl class="cat-details">
                        <div>
                            <dt>Мать</dt>
                            <dd>
                                <?php if ($cat['mother_name'] !== null): ?>
                                    <?= e($cat['mother_name']) ?>
                                <?php else: ?>
                                    <span class="is-empty">не указана</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt>Отцы</dt>
                            <dd>
                                <?php if ($cat['fathers_names'] !== null): ?>
                                    <?= e($cat['fathers_names']) ?>
                                <?php else: ?>
                                    <span class="is-empty">не указаны</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>

                    <div class="cat-actions">
                        <a class="btn btn-small btn-soft" href="cat_form.php?id=<?= $catId ?>">
                            <?= icon('edit', 'icon icon-sm') ?>Редактировать
                        </a>
                        <form method="post" action="cat_delete.php" data-confirm="<?= e($confirmText) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $catId ?>">
                            <button class="btn btn-small btn-danger-ghost" type="submit">
                                <?= icon('trash', 'icon icon-sm') ?>Удалить
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
