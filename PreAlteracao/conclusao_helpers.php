<?php

require_once __DIR__ . '/pre_alt_helpers.php';
require_once __DIR__ . '/../Entregas/prazo_entrega_helper.php';
require_once __DIR__ . '/../helpers/alteracoes_helper.php';

function pre_alt_next_status_id(int $statusId): ?int
{
    $map = [
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 14,
        14 => 15,
        15 => 15,
    ];

    return $map[$statusId] ?? null;
}

function pre_alt_status_nome(mysqli $conn, int $statusId): string
{
    $stmt = $conn->prepare('SELECT nome_status FROM status_imagem WHERE idstatus = ? LIMIT 1');
    if (!$stmt) {
        return 'Etapa ' . $statusId;
    }

    $stmt->bind_param('i', $statusId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (string) ($row['nome_status'] ?? ('Etapa ' . $statusId));
}

function pre_alt_default_prazo(mysqli $conn, int $obraId, int $statusId, string $dataTriagem): string
{
    $calculo = entregas_calcular_prazo_previsto($conn, $obraId, $statusId, $dataTriagem);
    if ($calculo && !empty($calculo['data_prevista'])) {
        return (string) $calculo['data_prevista'];
    }

    return entregas_adicionar_dias_uteis($dataTriagem, 7);
}

function pre_alt_normalizar_data(?string $date, ?string $fallback = null): string
{
    $date = trim((string) ($date ?? ''));
    if ($date !== '' && entregas_valid_date($date)) {
        return $date;
    }

    if ($fallback && entregas_valid_date($fallback)) {
        return $fallback;
    }

    return date('Y-m-d');
}

function pre_alt_fetch_conclusao_summary(mysqli $conn, int $loteId, ?string $dataTriagem = null): array
{
    pre_alt_ensure_schema($conn);
    entregas_ensure_data_recebimento_schema($conn);

    $dataTriagem = pre_alt_normalizar_data($dataTriagem);

    $stmt = $conn->prepare(
        "SELECT
            l.id,
            l.obra_id,
            l.status_id,
            l.status,
            l.data_finalizacao_cliente,
            l.prazo,
            o.nomenclatura AS obra_nome,
            c.nome_cliente,
            COALESCE(si.nome_status, CONCAT('Etapa ', l.status_id)) AS status_nome,
            MAX(COALESCE(cr.resolved_at, CASE WHEN rb.status = 'RESOLVED' THEN rb.updated_at ELSE NULL END)) AS lote_resolvido_em
         FROM pre_alt_lote l
         JOIN obra o ON o.idobra = l.obra_id
         LEFT JOIN cliente c ON c.idcliente = o.cliente
         LEFT JOIN status_imagem si ON si.idstatus = l.status_id
         LEFT JOIN pre_alt_lote_batches plb ON plb.pre_alt_lote_id = l.id
         LEFT JOIN review_batch rb ON rb.id = plb.review_batch_id
         LEFT JOIN cobranca_review cr ON cr.review_batch_id = rb.id
         WHERE l.id = ?
         GROUP BY l.id, l.obra_id, l.status_id, l.status, l.data_finalizacao_cliente, l.prazo, o.nomenclatura, c.nome_cliente, si.nome_status
         LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException('Nao foi possivel preparar a consulta do lote.');
    }

    $stmt->bind_param('i', $loteId);
    $stmt->execute();
    $lote = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$lote) {
        throw new RuntimeException('Lote de triagem nao encontrado.');
    }

    $stmtItens = $conn->prepare(
        "SELECT
            pai.id AS item_id,
            pai.imagem_id,
            ico.imagem_nome,
            pai.resultado,
            pai.nivel_complexidade,
            pai.necessita_retorno,
            COALESCE(pai.quantidade_comentarios, 0) AS quantidade_comentarios
         FROM pre_alt_itens pai
         JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = pai.imagem_id
         WHERE pai.pre_alt_lote_id = ?
         ORDER BY ico.imagem_nome ASC, pai.id ASC"
    );
    if (!$stmtItens) {
        throw new RuntimeException('Nao foi possivel preparar a consulta das imagens.');
    }

    $stmtItens->bind_param('i', $loteId);
    $stmtItens->execute();
    $resultItens = $stmtItens->get_result();

    $itens = [];
    while ($row = $resultItens->fetch_assoc()) {
        $row['item_id'] = (int) $row['item_id'];
        $row['imagem_id'] = (int) $row['imagem_id'];
        $row['nivel_complexidade'] = $row['nivel_complexidade'] !== null ? (int) $row['nivel_complexidade'] : null;
        $row['necessita_retorno'] = (int) $row['necessita_retorno'];
        $row['quantidade_comentarios'] = (int) $row['quantidade_comentarios'];
        $itens[] = $row;
    }
    $stmtItens->close();

    $pendencias = [];
    $aprovadas = [];
    $alteracoes = [];
    $niveis = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

    if (($lote['status'] ?? '') === 'PLANEJADO') {
        $pendencias[] = 'Este lote ja foi planejado.';
    }

    if (empty($itens)) {
        $pendencias[] = 'O lote nao possui imagens para liberar.';
    }

    foreach ($itens as $item) {
        $nomeImagem = trim((string) ($item['imagem_nome'] ?? ('Imagem ' . $item['imagem_id'])));
        $resultado = strtoupper(trim((string) ($item['resultado'] ?? '')));

        if ($resultado === '') {
            $pendencias[] = $nomeImagem . ': informe o resultado da triagem.';
            continue;
        }

        if ($resultado === 'AGUARDANDO_CLIENTE' || $item['necessita_retorno'] === 1) {
            $pendencias[] = $nomeImagem . ': ainda necessita retorno do cliente.';
            continue;
        }

        if ($resultado === 'ALTERACAO') {
            if ($item['nivel_complexidade'] === null || $item['nivel_complexidade'] < 1 || $item['nivel_complexidade'] > 5) {
                $pendencias[] = $nomeImagem . ': informe o nivel de complexidade.';
                continue;
            }

            $alteracoes[] = $item;
            $niveis[$item['nivel_complexidade']]++;
            continue;
        }

        if ($resultado === 'SEM_ALTERACAO') {
            $aprovadas[] = $item;
            continue;
        }

        $pendencias[] = $nomeImagem . ': resultado de triagem invalido.';
    }

    if (!empty($itens) && empty($aprovadas) && empty($alteracoes)) {
        $pendencias[] = 'Nenhuma imagem esta pronta para EF ou alteracao.';
    }

    $statusAtual = (int) $lote['status_id'];
    $statusAlteracao = !empty($alteracoes) ? pre_alt_next_status_id($statusAtual) : null;
    if (!empty($alteracoes) && $statusAlteracao === null) {
        $pendencias[] = 'A etapa atual nao possui proxima etapa de alteracao configurada.';
    }

    $prazoEf = !empty($aprovadas)
        ? pre_alt_default_prazo($conn, (int) $lote['obra_id'], 6, $dataTriagem)
        : null;
    $prazoAlteracao = (!empty($alteracoes) && $statusAlteracao !== null)
        ? pre_alt_default_prazo($conn, (int) $lote['obra_id'], $statusAlteracao, $dataTriagem)
        : null;

    return [
        'success' => true,
        'eligible' => empty($pendencias),
        'pendencias' => array_values(array_unique($pendencias)),
        'data_triagem' => $dataTriagem,
        'lote' => [
            'id' => (int) $lote['id'],
            'obra_id' => (int) $lote['obra_id'],
            'obra_nome' => (string) ($lote['obra_nome'] ?? ''),
            'cliente_nome' => (string) ($lote['nome_cliente'] ?? ''),
            'status_id' => $statusAtual,
            'status_nome' => (string) ($lote['status_nome'] ?? ''),
            'status' => (string) ($lote['status'] ?? ''),
            'data_resolvida_cliente' => $lote['lote_resolvido_em']
                ? substr((string) $lote['lote_resolvido_em'], 0, 10)
                : (string) ($lote['data_finalizacao_cliente'] ?? ''),
        ],
        'totais' => [
            'imagens' => count($itens),
            'aprovadas' => count($aprovadas),
            'alteracoes' => count($alteracoes),
            'niveis' => $niveis,
        ],
        'grupos' => [
            'ef' => [
                'total' => count($aprovadas),
                'status_id' => 6,
                'status_nome' => pre_alt_status_nome($conn, 6),
                'prazo' => $prazoEf,
                'itens' => array_map(static function (array $item): array {
                    return [
                        'imagem_id' => $item['imagem_id'],
                        'imagem_nome' => $item['imagem_nome'],
                    ];
                }, $aprovadas),
            ],
            'alteracao' => [
                'total' => count($alteracoes),
                'status_id' => $statusAlteracao,
                'status_nome' => $statusAlteracao ? pre_alt_status_nome($conn, $statusAlteracao) : '',
                'prazo' => $prazoAlteracao,
                'niveis' => $niveis,
                'itens' => array_map(static function (array $item): array {
                    return [
                        'imagem_id' => $item['imagem_id'],
                        'imagem_nome' => $item['imagem_nome'],
                        'nivel_complexidade' => $item['nivel_complexidade'],
                    ];
                }, $alteracoes),
            ],
        ],
    ];
}

function pre_alt_criar_entrega_conclusao(
    mysqli $conn,
    int $obraId,
    int $statusId,
    string $dataRecebimento,
    string $prazo,
    array $imagemIds,
    ?string $observacao
): int {
    if (empty($imagemIds)) {
        return 0;
    }

    $tipoEntrega = 'PADRAO';
    $stmt = $conn->prepare("INSERT INTO entregas (obra_id, status_id, tipo_entrega, data_recebimento, data_prevista, observacoes) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new RuntimeException('Nao foi possivel criar a entrega.');
    }
    $stmt->bind_param('iissss', $obraId, $statusId, $tipoEntrega, $dataRecebimento, $prazo, $observacao);
    $stmt->execute();
    $entregaId = (int) $stmt->insert_id;
    $stmt->close();

    $stmtItem = $conn->prepare("INSERT INTO entregas_itens (entrega_id, imagem_id, data_prevista) VALUES (?, ?, ?)");
    if (!$stmtItem) {
        throw new RuntimeException('Nao foi possivel criar os itens da entrega.');
    }
    foreach ($imagemIds as $imagemId) {
        $imagemId = (int) $imagemId;
        $stmtItem->bind_param('iis', $entregaId, $imagemId, $prazo);
        $stmtItem->execute();
    }
    $stmtItem->close();

    $statusNome = pre_alt_status_nome($conn, $statusId);
    $nextOrdem = 1;
    $stmtOrdem = $conn->prepare("SELECT IFNULL(MAX(ordem), 0) + 1 AS next_ordem FROM acompanhamento_email WHERE obra_id = ?");
    if ($stmtOrdem) {
        $stmtOrdem->bind_param('i', $obraId);
        $stmtOrdem->execute();
        $row = $stmtOrdem->get_result()->fetch_assoc();
        $nextOrdem = (int) ($row['next_ordem'] ?? 1);
        $stmtOrdem->close();
    }

    $assunto = 'Nova entrega registrada pela Pre-Alteracao para a etapa ' . $statusNome . ', com ' . count($imagemIds) . ' imagem(ns).';
    $hoje = date('Y-m-d');
    $stmtAcomp = $conn->prepare("INSERT INTO acompanhamento_email (obra_id, colaborador_id, assunto, data, ordem, entrega_id, tipo, status) VALUES (?, NULL, ?, ?, ?, ?, 'entrega', '')");
    if ($stmtAcomp) {
        $stmtAcomp->bind_param('issii', $obraId, $assunto, $hoje, $nextOrdem, $entregaId);
        $stmtAcomp->execute();
        $stmtAcomp->close();
    }

    return $entregaId;
}

function pre_alt_upsert_funcao_alteracao(mysqli $conn, int $imagemId, string $prazo): int
{
    $funcaoId = 0;
    $stmt = $conn->prepare('SELECT idfuncao_imagem FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = 6 LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Nao foi possivel consultar a funcao de alteracao.');
    }
    $stmt->bind_param('i', $imagemId);
    $stmt->execute();
    $stmt->bind_result($funcaoIdExistente);
    if ($stmt->fetch()) {
        $funcaoId = (int) $funcaoIdExistente;
    }
    $stmt->close();

    if ($funcaoId > 0) {
        $status = 'Não iniciado';
        $stmtUpdate = $conn->prepare('UPDATE funcao_imagem SET status = ?, prazo = ? WHERE idfuncao_imagem = ?');
        if (!$stmtUpdate) {
            throw new RuntimeException('Nao foi possivel atualizar a funcao de alteracao.');
        }
        $stmtUpdate->bind_param('ssi', $status, $prazo, $funcaoId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
        return $funcaoId;
    }

    $status = 'Não iniciado';
    $stmtInsert = $conn->prepare('INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id, prazo, status) VALUES (?, NULL, 6, ?, ?)');
    if (!$stmtInsert) {
        throw new RuntimeException('Nao foi possivel criar a funcao de alteracao.');
    }
    $stmtInsert->bind_param('iss', $imagemId, $prazo, $status);
    $stmtInsert->execute();
    $funcaoId = (int) $stmtInsert->insert_id;
    $stmtInsert->close();

    return $funcaoId;
}
