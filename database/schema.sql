CREATE DATABASE IF NOT EXISTS cats_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cats_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS cat_fathers;
DROP TABLE IF EXISTS fathers;
DROP TABLE IF EXISTS cats;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE cats (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    sex ENUM('male', 'female') NOT NULL,
    age TINYINT UNSIGNED NOT NULL DEFAULT 0,
    mother_id INT UNSIGNED NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cats_sex (sex),
    KEY idx_cats_age (age),
    KEY idx_cats_mother (mother_id),
    CONSTRAINT fk_cats_mother FOREIGN KEY (mother_id) REFERENCES cats (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE = InnoDB;

CREATE TABLE fathers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fathers_name (name)
) ENGINE = InnoDB;

CREATE TABLE cat_fathers (
    cat_id INT UNSIGNED NOT NULL,
    father_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (cat_id, father_id),
    KEY idx_cat_fathers_father (father_id),
    CONSTRAINT fk_cat_fathers_cat FOREIGN KEY (cat_id) REFERENCES cats (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_cat_fathers_father FOREIGN KEY (father_id) REFERENCES fathers (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE = InnoDB;

INSERT INTO fathers (id, name) VALUES
    (1, 'Барсик'),
    (2, 'Снежок'),
    (3, 'Мурзик'),
    (4, 'Огонёк');

INSERT INTO cats (id, name, sex, age, mother_id) VALUES
    (1, 'Мурка', 'female', 5, NULL),
    (2, 'Луна', 'female', 3, NULL),
    (3, 'Тимка', 'male', 6, NULL),
    (4, 'Симба', 'male', 2, 1),
    (5, 'Марта', 'female', 1, 2),
    (6, 'Персик', 'male', 0, 1);

INSERT INTO cat_fathers (cat_id, father_id) VALUES
    (4, 1),
    (4, 2),
    (5, 3),
    (6, 2),
    (6, 4);
