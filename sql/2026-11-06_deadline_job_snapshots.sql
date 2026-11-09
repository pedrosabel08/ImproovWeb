-- Após 2026-11-06_render_ef_nas.sql. Somente em janela autorizada.
CREATE TABLE IF NOT EXISTS render_job_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deadline_job_id VARCHAR(64) NOT NULL,
    tentativa_id BIGINT UNSIGNED NOT NULL,
    imagem_id INT NOT NULL,
    formato INT NOT NULL DEFAULT 1,
    origem VARCHAR(32) NOT NULL,
    estado VARCHAR(32) NOT NULL,
    conteudo_json LONGTEXT NOT NULL,
    conteudo_sha256 CHAR(64) NOT NULL,
    normalizado_json LONGTEXT NOT NULL,
    capturado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_snapshot_job (deadline_job_id),
    UNIQUE KEY uniq_snapshot_tentativa (tentativa_id),
    KEY idx_snapshot_imagem (imagem_id),
    CONSTRAINT fk_snapshot_tentativa FOREIGN KEY (tentativa_id) REFERENCES render_tentativas(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS render_job_snapshot_capturas (
    deadline_job_id VARCHAR(64) PRIMARY KEY,
    tentativa_id BIGINT UNSIGNED NOT NULL,
    imagem_id INT NOT NULL,
    estado VARCHAR(24) NOT NULL DEFAULT 'PENDENTE',
    prioridade INT NOT NULL DEFAULT 0,
    tentativas INT NOT NULL DEFAULT 0,
    ultimo_erro TEXT NULL,
    disponivel_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    iniciado_em DATETIME NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_captura_tentativa FOREIGN KEY (tentativa_id) REFERENCES render_tentativas(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS render_job_snapshot_eventos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deadline_job_id VARCHAR(64) NOT NULL,
    tentativa_id BIGINT UNSIGNED NOT NULL,
    estado VARCHAR(32) NOT NULL,
    mensagem TEXT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_snapshot_evento_job (deadline_job_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE render_ef_solicitacoes
    ADD snapshot_id BIGINT UNSIGNED NULL,
    ADD origem_tentativa_id BIGINT UNSIGNED NULL,
    ADD arquivo_confirmado_por INT NULL,
    ADD arquivo_confirmado_em DATETIME NULL,
    ADD CONSTRAINT fk_ef_snapshot FOREIGN KEY (snapshot_id) REFERENCES render_job_snapshots(id) ON DELETE RESTRICT;
