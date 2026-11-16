SET NAMES utf8mb4;

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
