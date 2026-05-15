ALTER TABLE `{prefix}webauthn_credentials`
  ADD COLUMN `name` VARCHAR(100) NOT NULL DEFAULT 'Security Key' AFTER `credential_type`,
  ADD COLUMN `aaguid` VARCHAR(32) NOT NULL DEFAULT '' AFTER `sign_count`;
