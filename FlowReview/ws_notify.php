<?php

/**
 * Publica eventos do FlowReview via Redis sem interferir na gravação principal.
 * O servidor WebSocket já existente encaminha o canal flow_review:* aos clientes.
 */
function notifyFlowReviewUpdate(mysqli $conn, string $event, array $payload = []): void
{
    $channel = 'flow_review:updated';

    try {
        $autoloadCandidates = [
            __DIR__ . '/../vendor/autoload.php',
            __DIR__ . '/vendor/autoload.php',
        ];
        foreach ($autoloadCandidates as $autoload) {
            if (file_exists($autoload)) {
                require_once $autoload;
                break;
            }
        }

        if (!class_exists(\Predis\Client::class)) {
            error_log('[WS-DIAG][FlowReview][PHP] Predis\\Client indisponível.');
            return;
        }

        $context = flowReviewResolveRealtimeContext($conn, $payload);
        $actorId = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null;
        $actorName = $_SESSION['nome_usuario'] ?? $_SESSION['nome_colaborador'] ?? null;

        $message = array_merge([
            'version' => 1,
            'event_id' => flowReviewRealtimeEventId(),
            'event' => $event,
            'source' => 'flow_review',
            'obra_id' => null,
            'entrega_id' => null,
            'imagem_id' => null,
            'funcao_imagem_id' => null,
            'funcao_animacao_id' => null,
            'historico_id' => null,
            'arquivo_log_id' => null,
            'indice_envio' => null,
            'versao' => null,
            'comentario_id' => null,
            'resposta_id' => null,
            'aprovacao_id' => null,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'occurred_at' => date(DATE_ATOM),
            'ts' => time(),
        ], $context, $payload);

        $encoded = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('Falha ao serializar evento: ' . json_last_error_msg());
        }

        $redisUrl = getenv('REDIS_URL') ?: 'tcp://127.0.0.1:6379';
        $redis = new \Predis\Client($redisUrl, [
            'timeout' => 1,
            'read_write_timeout' => 1,
        ]);
        $redis->publish($channel, $encoded);
    } catch (Throwable $e) {
        error_log(
            '[WS-DIAG][FlowReview][PHP] publish.error canal=' . $channel .
                ' evento=' . $event .
                ' exception=' . $e->getMessage()
        );
        // A atualização em tempo real nunca deve invalidar uma gravação concluída.
    }
}

function flowReviewRealtimeEventId(): string
{
    try {
        return bin2hex(random_bytes(12));
    } catch (Throwable $e) {
        return uniqid('fr_', true);
    }
}

function flowReviewResolveRealtimeContext(mysqli $conn, array $payload): array
{
    $context = [];
    $comentarioId = (int) ($payload['comentario_id'] ?? 0);
    $respostaId = (int) ($payload['resposta_id'] ?? 0);
    $historicoId = (int) ($payload['historico_id'] ?? 0);
    $arquivoLogId = (int) ($payload['arquivo_log_id'] ?? 0);
    $funcaoImagemId = (int) ($payload['funcao_imagem_id'] ?? 0);
    $funcaoAnimacaoId = (int) ($payload['funcao_animacao_id'] ?? 0);

    if ($respostaId > 0 && $comentarioId <= 0) {
        $stmt = $conn->prepare('SELECT comentario_id FROM respostas_comentario WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $respostaId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $comentarioId = (int) ($row['comentario_id'] ?? 0);
            $stmt->close();
        }
    }

    if ($comentarioId > 0) {
        $stmt = $conn->prepare('SELECT ap_imagem_id, arquivo_log_id FROM comentarios_imagem WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $comentarioId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $historicoId = $historicoId ?: (int) ($row['ap_imagem_id'] ?? 0);
            $arquivoLogId = $arquivoLogId ?: (int) ($row['arquivo_log_id'] ?? 0);
            $stmt->close();
        }
    }

    if ($historicoId > 0) {
        $stmt = $conn->prepare(
            'SELECT funcao_imagem_id, funcao_animacao_id, indice_envio
             FROM historico_aprovacoes_imagens WHERE id = ? LIMIT 1'
        );
        if ($stmt) {
            $stmt->bind_param('i', $historicoId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $funcaoImagemId = $funcaoImagemId ?: (int) ($row['funcao_imagem_id'] ?? 0);
            $funcaoAnimacaoId = $funcaoAnimacaoId ?: (int) ($row['funcao_animacao_id'] ?? 0);
            if (!array_key_exists('indice_envio', $payload) && isset($row['indice_envio'])) {
                $context['indice_envio'] = (int) $row['indice_envio'];
            }
            $stmt->close();
        }
    }

    if ($arquivoLogId > 0 && $funcaoImagemId <= 0) {
        $stmt = $conn->prepare('SELECT funcao_imagem_id FROM arquivo_log WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $arquivoLogId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $funcaoImagemId = (int) ($row['funcao_imagem_id'] ?? 0);
            $stmt->close();
        }
    }

    if ($funcaoImagemId > 0) {
        $stmt = $conn->prepare(
            'SELECT fi.imagem_id, ico.obra_id,
                    (SELECT ei.entrega_id
                       FROM entregas_itens ei
                       JOIN entregas e ON e.id = ei.entrega_id
                      WHERE ei.imagem_id = fi.imagem_id AND e.obra_id = ico.obra_id
                      ORDER BY e.id DESC LIMIT 1) AS entrega_id
               FROM funcao_imagem fi
               JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
              WHERE fi.idfuncao_imagem = ? LIMIT 1'
        );
        if ($stmt) {
            $stmt->bind_param('i', $funcaoImagemId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                $context['imagem_id'] = (int) $row['imagem_id'];
                $context['obra_id'] = (int) $row['obra_id'];
                $context['entrega_id'] = isset($row['entrega_id']) ? (int) $row['entrega_id'] : null;
            }
            $stmt->close();
        }
    } elseif ($funcaoAnimacaoId > 0) {
        $stmt = $conn->prepare(
            'SELECT a.imagem_id, ico.obra_id
               FROM funcao_animacao fa
               JOIN animacao a ON a.idanimacao = fa.animacao_id
               JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = a.imagem_id
              WHERE fa.id = ? LIMIT 1'
        );
        if ($stmt) {
            $stmt->bind_param('i', $funcaoAnimacaoId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                $context['imagem_id'] = (int) $row['imagem_id'];
                $context['obra_id'] = (int) $row['obra_id'];
            }
            $stmt->close();
        }
    }

    if ($funcaoImagemId > 0) {
        $context['funcao_imagem_id'] = $funcaoImagemId;
    }
    if ($funcaoAnimacaoId > 0) {
        $context['funcao_animacao_id'] = $funcaoAnimacaoId;
    }
    if ($historicoId > 0) {
        $context['historico_id'] = $historicoId;
    }
    if ($arquivoLogId > 0) {
        $context['arquivo_log_id'] = $arquivoLogId;
    }
    if ($comentarioId > 0) {
        $context['comentario_id'] = $comentarioId;
    }
    if ($respostaId > 0) {
        $context['resposta_id'] = $respostaId;
    }

    return $context;
}
