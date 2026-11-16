<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class DatabaseSchemaTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        TestDatabase::reset();
        $this->pdo = TestDatabase::pdo();
    }

    public function testMotherReferenceIsClearedWhenMotherIsDeleted(): void
    {
        $motherId = $this->insertCat('Мама', 'female', 4);
        $childId = $this->insertCat('Котёнок', 'male', 1, $motherId);

        $this->pdo->prepare('DELETE FROM cats WHERE id = ?')->execute([$motherId]);

        $child = $this->findCat($childId);

        self::assertNotNull($child);
        self::assertNull($child['mother_id']);
    }

    public function testMotherReferenceMustPointToExistingCat(): void
    {
        try {
            $this->insertCat('Сирота', 'female', 1, 999999);
            self::fail('Внешний ключ должен запрещать ссылку на несуществующую мать.');
        } catch (PDOException $exception) {
            self::assertSame('23000', (string) $exception->getCode());
        }
    }

    public function testCatCanHaveManyFathersAndFatherCanHaveManyCats(): void
    {
        $firstCat = $this->insertCat('Первая', 'female', 2);
        $secondCat = $this->insertCat('Вторая', 'female', 3);
        $firstFather = $this->insertFather('Первый отец');
        $secondFather = $this->insertFather('Второй отец');

        $this->linkCatAndFather($firstCat, $firstFather);
        $this->linkCatAndFather($firstCat, $secondFather);
        $this->linkCatAndFather($secondCat, $firstFather);

        self::assertSame(['Второй отец', 'Первый отец'], $this->fatherNamesForCat($firstCat));
        self::assertSame(['Первый отец'], $this->fatherNamesForCat($secondCat));
        self::assertSame(2, $this->fatherCountForFather($firstFather));
        self::assertSame(1, $this->fatherCountForFather($secondFather));
    }

    public function testDeletingCatRemovesItsFatherLinks(): void
    {
        $catId = $this->insertCat('Кот', 'male', 2);
        $fatherId = $this->insertFather('Отец');
        $this->linkCatAndFather($catId, $fatherId);

        $this->pdo->prepare('DELETE FROM cats WHERE id = ?')->execute([$catId]);

        self::assertSame(0, $this->linkCount(catId: $catId));
        self::assertNotNull($this->findFather($fatherId));
    }

    public function testDeletingFatherRemovesLinksFromAllCats(): void
    {
        $firstCat = $this->insertCat('Первая', 'female', 2);
        $secondCat = $this->insertCat('Вторая', 'female', 3);
        $fatherId = $this->insertFather('Отец');

        $this->linkCatAndFather($firstCat, $fatherId);
        $this->linkCatAndFather($secondCat, $fatherId);

        $this->pdo->prepare('DELETE FROM fathers WHERE id = ?')->execute([$fatherId]);

        self::assertSame(0, $this->linkCount(fatherId: $fatherId));
        self::assertNotNull($this->findCat($firstCat));
        self::assertNotNull($this->findCat($secondCat));
    }

    public function testFatherNameMustBeUnique(): void
    {
        $this->insertFather('Барсик');

        try {
            $this->insertFather('Барсик');
            self::fail('Уникальный индекс должен запрещать двух отцов с одинаковой кличкой.');
        } catch (PDOException $exception) {
            self::assertSame('23000', (string) $exception->getCode());
        }
    }

    public function testCatFatherPairMustBeUnique(): void
    {
        $catId = $this->insertCat('Кот', 'male', 2);
        $fatherId = $this->insertFather('Отец');
        $this->linkCatAndFather($catId, $fatherId);

        try {
            $this->linkCatAndFather($catId, $fatherId);
            self::fail('Составной первичный ключ должен запрещать дубли связей.');
        } catch (PDOException $exception) {
            self::assertSame('23000', (string) $exception->getCode());
        }
    }

    public function testFatherLinkMustReferenceExistingFather(): void
    {
        $catId = $this->insertCat('Кот', 'male', 2);

        try {
            $this->linkCatAndFather($catId, 999999);
            self::fail('Внешний ключ должен запрещать ссылку на несуществующего отца.');
        } catch (PDOException $exception) {
            self::assertSame('23000', (string) $exception->getCode());
        }
    }

    public function testCatsTableHasExpectedColumnsAndTypes(): void
    {
        $statement = $this->pdo->query(
            "SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable, EXTRA AS extra
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cats'"
        );

        $columns = [];

        foreach ($statement->fetchAll() as $column) {
            $columns[$column['name']] = $column;
        }

        foreach (['id', 'name', 'sex', 'age', 'mother_id'] as $requiredColumn) {
            self::assertArrayHasKey($requiredColumn, $columns);
        }

        self::assertSame("enum('male','female')", $columns['sex']['type']);
        self::assertStringStartsWith('tinyint', $columns['age']['type']);
        self::assertStringContainsString('unsigned', $columns['age']['type']);
        self::assertSame('YES', $columns['mother_id']['nullable']);
        self::assertStringContainsString('auto_increment', $columns['id']['extra']);
    }

    public function testForeignKeysHaveExpectedDeleteRules(): void
    {
        $statement = $this->pdo->query(
            'SELECT CONSTRAINT_NAME AS name, DELETE_RULE AS delete_rule
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()'
        );

        $rules = [];

        foreach ($statement->fetchAll() as $row) {
            $rules[$row['name']] = $row['delete_rule'];
        }

        self::assertSame('SET NULL', $rules['fk_cats_mother'] ?? null);
        self::assertSame('CASCADE', $rules['fk_cat_fathers_cat'] ?? null);
        self::assertSame('CASCADE', $rules['fk_cat_fathers_father'] ?? null);
    }

    public function testCatFathersTableHasCompositePrimaryKey(): void
    {
        $statement = $this->pdo->query(
            "SELECT COLUMN_NAME AS name
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'cat_fathers'
               AND INDEX_NAME = 'PRIMARY'
             ORDER BY SEQ_IN_INDEX"
        );

        self::assertSame(['cat_id', 'father_id'], array_column($statement->fetchAll(), 'name'));
    }

    public function testAgeZeroIsStoredCorrectly(): void
    {
        $catId = $this->insertCat('Малыш', 'male', 0);
        $cat = $this->findCat($catId);

        self::assertNotNull($cat);
        self::assertSame(0, (int) $cat['age']);
    }

    private function insertCat(string $name, string $sex, int $age, ?int $motherId = null): int
    {
        $statement = $this->pdo->prepare('INSERT INTO cats (name, sex, age, mother_id) VALUES (?, ?, ?, ?)');
        $statement->execute([$name, $sex, $age, $motherId]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertFather(string $name): int
    {
        $statement = $this->pdo->prepare('INSERT INTO fathers (name) VALUES (?)');
        $statement->execute([$name]);

        return (int) $this->pdo->lastInsertId();
    }

    private function linkCatAndFather(int $catId, int $fatherId): void
    {
        $statement = $this->pdo->prepare('INSERT INTO cat_fathers (cat_id, father_id) VALUES (?, ?)');
        $statement->execute([$catId, $fatherId]);
    }

    private function findCat(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, name, age, mother_id FROM cats WHERE id = ?');
        $statement->execute([$id]);
        $cat = $statement->fetch();

        return $cat === false ? null : $cat;
    }

    private function findFather(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, name FROM fathers WHERE id = ?');
        $statement->execute([$id]);
        $father = $statement->fetch();

        return $father === false ? null : $father;
    }

    private function fatherNamesForCat(int $catId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT f.name
             FROM cat_fathers cf
             INNER JOIN fathers f ON f.id = cf.father_id
             WHERE cf.cat_id = ?
             ORDER BY f.name'
        );
        $statement->execute([$catId]);

        return array_column($statement->fetchAll(), 'name');
    }

    private function fatherCountForFather(int $fatherId): int
    {
        return $this->linkCount(fatherId: $fatherId);
    }

    private function linkCount(?int $catId = null, ?int $fatherId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM cat_fathers';
        $where = [];
        $params = [];

        if ($catId !== null) {
            $where[] = 'cat_id = ?';
            $params[] = $catId;
        }

        if ($fatherId !== null) {
            $where[] = 'father_id = ?';
            $params[] = $fatherId;
        }

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }
}
