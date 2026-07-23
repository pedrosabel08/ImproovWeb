-- Integra as pendencias do Fotográfico à consolidação operacional.
-- Executar após as migrations do MVP e da refatoração do planejamento.

ALTER TABLE fotografico_pendencia
    ADD COLUMN responsavel_cobranca_id INT NULL AFTER responsavel_id,
    ADD COLUMN proxima_cobranca_em DATETIME NULL AFTER criado_em,
    ADD COLUMN ultima_cobranca_em DATETIME NULL AFTER proxima_cobranca_em,
    ADD COLUMN erro_cobranca_em DATETIME NULL AFTER ultima_cobranca_em,
    ADD KEY idx_fotografico_pendencia_cobranca (status, proxima_cobranca_em, responsavel_cobranca_id),
    ADD CONSTRAINT fk_fotografico_pendencia_cobranca
        FOREIGN KEY (responsavel_cobranca_id) REFERENCES colaborador(idcolaborador) ON DELETE SET NULL;

CREATE TABLE fotografico_pendencia_cobranca_envio (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pendencia_id BIGINT UNSIGNED NOT NULL,
    responsavel_cobranca_id INT NOT NULL,
    referencia_cobranca_em DATETIME NOT NULL,
    enviado_em DATETIME NULL,
    status ENUM('RESERVADA', 'ENVIADA', 'ERRO', 'IGNORADA') NOT NULL DEFAULT 'RESERVADA',
    erro TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fotografico_cobranca_idempotente (pendencia_id, responsavel_cobranca_id, referencia_cobranca_em),
    KEY idx_fotografico_cobranca_pendencia (pendencia_id, enviado_em),
    CONSTRAINT fk_fotografico_cobranca_pendencia
        FOREIGN KEY (pendencia_id) REFERENCES fotografico_pendencia(id) ON DELETE CASCADE,
    CONSTRAINT fk_fotografico_cobranca_responsavel
        FOREIGN KEY (responsavel_cobranca_id) REFERENCES colaborador(idcolaborador) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pendências históricas continuam abertas e ganham uma cobrança inicial sem perder dados.
UPDATE fotografico_pendencia pe
JOIN fotografico_plano p ON p.id = pe.plano_id
LEFT JOIN fotografico_sla s ON s.plano_id = p.id AND s.completed_at IS NULL
   SET pe.responsavel_cobranca_id = COALESCE(pe.responsavel_cobranca_id, p.responsavel_plano_id, pe.responsavel_id),
       pe.proxima_cobranca_em = COALESCE(pe.proxima_cobranca_em, s.due_at_effective)
 WHERE pe.status = 'ABERTA';
