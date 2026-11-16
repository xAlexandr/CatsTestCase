<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $postedName = $_POST['name'] ?? '';
        $name = is_string($postedName) ? trim($postedName) : '';

        if ($name === '') {
            flash('error', 'Укажите кличку отца.');
        } elseif (mb_strlen($name) > 100) {
            flash('error', 'Кличка не должна превышать 100 символов.');
        } else {
            try {
                db()->prepare('INSERT INTO fathers (name) VALUES (?)')->execute([$name]);
                flash('success', 'Отец «' . $name . '» добавлен в справочник.');
            } catch (PDOException $exception) {
                $errorCode = $exception->errorInfo[1] ?? null;

                if ((int) $errorCode === 1062) {
                    flash('error', 'Отец с такой кличкой уже есть в справочнике.');
                } else {
                    error_log('Father add failed: ' . $exception->getMessage());
                    flash('error', 'Не удалось добавить отца. Попробуйте ещё раз.');
                }
            }
        }

        redirect('fathers.php');
    }

    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id === false) {
            flash('error', 'Отец не найден.');
            redirect('fathers.php');
        }

        $statement = db()->prepare('SELECT name FROM fathers WHERE id = ?');
        $statement->execute([$id]);
        $father = $statement->fetch();

        if ($father === false) {
            flash('error', 'Отец не найден.');
            redirect('fathers.php');
        }

        db()->prepare('DELETE FROM fathers WHERE id = ?')->execute([$id]);
        flash('success', 'Отец «' . $father['name'] . '» удалён из справочника.');
        redirect('fathers.php');
    }

    flash('error', 'Неизвестное действие.');
    redirect('fathers.php');
}

$fathers = db()->query(
    'SELECT f.id, f.name, COUNT(cf.cat_id) AS cats_count
     FROM fathers f
     LEFT JOIN cat_fathers cf ON cf.father_id = f.id
     GROUP BY f.id, f.name
     ORDER BY f.name ASC, f.id ASC'
)->fetchAll();

$totalFathers = count($fathers);
$linkedFathers = 0;

foreach ($fathers as $father) {
    if ((int) $father['cats_count'] > 0) {
        $linkedFathers++;
    }
}

$pageTitle = 'Отцы';
$activeNav = 'fathers';
require __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <a class="back-link" href="index.php"><?= icon('arrow-left', 'icon icon-sm') ?>К картотеке</a>
        <h1>Справочник отцов</h1>
        <p>
            Отцы хранятся отдельно от кошек, потому что у одного котёнка может быть
            несколько возможных отцов. Здесь можно пополнять справочник и удалять записи.
        </p>
    </div>
</div>

<div class="fathers-layout">
    <aside class="fathers-aside">
        <form class="panel form-panel father-add-panel" method="post" action="fathers.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div class="form-section-head">
                <h2>Новый отец</h2>
                <p>Добавьте кота в справочник, чтобы отмечать его в карточках котят.</p>
            </div>

            <div class="field">
                <label for="father-name">Кличка</label>
                <input
                    class="input"
                    type="text"
                    id="father-name"
                    name="name"
                    maxlength="100"
                    placeholder="Например, Барсик"
                    required
                >
            </div>

            <button class="btn btn-primary btn-block" type="submit">
                <?= icon('plus', 'icon icon-sm') ?>Добавить отца
            </button>
        </form>

        <div class="side-note side-note--compact">
            <span class="side-note-icon"><?= icon('info') ?></span>
            <h2>Зачем отдельно</h2>
            <p>
                Таблица fathers и промежуточная cat_fathers реализуют связь
                «многие ко многим». Один котёнок может иметь несколько отцов,
                а один отец — потомство от разных кошек.
            </p>
        </div>
    </aside>

    <section class="panel fathers-panel" aria-label="Список отцов">
        <div class="panel-head">
            <div>
                <h2>Все отцы</h2>
                <p>
                    Всего: <?= e(fathers_count($totalFathers)) ?> в справочнике ·
                    <?= e((string) $linkedFathers) ?> со связями
                </p>
            </div>
            <span class="count-pill"><?= $totalFathers ?></span>
        </div>

        <?php if ($fathers === []): ?>
            <div class="empty-state empty-state--compact">
                <span class="empty-icon"><?= icon('users') ?></span>
                <h3>Справочник пуст</h3>
                <p>Добавьте первого отца с помощью формы слева.</p>
            </div>
        <?php else: ?>
            <div class="father-list">
                <?php foreach ($fathers as $father): ?>
                    <?php
                    $fatherId = (int) $father['id'];
                    $catsCount = (int) $father['cats_count'];
                    $confirmText = $catsCount > 0
                        ? 'Удалить отца «' . $father['name'] . '»? Связи с кошками (' . cats_count($catsCount) . ') тоже будут удалены.'
                        : 'Удалить отца «' . $father['name'] . '» из справочника?';
                    ?>
                    <div class="father-row">
                        <span class="father-avatar"><?= e(initials((string) $father['name'])) ?></span>
                        <span class="father-info">
                            <span class="father-name"><?= e($father['name']) ?></span>
                            <span class="father-meta">
                                <?= $catsCount > 0
                                    ? e('Указан как отец: ' . cats_count($catsCount))
                                    : 'Пока ни к кому не привязан' ?>
                            </span>
                        </span>
                        <form method="post" action="fathers.php" data-confirm="<?= e($confirmText) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $fatherId ?>">
                            <button
                                class="icon-btn icon-btn--danger"
                                type="submit"
                                aria-label="Удалить отца <?= e($father['name']) ?>"
                                title="Удалить"
                            >
                                <?= icon('trash') ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
