-- Migration: add missing columns to historico_cliente
-- This uses MySQL 8+ syntax `ADD COLUMN IF NOT EXISTS`.
-- If your MySQL version does not support `IF NOT EXISTS`, run the individual ALTER statements (or use phpMyAdmin).

ALTER TABLE `historico_cliente`
  ADD COLUMN IF NOT EXISTS `cliente_id` INT NOT NULL,
  ADD COLUMN IF NOT EXISTS `obra` VARCHAR(1024) NULL,
  ADD COLUMN IF NOT EXISTS `endereco` VARCHAR(1024) NULL,
  ADD COLUMN IF NOT EXISTS `data` VARCHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS `servico` TEXT NULL,
  ADD COLUMN IF NOT EXISTS `equipe` VARCHAR(255) NULL;

-- Fallback (remove IF NOT EXISTS if older MySQL; run each line separately if needed):
-- ALTER TABLE `historico_cliente` ADD COLUMN `cliente_id` INT NOT NULL;
-- ALTER TABLE `historico_cliente` ADD COLUMN `obra` VARCHAR(1024) NULL;
-- ALTER TABLE `historico_cliente` ADD COLUMN `data` VARCHAR(64) NULL;
-- ALTER TABLE `historico_cliente` ADD COLUMN `servico` TEXT NULL;
-- ALTER TABLE `historico_cliente` ADD COLUMN `equipe` VARCHAR(255) NULL;
