<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use RuntimeException;

final class TestDatabase
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (!str_ends_with(DB_NAME, '_test')) {
            throw new RuntimeException('Тесты можно запускать только с базой данных, имя которой заканчивается на _test.');
        }

        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

        self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }

    public static function reset(bool $withDemoData = false): void
    {
        $pdo = self::pdo();
        self::executeSqlFile(dirname(__DIR__, 2) . '/database/structure.sql', $pdo);

        if ($withDemoData) {
            self::executeSqlFile(dirname(__DIR__, 2) . '/database/seed.sql', $pdo);
        }
    }

    public static function findCat(int $id): ?array
    {
        $statement = self::pdo()->prepare('SELECT * FROM cats WHERE id = ?');
        $statement->execute([$id]);
        $cat = $statement->fetch();

        return $cat === false ? null : $cat;
    }

    public static function findCatByName(string $name): ?array
    {
        $statement = self::pdo()->prepare('SELECT * FROM cats WHERE name = ?');
        $statement->execute([$name]);
        $cat = $statement->fetch();

        return $cat === false ? null : $cat;
    }

    public static function findFatherByName(string $name): ?array
    {
        $statement = self::pdo()->prepare('SELECT * FROM fathers WHERE name = ?');
        $statement->execute([$name]);
        $father = $statement->fetch();

        return $father === false ? null : $father;
    }

    public static function catFatherIds(int $catId): array
    {
        $statement = self::pdo()->prepare('SELECT father_id FROM cat_fathers WHERE cat_id = ? ORDER BY father_id');
        $statement->execute([$catId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function countRows(string $table, string $where = '1', array $params = []): int
    {
        $allowedTables = ['cats', 'fathers', 'cat_fathers'];

        if (!in_array($table, $allowedTables, true)) {
            throw new RuntimeException('Недопустимая таблица для подсчёта: ' . $table);
        }

        $statement = self::pdo()->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $where);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    private static function executeSqlFile(string $path, PDO $pdo): void
    {
        $sql = file_get_contents($path);

        if ($sql === false) {
            throw new RuntimeException('Не удалось прочитать SQL-файл: ' . $path);
        }

        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            static fn (string $statement): bool => $statement !== ''
        );

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }
}
