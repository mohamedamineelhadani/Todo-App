-- Luxy To-Do App — database schema
-- Fresh install: run this whole file once (phpMyAdmin > Import, or `mysql -u root < database.sql`).
--
-- Upgrading an existing todo_app database? Old accounts stored plain/unsafe recovery data,
-- so the simplest path is to drop the old DB and re-create it:
--   DROP DATABASE todo_app;   then run this file.

CREATE DATABASE IF NOT EXISTS todo_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE todo_app;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,          -- password_hash()
    recovery_hash VARCHAR(255) NOT NULL,          -- password_hash() of the recovery code
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tasks (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    task       VARCHAR(500) NOT NULL,
    completed  TINYINT(1) NOT NULL DEFAULT 0,
    priority   ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    due_date   DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_done (user_id, completed)
) ENGINE=InnoDB;

-- Failed login / recovery attempts, used to slow down brute-force guessing.
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip           VARCHAR(45)  NOT NULL,
    email        VARCHAR(255) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip, attempted_at),
    INDEX idx_email_time (email, attempted_at)
) ENGINE=InnoDB;
