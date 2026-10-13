-- Configuração não destrutiva da página original. Migração pelo runner CLI.
ALTER TABLE referencias_arquitetonicas ADD COLUMN area_util_json JSON NULL;
ALTER TABLE referencias_arquitetonicas ADD COLUMN layers_json JSON NULL;
ALTER TABLE referencias_arquitetonicas ADD COLUMN has_layers TINYINT(1) NULL;
ALTER TABLE referencias_arquitetonicas ADD COLUMN versao_config INT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE referencias_arquitetonicas ADD COLUMN atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
