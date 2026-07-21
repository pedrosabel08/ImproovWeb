-- Flow Block: menções persistentes, leitura e filtro "Mencionaram você".
-- Execute uma vez nos ambientes que já possuem a tabela flow_issue_mencao.

ALTER TABLE flow_issue_mencao
ADD COLUMN issue_id INT NULL AFTER id,
ADD COLUMN mencionado_por_colaborador_id INT NULL AFTER colaborador_id,
ADD COLUMN status ENUM('PENDENTE', 'LIDA') NOT NULL DEFAULT 'PENDENTE' AFTER mencionado_por_colaborador_id,
ADD COLUMN visualizado_em DATETIME NULL AFTER criado_em;

UPDATE flow_issue_mencao m
JOIN flow_issue_atividade a ON a.id = m.atividade_id
SET
    m.issue_id = a.issue_id,
    m.mencionado_por_colaborador_id = a.criado_por_colaborador_id
WHERE
    m.issue_id IS NULL;

ALTER TABLE flow_issue_mencao
MODIFY COLUMN issue_id INT NOT NULL,
ADD INDEX idx_flow_issue_mencao_destinatario_status (
    colaborador_id,
    status,
    criado_em
),
ADD INDEX idx_flow_issue_mencao_issue_status (issue_id, status);