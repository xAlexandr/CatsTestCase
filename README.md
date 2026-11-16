# Мурлыка — учёт кошек и родословных

Тестовое задание: веб-приложение на PHP для учёта кошек. У каждой кошки есть пол, возраст, необязательная мать и любое количество отцов. Мать и отцы выбираются из общей картотеки, поэтому одна и та же кошка может быть матерью нескольких питомцев, а один кот — отцом многих.

## Возможности

- добавление, редактирование и удаление кошек;
- выбор матери из кошек картотеки с защитой от циклов;
- несколько отцов у одной кошки через связь many-to-many;
- справочник отцов: добавление, удаление, количество привязанных кошек;
- фильтры по имени, полу и возрасту, сортировка и статистика;
- серверная валидация всех данных;
- защита от SQL-инъекций, XSS и CSRF;
- адаптивный интерфейс без внешних зависимостей.

## Стек

- PHP 8.3+ (`pdo_mysql`, `mbstring`, `curl`), в Docker используется PHP 8.4;
- MySQL 8.4 или MariaDB 11+;
- Docker Compose для запуска приложения и тестов;
- PHPUnit 12;
- HTML, CSS, ванильный JavaScript.

## Быстрый запуск в Docker

Понадобятся Docker Engine и плагин Docker Compose.

Если команды Docker возвращают `permission denied` для `/var/run/docker.sock`, добавьте пользователя в группу `docker` и перелогиньтесь либо выполняйте команды через `sudo`.

```bash
git clone git@github.com:xAlexandr/CatsTestCase.git
cd CatsTestCase
docker compose up --build -d
```

В репозитории уже лежит готовый `.env` с демонстрационными паролями, поэтому проект запускается сразу. Пользователь БД фиксированный — `cat_app`. Если хотите сменить пароли, отредактируйте `.env`; шаблон находится в `.env.example`.

Приложение: **http://localhost:8080** (порт меняется через `APP_PORT` в `.env`).

При первом запуске MySQL автоматически:

1. создаёт базу `cats_db` и пользователя `cat_app`;
2. применяет `database/structure.sql`;
3. загружает демо-данные из `database/seed.sql`;
4. создаёт тестовую базу `cats_db_test` и выдаёт на неё права пользователю `cat_app`.

Данные MySQL хранятся в named volume. Команды управления:

```bash
docker compose logs -f app       # логи приложения
docker compose exec db mysql -ucat_app -p cats_db
docker compose down              # остановить, данные сохранить
docker compose down -v           # остановить и удалить БД вместе с volume
```

После `down -v` при следующем `up` база будет создана и заполнена заново.

### Тесты в Docker

```bash
docker compose --profile tests run --rm --build tests
```

Тестовая база `cats_db_test` создаётся при инициализации MySQL. Каждый тест пересоздаёт таблицы, поэтому рабочая база `cats_db` не затрагивается. Ожидаемый результат:

```text
OK (90 tests, 283 assertions)
```

## Локальный запуск

Требования: PHP 8.3+ с расширениями `pdo_mysql`, `mbstring`, `curl`, `dom`, `xml`, `xmlwriter`, Composer и MySQL/MariaDB.

### 1. База данных

Подключитесь к MySQL под администратором и создайте пользователя:

```sql
CREATE USER IF NOT EXISTS 'cat_app'@'localhost' IDENTIFIED BY 'change-me';
GRANT SELECT, INSERT, UPDATE, DELETE ON cats_db.* TO 'cat_app'@'localhost';
```

Создайте и заполните рабочую базу. Команду нужно выполнять **из корня проекта**, потому что `schema.sql` использует относительные пути `SOURCE`:

```bash
mysql -u root -p < database/schema.sql
```

Альтернативно можно применить файлы по отдельности:

```bash
mysql -u root -p cats_db < database/structure.sql
mysql -u root -p cats_db < database/seed.sql
```

Для локальных тестов создайте отдельную базу и выдайте на неё полные права:

```sql
CREATE DATABASE IF NOT EXISTS cats_db_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON cats_db_test.* TO 'cat_app'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Приложение

```bash
composer install
cp config.example.php config.local.php
```

Укажите в `config.local.php` пароль пользователя `cat_app`. Файл исключён из Git. Можно вместо него задать переменные окружения `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` — они имеют приоритет над `config.local.php`.

```bash
php -S 127.0.0.1:8000
```

Приложение: **http://127.0.0.1:8000**.

### 3. Тесты

```bash
composer test
```

Или напрямую:

```bash
vendor/bin/phpunit
```

Тесты используют базу из `TEST_DB_NAME` (по умолчанию `cats_db_test`). Имя обязано заканчиваться на `_test`, иначе запуск будет остановлен. Перед каждым тестом таблицы пересоздаются, поэтому не направляйте тесты на рабочую базу.

## Переменные окружения

| Переменная | Назначение | По умолчанию |
| --- | --- | --- |
| `DB_HOST` | хост MySQL | `localhost` |
| `DB_NAME` | имя базы | `cats_db` |
| `DB_USER` | пользователь MySQL | `cat_app` |
| `DB_PASS` | пароль MySQL | из `config.local.php` |
| `TEST_DB_NAME` | база для тестов | `cats_db_test` |
| `APP_PORT` | порт приложения в Docker | `8080` |
| `MYSQL_ROOT_PASSWORD` | root-пароль MySQL в Docker | обязательна |
| `DB_PASS` | пароль `cat_app` в Docker | обязательна |

## Модель данных

```text
cats
  id, name, sex, age, mother_id -> cats.id, created_at
  mother_id: ON DELETE SET NULL

fathers
  id, name UNIQUE, created_at

cat_fathers
  cat_id -> cats.id      ON DELETE CASCADE
  father_id -> fathers.id ON DELETE CASCADE
  PRIMARY KEY (cat_id, father_id)
```

Ограничения:

- мать может быть только кошкой (`sex = female`);
- кошка не может быть матерью самой себе и потомком в собственной цепочке;
- кошку, у которой уже есть потомство, нельзя перевести в пол `male`;
- имена отцов уникальны;
- удаление отца удаляет только связи, сами кошки остаются;
- удаление матери обнуляет `mother_id` у детей.

## Структура проекта

```text
.
├── assets/                  # CSS и JavaScript
├── database/
│   ├── structure.sql        # схема без данных
│   ├── seed.sql             # демо-данные
│   └── schema.sql           # создание БД + schema + seed
├── docker/mysql/            # init-скрипт тестовой БД
├── partials/                # общие header/footer
├── tests/
│   ├── Support/             # тестовая БД, HTTP-клиент, тестовый сервер
│   ├── Unit/                # тесты хелперов
│   └── Integration/         # тесты БД и HTTP-сценариев
├── cat_delete.php           # удаление кошки
├── cat_form.php             # добавление и редактирование
├── fathers.php              # справочник отцов
├── index.php                # список, фильтры, статистика
├── config.php               # конфигурация и env-override
├── db.php                   # PDO-подключение
├── helpers.php              # общие функции
├── Dockerfile
├── docker-compose.yml
├── phpunit.xml
└── composer.json
```

## Безопасность

- все SQL-запросы выполняются через подготовленные выражения PDO;
- вывод экранируется через `htmlspecialchars`;
- изменения выполняются только POST-запросами с CSRF-токеном;
- входные данные проверяются на сервере, SQL-фильтры ограничены белым списком;
- тестовая база защищена проверкой суффикса `_test`;
- `config.local.php` не попадает в репозиторий и Docker-образ;
- `.env` содержит только демонстрационные пароли, исключён из Docker-образа и не подходит для production.

Приложение не содержит аутентификации: это демонстрационный проект для локального запуска, а не готовый к публичному размещению сервис.
