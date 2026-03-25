-- Criada em 2026-03-23
-- Registra pares de funções que o colaborador optou por NÃO executar em conjunto.
-- Decisão permanente: uma vez separado, nunca se re-unifica automaticamente.
CREATE TABLE IF NOT EXISTS funcao_par_separado (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    imagem_id   INT NOT NULL,
    par_tipo    ENUM('caderno_filtro', 'modelagem_composicao') NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_par (imagem_id, par_tipo),
    KEY idx_imagem (imagem_id)
);
