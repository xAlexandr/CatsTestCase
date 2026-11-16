<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

function wouldCreateMotherCycle(PDO $pdo, int $catId, int $motherId): bool
{
    $statement = $pdo->prepare('SELECT mother_id FROM cats WHERE id = ?');
    $visited = [];
    $current = $motherId;

    while ($current !== null) {
        if ($current === $catId || isset($visited[$current])) {
            return true;
        }

        $visited[$current] = true;
        $statement->execute([$current]);
        $mother = $statement->fetchColumn();

        if ($mother === false) {
            return false;
        }

        $current = $mother !== null ? (int) $mother : null;
    }

    return false;
}

$id = null;
$rawId = $_POST['id'] ?? $_GET['id'] ?? null;

if ($rawId !== null && $rawId !== '') {
    $validatedId = is_scalar($rawId)
        ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;

    if ($validatedId === false) {
        http_response_code(404);
        exit('Кошка не найдена.');
    }

    $id = (int) $validatedId;
}

$cat = null;

if ($id !== null) {
    $statement = db()->prepare('SELECT id, name, sex, age, mother_id FROM cats WHERE id = ?');
    $statement->execute([$id]);
    $cat = $statement->fetch();

    if ($cat === false) {
        http_response_code(404);
        exit('Кошка не найдена.');
    }
}

$values = [
    'name' => (string) ($cat['name'] ?? ''),
    'sex' => (string) ($cat['sex'] ?? 'female'),
    'age' => $cat !== null ? (string) $cat['age'] : '',
    'mother_id' => $cat !== null && $cat['mother_id'] !== null ? (string) $cat['mother_id'] : '',
    'father_ids' => [],
];

if ($id !== null) {
    $statement = db()->prepare('SELECT father_id FROM cat_fathers WHERE cat_id = ? ORDER BY father_id');
    $statement->execute([$id]);
    $values['father_ids'] = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $postedName = $_POST['name'] ?? '';
    $postedSex = $_POST['sex'] ?? 'female';
    $postedAge = $_POST['age'] ?? '';
    $postedMother = $_POST['mother_id'] ?? '';

    $values['name'] = is_string($postedName) ? trim($postedName) : '';
    $values['sex'] = is_string($postedSex) ? $postedSex : '';
    $values['age'] = is_string($postedAge) ? trim($postedAge) : '';
    $values['mother_id'] = is_string($postedMother) ? trim($postedMother) : '';

    $postedFathers = $_POST['father_ids'] ?? [];
    $postedFathers = is_array($postedFathers) ? $postedFathers : [];
    $fatherIds = [];

    foreach ($postedFathers as $postedFather) {
        if (!is_scalar($postedFather)) {
            continue;
        }

        $validatedFather = filter_var($postedFather, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($validatedFather !== false) {
            $fatherIds[] = (int) $validatedFather;
        }
    }

    $values['father_ids'] = array_values(array_unique($fatherIds));

    if ($values['name'] === '') {
        $errors[] = 'Укажите кличку кошки.';
    } elseif (mb_strlen($values['name']) > 100) {
        $errors[] = 'Кличка не должна превышать 100 символов.';
    }

    if (!in_array($values['sex'], ['female', 'male'], true)) {
        $errors[] = 'Выберите пол кошки.';
    }

    $age = filter_var($values['age'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => 40],
    ]);

    if ($age === false) {
        $errors[] = 'Возраст должен быть целым числом от 0 до 40 лет.';
    }

    $motherId = null;

    if ($values['mother_id'] !== '') {
        $validatedMother = filter_var($values['mother_id'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($validatedMother === false) {
            $errors[] = 'Выбрана некорректная мать.';
        } else {
            $motherId = (int) $validatedMother;

            if ($id !== null && $motherId === $id) {
                $errors[] = 'Кошка не может быть собственной матерью.';
            } else {
                $statement = db()->prepare('SELECT name, sex FROM cats WHERE id = ?');
                $statement->execute([$motherId]);
                $mother = $statement->fetch();

                if ($mother === false) {
                    $errors[] = 'Выбранная мать не найдена.';
                } elseif ($mother['sex'] !== 'female') {
                    $errors[] = 'В качестве матери можно выбрать только кошку.';
                } elseif ($id !== null && wouldCreateMotherCycle(db(), $id, $motherId)) {
                    $errors[] = 'Нельзя выбрать мать: получится замкнутая цепочка родства.';
                }
            }
        }
    }

    if ($id !== null && $values['sex'] === 'male') {
        $statement = db()->prepare('SELECT COUNT(*) FROM cats WHERE mother_id = ?');
        $statement->execute([$id]);

        if ((int) $statement->fetchColumn() > 0) {
            $errors[] = 'Нельзя указать пол «кот»: эта кошка уже отмечена матерью других кошек.';
        }
    }

    if ($values['father_ids'] !== []) {
        $placeholders = implode(',', array_fill(0, count($values['father_ids']), '?'));
        $statement = db()->prepare("SELECT id FROM fathers WHERE id IN ($placeholders) ORDER BY id");
        $statement->execute($values['father_ids']);
        $existingFatherIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));

        if (count($existingFatherIds) !== count($values['father_ids'])) {
            $errors[] = 'Некоторые отцы не найдены в справочнике.';
            $values['father_ids'] = $existingFatherIds;
        }
    }

    if ($errors === []) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            if ($id === null) {
                $statement = $pdo->prepare('INSERT INTO cats (name, sex, age, mother_id) VALUES (?, ?, ?, ?)');
                $statement->execute([$values['name'], $values['sex'], (int) $age, $motherId]);
                $savedId = (int) $pdo->lastInsertId();
            } else {
                $statement = $pdo->prepare('UPDATE cats SET name = ?, sex = ?, age = ?, mother_id = ? WHERE id = ?');
                $statement->execute([$values['name'], $values['sex'], (int) $age, $motherId, $id]);
                $savedId = $id;
            }

            $pdo->prepare('DELETE FROM cat_fathers WHERE cat_id = ?')->execute([$savedId]);

            if ($values['father_ids'] !== []) {
                $insertFather = $pdo->prepare('INSERT INTO cat_fathers (cat_id, father_id) VALUES (?, ?)');

                foreach ($values['father_ids'] as $fatherId) {
                    $insertFather->execute([$savedId, $fatherId]);
                }
            }

            $pdo->commit();

            flash(
                'success',
                $id === null
                    ? 'Кошка «' . $values['name'] . '» добавлена в картотеку.'
                    : 'Изменения для кошки «' . $values['name'] . '» сохранены.'
            );

            redirect('index.php');
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Cat save failed: ' . $exception->getMessage());
            $errors[] = 'Не удалось сохранить данные. Попробуйте ещё раз.';
        }
    }
}

$motherSql = "SELECT id, name, age FROM cats WHERE sex = 'female'";
$motherParams = [];

if ($id !== null) {
    $motherSql .= ' AND id <> ?';
    $motherParams[] = $id;
}

$motherSql .= ' ORDER BY name ASC, id ASC';
$statement = db()->prepare($motherSql);
$statement->execute($motherParams);
$mothers = $statement->fetchAll();

$fathers = db()->query('SELECT id, name FROM fathers ORDER BY name ASC, id ASC')->fetchAll();
$selectedFatherIds = array_map('intval', $values['father_ids']);
$isEdit = $id !== null;

$pageTitle = $isEdit ? 'Редактирование кошки' : 'Новая кошка';
$activeNav = 'cats';
require __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <a class="back-link" href="index.php"><?= icon('arrow-left', 'icon icon-sm') ?>К картотеке</a>
        <h1><?= $isEdit ? 'Редактирование кошки' : 'Новая кошка' ?></h1>
        <p>
            <?= $isEdit
                ? 'Обновите данные питомца, его мать и возможных отцов.'
                : 'Заполните карточку нового питомца и при необходимости укажите родственные связи.' ?>
        </p>
    </div>
</div>

<div class="editor-layout">
    <div class="editor-main">
        <form
            class="panel form-panel"
            method="post"
            action="cat_form.php<?= $isEdit ? '?id=' . $id : '' ?>"
        >
            <?= csrf_field() ?>
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= $id ?>">
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="form-errors" role="alert">
                    <strong>Проверьте форму:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <section class="form-section">
                <div class="form-section-head">
                    <h2>Основное</h2>
                    <p>Кличка, пол и возраст питомца.</p>
                </div>

                <div class="form-grid">
                    <div class="field field--full">
                        <label for="cat-name">Кличка</label>
                        <input
                            class="input"
                            type="text"
                            id="cat-name"
                            name="name"
                            value="<?= e($values['name']) ?>"
                            maxlength="100"
                            placeholder="Например, Мурка"
                            required
                            autofocus
                        >
                    </div>

                    <fieldset class="field">
                        <legend>Пол</legend>
                        <div class="segmented">
                            <label>
                                <input
                                    type="radio"
                                    name="sex"
                                    value="female"
                                    <?= $values['sex'] === 'female' ? 'checked' : '' ?>
                                    required
                                >
                                <span><i class="dot dot--female"></i>Кошка</span>
                            </label>
                            <label>
                                <input
                                    type="radio"
                                    name="sex"
                                    value="male"
                                    <?= $values['sex'] === 'male' ? 'checked' : '' ?>
                                    required
                                >
                                <span><i class="dot dot--male"></i>Кот</span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="field">
                        <label for="cat-age">Возраст, полных лет</label>
                        <input
                            class="input"
                            type="number"
                            id="cat-age"
                            name="age"
                            value="<?= e($values['age']) ?>"
                            min="0"
                            max="40"
                            step="1"
                            placeholder="0"
                            required
                        >
                    </div>
                </div>
            </section>

            <section class="form-section">
                <div class="form-section-head">
                    <h2>Родители</h2>
                    <p>Мать выбирается из кошек картотеки, отцов можно указать несколько.</p>
                </div>

                <div class="form-grid">
                    <div class="field field--full">
                        <label for="cat-mother">Мать</label>
                        <select class="select" id="cat-mother" name="mother_id">
                            <option value="">Не указана</option>
                            <?php foreach ($mothers as $mother): ?>
                                <option
                                    value="<?= (int) $mother['id'] ?>"
                                    <?= (string) $mother['id'] === $values['mother_id'] ? 'selected' : '' ?>
                                >
                                    <?= e($mother['name']) ?> · <?= e(format_age((int) $mother['age'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="field-hint">В списке только кошки. Если нужной нет, сначала добавьте её в картотеку.</p>
                    </div>

                    <div class="field field--full">
                        <div class="field-label-row">
                            <span class="field-label">Отцы</span>
                            <?php if ($fathers !== []): ?>
                                <span class="field-counter">Можно отметить несколько</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($fathers === []): ?>
                            <div class="inline-empty">
                                В справочнике пока нет отцов.
                                <a href="fathers.php">Добавить первого отца</a>
                            </div>
                        <?php else: ?>
                            <?php if (count($fathers) > 6): ?>
                                <div class="input-with-icon father-filter">
                                    <?= icon('search', 'input-icon') ?>
                                    <input
                                        class="input"
                                        type="search"
                                        data-father-filter
                                        placeholder="Найти отца по кличке"
                                        aria-label="Поиск отца по кличке"
                                    >
                                </div>
                            <?php endif; ?>

                            <div class="check-grid">
                                <?php foreach ($fathers as $father): ?>
                                    <label class="check-card" data-father-item>
                                        <input
                                            type="checkbox"
                                            name="father_ids[]"
                                            value="<?= (int) $father['id'] ?>"
                                            <?= in_array((int) $father['id'], $selectedFatherIds, true) ? 'checked' : '' ?>
                                        >
                                        <span class="check-card-body"><?= e($father['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="inline-empty" data-father-empty hidden>Ничего не найдено.</div>
                        <?php endif; ?>

                        <p class="field-hint">Связь многие ко многим: у котёнка может быть несколько возможных отцов.</p>
                    </div>
                </div>
            </section>

            <div class="form-actions">
                <a class="btn btn-ghost" href="index.php">Отмена</a>
                <button class="btn btn-primary" type="submit">
                    <?= icon('check', 'icon icon-sm') ?>Сохранить
                </button>
            </div>
        </form>
    </div>

    <aside class="editor-aside">
        <div class="side-note">
            <span class="side-note-icon"><?= icon('info') ?></span>
            <h2>Подсказки</h2>
            <ul>
                <li>Мать — это кошка из картотеки. Выбрать кота в качестве матери нельзя.</li>
                <li>Отцов можно отметить сразу нескольких: это отражает особенности кошачьего поведения.</li>
                <li>Возраст указывается в полных годах, от 0 до 40.</li>
                <li>Если у кошки уже есть потомство, пол сменить на «кот» не получится.</li>
            </ul>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
