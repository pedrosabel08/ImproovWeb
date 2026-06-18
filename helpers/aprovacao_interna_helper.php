<?php

if (!function_exists('aprovacao_interna_ensure_schema')) {
    function aprovacao_interna_ensure_schema(mysqli $conn): void
    {
        static $schemaEnsured = false;
        if ($schemaEnsured) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS alteracao_aprovacao_interna (
            id INT AUTO_INCREMENT PRIMARY KEY,
            funcao_imagem_id INT NOT NULL,
            imagem_id INT NOT NULL,
            status_id INT NOT NULL,
            origem ENUM('flowreview','presencial','whatsapp') NOT NULL,
            registrado_por_colaborador_id INT NOT NULL,
            registrado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            render_id INT NULL,
            historico_aprovacao_id INT NULL,
            observacao VARCHAR(255) NULL,
            UNIQUE KEY uniq_funcao_status (funcao_imagem_id, status_id),
            KEY idx_imagem_status (imagem_id, status_id),
            KEY idx_status_id (status_id),
            KEY idx_origem (origem),
            KEY idx_registrado_por (registrado_por_colaborador_id),
            KEY idx_render_id (render_id),
            KEY idx_historico_aprovacao_id (historico_aprovacao_id),
            CONSTRAINT fk_aai_funcao_imagem
                FOREIGN KEY (funcao_imagem_id) REFERENCES funcao_imagem (idfuncao_imagem)
                ON DELETE CASCADE,
            CONSTRAINT fk_aai_imagem
                FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra (idimagens_cliente_obra)
                ON DELETE CASCADE,
            CONSTRAINT fk_aai_status
                FOREIGN KEY (status_id) REFERENCES status_imagem (idstatus)
                ON DELETE RESTRICT,
            CONSTRAINT fk_aai_colaborador
                FOREIGN KEY (registrado_por_colaborador_id) REFERENCES colaborador (idcolaborador)
                ON DELETE RESTRICT,
            CONSTRAINT fk_aai_render
                FOREIGN KEY (render_id) REFERENCES render_alta (idrender_alta)
                ON DELETE SET NULL,
            CONSTRAINT fk_aai_historico
                FOREIGN KEY (historico_aprovacao_id) REFERENCES historico_aprovacoes (id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        $conn->query($sql);
        $schemaEnsured = true;
    }
}

if (!function_exists('aprovacao_interna_origem_valida')) {
    function aprovacao_interna_origem_valida(string $origem): bool
    {
        return in_array($origem, ['flowreview', 'presencial', 'whatsapp'], true);
    }
}

if (!function_exists('aprovacao_interna_resolver_alteracao_por_imagem')) {
    function aprovacao_interna_resolver_alteracao_por_imagem(mysqli $conn, int $imagemId, ?int $statusId = null): ?array
    {
        if ($statusId === null) {
            $stmt = $conn->prepare(
                "SELECT fi.idfuncao_imagem, fi.imagem_id, i.status_id
                   FROM funcao_imagem fi
                   JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
                  WHERE fi.imagem_id = ?
                    AND fi.funcao_id = 6
                  LIMIT 1"
            );
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param('i', $imagemId);
        } else {
            $stmt = $conn->prepare(
                "SELECT fi.idfuncao_imagem, fi.imagem_id, ? AS status_id
                   FROM funcao_imagem fi
                  WHERE fi.imagem_id = ?
                    AND fi.funcao_id = 6
                  LIMIT 1"
            );
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param('ii', $statusId, $imagemId);
        }

        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        return [
            'funcao_imagem_id' => (int) $row['idfuncao_imagem'],
            'imagem_id' => (int) $row['imagem_id'],
            'status_id' => (int) $row['status_id'],
        ];
    }
}

if (!function_exists('aprovacao_interna_resolver_alteracao_por_render')) {
    function aprovacao_interna_resolver_alteracao_por_render(mysqli $conn, int $renderId): ?array
    {
        $stmt = $conn->prepare(
            "SELECT r.idrender_alta,
                    r.imagem_id,
                    r.status_id,
                    fi.idfuncao_imagem
               FROM render_alta r
          LEFT JOIN funcao_imagem fi
                 ON fi.imagem_id = r.imagem_id
                AND fi.funcao_id = 6
              WHERE r.idrender_alta = ?
              LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $renderId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || empty($row['idfuncao_imagem'])) {
            return null;
        }

        return [
            'render_id' => (int) $row['idrender_alta'],
            'funcao_imagem_id' => (int) $row['idfuncao_imagem'],
            'imagem_id' => (int) $row['imagem_id'],
            'status_id' => (int) $row['status_id'],
        ];
    }
}

if (!function_exists('aprovacao_interna_resolver_alteracao_por_funcao')) {
    function aprovacao_interna_resolver_alteracao_por_funcao(mysqli $conn, int $funcaoImagemId): ?array
    {
        $stmt = $conn->prepare(
            "SELECT fi.idfuncao_imagem,
                    fi.imagem_id,
                    i.status_id
               FROM funcao_imagem fi
               JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
              WHERE fi.idfuncao_imagem = ?
                AND fi.funcao_id = 6
              LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $funcaoImagemId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        return [
            'funcao_imagem_id' => (int) $row['idfuncao_imagem'],
            'imagem_id' => (int) $row['imagem_id'],
            'status_id' => (int) $row['status_id'],
        ];
    }
}

if (!function_exists('aprovacao_interna_render_existe_na_etapa')) {
    function aprovacao_interna_render_existe_na_etapa(mysqli $conn, int $imagemId, int $statusId): bool
    {
        $stmt = $conn->prepare(
            "SELECT 1
               FROM render_alta
              WHERE imagem_id = ?
                AND status_id = ?
              LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $imagemId, $statusId);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        return $exists;
    }
}

if (!function_exists('aprovacao_interna_tem_registro')) {
    function aprovacao_interna_tem_registro(mysqli $conn, int $funcaoImagemId, int $statusId): bool
    {
        aprovacao_interna_ensure_schema($conn);

        $stmt = $conn->prepare(
            "SELECT 1
               FROM alteracao_aprovacao_interna
              WHERE funcao_imagem_id = ?
                AND status_id = ?
              LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $funcaoImagemId, $statusId);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        return $exists;
    }
}

if (!function_exists('aprovacao_interna_registrar')) {
    function aprovacao_interna_registrar(
        mysqli $conn,
        int $funcaoImagemId,
        int $imagemId,
        int $statusId,
        string $origem,
        int $registradoPorColaboradorId,
        ?int $renderId = null,
        ?int $historicoAprovacaoId = null,
        ?string $observacao = null
    ): bool {
        if (!aprovacao_interna_origem_valida($origem) || $registradoPorColaboradorId <= 0) {
            return false;
        }

        aprovacao_interna_ensure_schema($conn);

        $stmt = $conn->prepare(
            "INSERT INTO alteracao_aprovacao_interna
                (funcao_imagem_id, imagem_id, status_id, origem, registrado_por_colaborador_id, render_id, historico_aprovacao_id, observacao)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'iiisiiis',
            $funcaoImagemId,
            $imagemId,
            $statusId,
            $origem,
            $registradoPorColaboradorId,
            $renderId,
            $historicoAprovacaoId,
            $observacao
        );
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}
