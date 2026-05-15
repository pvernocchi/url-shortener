CREATE TABLE IF NOT EXISTS `{prefix}user_settings` (
  `user_id` INT UNSIGNED NOT NULL,
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `key`),
  CONSTRAINT `fk_user_settings_user` FOREIGN KEY (`user_id`) REFERENCES `{prefix}users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `{prefix}user_settings` (`user_id`, `key`, `value`)
SELECT `id`, 'mfa_totp_enabled',
       -- Legacy behavior challenged users when a TOTP secret existed, so we migrate those users as enabled.
       CASE
         WHEN `mfa_totp_secret` IS NULL OR `mfa_totp_secret` = '' THEN '0'
         ELSE '1'
       END
FROM `{prefix}users`
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW();

INSERT INTO `{prefix}user_settings` (`user_id`, `key`, `value`)
SELECT `id`, 'profile_edit_enabled', '1'
FROM `{prefix}users`
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW();

CREATE TABLE IF NOT EXISTS `{prefix}audit_events` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `actor_user_id` INT UNSIGNED NULL,
  `target_user_id` INT UNSIGNED NULL,
  `scope` ENUM('system','profile') NOT NULL,
  `event_key` VARCHAR(100) NOT NULL,
  `old_value` TEXT NULL,
  `new_value` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_created_at` (`created_at`),
  INDEX `idx_audit_scope` (`scope`),
  INDEX `idx_audit_actor` (`actor_user_id`),
  INDEX `idx_audit_target` (`target_user_id`),
  CONSTRAINT `fk_audit_actor_user` FOREIGN KEY (`actor_user_id`) REFERENCES `{prefix}users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_audit_target_user` FOREIGN KEY (`target_user_id`) REFERENCES `{prefix}users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
