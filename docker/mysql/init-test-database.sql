CREATE DATABASE IF NOT EXISTS cats_db_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON cats_db_test.* TO 'cat_app'@'%';
FLUSH PRIVILEGES;
