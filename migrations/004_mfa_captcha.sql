ALTER TABLE `{prefix}users`
  ADD COLUMN `mfa_totp_secret` VARCHAR(64) NULL AFTER `locked_until`;

CREATE TABLE IF NOT EXISTS `{prefix}webauthn_credentials` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `credential_id` VARCHAR(255) NOT NULL UNIQUE,
  `credential_type` ENUM('platform','security_key') NOT NULL,
  `public_key` TEXT NOT NULL,
  `sign_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_webauthn_user` FOREIGN KEY (`user_id`) REFERENCES `{prefix}users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
