CREATE DATABASE IF NOT EXISTS star_assessment
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE star_assessment;

CREATE TABLE IF NOT EXISTS consent_logs (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guid             CHAR(36)      NOT NULL,
    consent_status   ENUM('accepted','declined') NOT NULL,
    consent_version  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    consented_at     DATETIME      NOT NULL,
    ip_address       VARCHAR(45)   DEFAULT NULL,
    user_agent       VARCHAR(255)  DEFAULT NULL,
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_guid (guid),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_users (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(50)  NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until   DATETIME     DEFAULT NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;