-- Deduplicação de arquivos físicos (não altera a tabela arquivos)

CREATE TABLE IF NOT EXISTS arquivo_fisico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hash CHAR(64) NOT NULL,
    ext VARCHAR(10) NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    categoria_id INT NULL,
    obra_id INT NULL,
    caminho VARCHAR(1024) NOT NULL,
    tamanho BIGINT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    criado_por INT NULL,
    UNIQUE KEY uniq_hash_ext (hash, ext),
    KEY idx_hash (hash),
    KEY idx_obra (obra_id),
    KEY idx_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS arquivo_fisico_vinculo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    arquivo_id INT NOT NULL,
    arquivo_fisico_id INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_arquivo (arquivo_id),
    KEY idx_arquivo_fisico (arquivo_fisico_id),
    CONSTRAINT fk_afv_arquivo FOREIGN KEY (arquivo_id)
        REFERENCES arquivos(idarquivo)
        ON DELETE CASCADE,
    CONSTRAINT fk_afv_fisico FOREIGN KEY (arquivo_fisico_id)
        REFERENCES arquivo_fisico(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
