-- Planejamento Global de Capacidade V1.1.
-- Não inclui valores iniciais: a disponibilidade é uma decisão operacional
-- configurável, e uma ausência de configuração é devolvida explicitamente pelo motor.

CREATE TABLE IF NOT EXISTS planejamento_capacidade_etapa (
    codigo_etapa VARCHAR(50) NOT NULL,
    capacidade_padrao DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    atualizado_por_colaborador_id INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (codigo_etapa),
    KEY idx_planejamento_capacidade_etapa_ativo (ativo),
    CONSTRAINT fk_planejamento_capacidade_etapa_atualizado_por FOREIGN KEY (atualizado_por_colaborador_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Reservada para férias, recesso e outras exceções temporárias. A V1.1 já
-- respeita esses períodos na leitura, mas não inclui interface de manutenção.
CREATE TABLE IF NOT EXISTS planejamento_capacidade_etapa_periodo (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo_etapa VARCHAR(50) NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    capacidade_disponivel DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    atualizado_por_colaborador_id INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_planejamento_capacidade_periodo_consulta (
        codigo_etapa,
        ativo,
        data_inicio,
        data_fim
    ),
    CONSTRAINT fk_planejamento_capacidade_periodo_etapa FOREIGN KEY (codigo_etapa) REFERENCES planejamento_capacidade_etapa (codigo_etapa) ON DELETE CASCADE,
    CONSTRAINT fk_planejamento_capacidade_periodo_atualizado_por FOREIGN KEY (atualizado_por_colaborador_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;