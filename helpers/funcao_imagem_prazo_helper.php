<?php

/**
 * Atualiza o prazo operacional de uma funcao_imagem e registra a auditoria
 * na mesma transacao do chamador.
 *
 * O chamador deve abrir a transacao antes desta funcao. Se a data nao mudou,
 * nenhuma escrita e feita. Quando muda, o UPDATE e o INSERT do historico sao
 * obrigatorios: qualquer falha gera excecao para que a transacao seja desfeita.
 */
function funcao_imagem_prazo_normalizar($value): ?string
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }

    $date = explode(' ', trim((string) $value))[0];
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();
    $hasErrors = is_array($errors)
        ? (!empty($errors['warning_count']) || !empty($errors['error_count']))
        : false;

    if (!$parsed || $hasErrors || $parsed->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Prazo invalido para funcao_imagem.');
    }

    return $date;
}

/**
 * @return array{alterado: bool, prazo_anterior: ?string, prazo_novo: ?string, status_anterior: ?string, status_novo: ?string}
 */
function funcao_imagem_prazo_atualizar(mysqli $conn, int $funcaoImagemId, $novoPrazo, array $contexto = []): array
{
    if ($funcaoImagemId <= 0) {
        throw new InvalidArgumentException('Funcao de imagem invalida.');
    }

    $novoPrazo = funcao_imagem_prazo_normalizar($novoPrazo);

    $select = $conn->prepare(
        'SELECT prazo, status FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1 FOR UPDATE'
    );
    if (!$select) {
        throw new RuntimeException('Nao foi possivel consultar o prazo atual: ' . $conn->error);
    }
    $select->bind_param('i', $funcaoImagemId);
    if (!$select->execute()) {
        $error = $select->error;
        $select->close();
        throw new RuntimeException('Nao foi possivel consultar o prazo atual: ' . $error);
    }
    $atual = $select->get_result()->fetch_assoc();
    $select->close();

    if (!$atual) {
        throw new RuntimeException('Funcao de imagem nao encontrada: ' . $funcaoImagemId . '.');
    }

    $prazoAnterior = funcao_imagem_prazo_normalizar($atual['prazo'] ?? null);
    $statusAnterior = $atual['status'] ?? null;
    $statusNovo = array_key_exists('status_novo', $contexto)
        ? $contexto['status_novo']
        : $statusAnterior;

    if ($prazoAnterior === $novoPrazo) {
        return [
            'alterado' => false,
            'prazo_anterior' => $prazoAnterior,
            'prazo_novo' => $novoPrazo,
            'status_anterior' => $statusAnterior,
            'status_novo' => $statusNovo,
        ];
    }

    $updateSql = 'UPDATE funcao_imagem SET prazo = ?';
    if (array_key_exists('status_novo', $contexto)) {
        $updateSql .= ', status = ?';
    }
    $updateSql .= ' WHERE idfuncao_imagem = ?';

    $update = $conn->prepare($updateSql);
    if (!$update) {
        throw new RuntimeException('Nao foi possivel atualizar o prazo: ' . $conn->error);
    }
    if (array_key_exists('status_novo', $contexto)) {
        $update->bind_param('ssi', $novoPrazo, $statusNovo, $funcaoImagemId);
    } else {
        $update->bind_param('si', $novoPrazo, $funcaoImagemId);
    }
    if (!$update->execute()) {
        $error = $update->error;
        $update->close();
        throw new RuntimeException('Nao foi possivel atualizar o prazo: ' . $error);
    }
    $update->close();

    $actorColaboradorId = array_key_exists('alterado_por_colaborador_id', $contexto)
        ? $contexto['alterado_por_colaborador_id']
        : ($_SESSION['idcolaborador'] ?? null);
    $actorUsuarioId = array_key_exists('alterado_por_usuario_id', $contexto)
        ? $contexto['alterado_por_usuario_id']
        : ($_SESSION['idusuario'] ?? null);
    $origem = trim((string) ($contexto['origem'] ?? 'manual')) ?: 'manual';
    $motivo = array_key_exists('motivo', $contexto) ? $contexto['motivo'] : null;

    $history = $conn->prepare(
        'INSERT INTO funcao_imagem_prazo_historico (
            funcao_imagem_id,
            prazo_anterior,
            prazo_novo,
            alterado_por_colaborador_id,
            alterado_por_usuario_id,
            origem,
            motivo,
            status_anterior,
            status_novo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$history) {
        throw new RuntimeException('Nao foi possivel preparar a auditoria do prazo: ' . $conn->error);
    }
    $historyTypes = 'issiissss';
    $history->bind_param(
        $historyTypes,
        $funcaoImagemId,
        $prazoAnterior,
        $novoPrazo,
        $actorColaboradorId,
        $actorUsuarioId,
        $origem,
        $motivo,
        $statusAnterior,
        $statusNovo
    );
    if (!$history->execute()) {
        $error = $history->error;
        $history->close();
        throw new RuntimeException('Nao foi possivel registrar a auditoria do prazo: ' . $error);
    }
    $history->close();

    return [
        'alterado' => true,
        'prazo_anterior' => $prazoAnterior,
        'prazo_novo' => $novoPrazo,
        'status_anterior' => $statusAnterior,
        'status_novo' => $statusNovo,
    ];
}
