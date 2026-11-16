CREATE DATABASE IF NOT EXISTS cats_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cats_db;

SOURCE database/structure.sql;
SOURCE database/seed.sql;
