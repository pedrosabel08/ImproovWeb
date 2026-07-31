<?php

declare(strict_types=1);

namespace FlowConnect\Application;

use FlowConnect\Contracts\EventEnvelope;

final class FlowReviewEventFactory
{
    public static function mention(array $context): array
    {
        $sourceKind = ($context['resposta_id'] ?? null) ? 'resposta' : 'comentario';
        $sourceId = (int) ($context[$sourceKind . '_id'] ?? 0);
        $mentionedId = (int) ($context['mencionado_id'] ?? 0);
        return self::base(
            'review.mencao.criada',
            'mencao',
            (string) (($context['mencao_id'] ?? 0) ?: $sourceKind . ':' . $sourceId . ':' . $mentionedId),
            (int) ($context['autor_id'] ?? 0),
            "review:mencao:{$sourceKind}:{$sourceId}:colaborador:{$mentionedId}:v1",
            $context
        );
    }

    public static function angle(array $context): array
    {
        $map = [
            'escolhido' => 'review.angulo.escolhido',
            'escolhido_com_ajustes' => 'review.angulo.escolhido_com_ajustes',
            'ajustes' => 'review.angulo.ajuste_solicitado',
        ];
        $decision = (string) ($context['decisao'] ?? '');
        $eventType = $map[$decision] ?? 'review.angulo.ajuste_solicitado';
        $angleId = (int) ($context['angulo_id'] ?? 0);
        $historyId = (int) ($context['historico_id'] ?? 0);
        return self::base(
            $eventType,
            'angulo',
            (string) ($angleId ?: $historyId),
            (int) ($context['revisor_id'] ?? 0),
            'review:angulo:' . ($angleId ?: $historyId) . ':decisao:' . $decision . ':historico:' . $historyId . ':v1',
            $context
        );
    }

    public static function task(array $context): array
    {
        $map = [
            'Aprovado' => 'review.tarefa.aprovada',
            'Ajuste' => 'review.tarefa.ajuste_solicitado',
            'Aprovado com ajustes' => 'review.tarefa.aprovada_com_ajustes',
            'Reprovado' => 'review.tarefa.reprovada',
        ];
        $status = (string) ($context['status_novo'] ?? '');
        $eventType = $map[$status] ?? 'review.tarefa.ajuste_solicitado';
        $entityType = ($context['tipo_fluxo'] ?? 'imagem') === 'animacao' ? 'funcao_animacao' : 'funcao_imagem';
        $entityId = (int) ($context[$entityType . '_id'] ?? $context['funcao_imagem_id'] ?? 0);
        $historyId = (int) ($context['idempotency_historico_id'] ?? $context['historico_aprovacao_id'] ?? $context['historico_id'] ?? 0);
        return self::base(
            $eventType,
            $entityType,
            (string) $entityId,
            (int) ($context['revisor_id'] ?? 0),
            "review:tarefa:{$entityId}:historico:{$historyId}:status:" . self::keyToken($status) . ':v1',
            $context
        );
    }

    public static function direction(array $context): array
    {
        $historyId = (int) ($context['historico_direcao_id'] ?? 0);
        return self::base(
            'review.direcao.validacao_solicitada',
            'funcao_imagem',
            (string) ($context['funcao_imagem_id'] ?? 0),
            (int) ($context['revisor_id'] ?? 0),
            "review:direcao:{$historyId}:solicitada:v1",
            $context
        );
    }

    public static function taskSentToDirection(array $context): array
    {
        $historyId = (int) ($context['historico_direcao_id'] ?? 0);
        $taskId = (int) ($context['funcao_imagem_id'] ?? 0);
        return self::base(
            'review.tarefa.enviada_direcao',
            'funcao_imagem',
            (string) $taskId,
            (int) ($context['revisor_id'] ?? 0),
            "review:tarefa:{$taskId}:historico:{$historyId}:enviada_direcao:v1",
            $context
        );
    }

    public static function sftpFailure(array $context): array
    {
        $entityId = (int) ($context['funcao_imagem_id'] ?? 0);
        $operation = self::keyToken((string) ($context['operation_id'] ?? $context['operacao'] ?? 'upload'));
        $attempt = max(1, (int) ($context['tentativa'] ?? 1));
        return self::base(
            'review.sftp.envio_falhou',
            'funcao_imagem',
            (string) $entityId,
            (int) ($context['actor_id'] ?? 0),
            "review:sftp:{$entityId}:operacao:{$operation}:tentativa:{$attempt}:falhou:v1",
            $context
        );
    }

    public static function slaExceeded(array $context): array
    {
        $entityId = (int) ($context['funcao_imagem_id'] ?? 0);
        $level = max(1, (int) ($context['nivel'] ?? 1));
        $reference = self::keyToken((string) ($context['janela_referencia'] ?? gmdate('Y-m-d')));
        return self::base(
            'review.aprovacao.sla_excedido',
            'funcao_imagem',
            (string) $entityId,
            0,
            "review:sla:{$entityId}:nivel:{$level}:janela:{$reference}:v1",
            $context
        );
    }

    private static function base(string $type, string $entityType, string $entityId, int $actorId, string $idempotency, array $payload): array
    {
        unset($payload['event_uuid'], $payload['correlation_id'], $payload['causation_event_uuid'], $payload['metadata']);
        return [
            'event_type' => $type,
            'event_version' => 1,
            'source_module' => 'flow_review',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'actor_id' => $actorId > 0 ? $actorId : null,
            'occurred_at' => gmdate('Y-m-d H:i:s'),
            'event_uuid' => null,
            'correlation_id' => function_exists('flow_connect_request_correlation_id')
                ? flow_connect_request_correlation_id()
                : EventEnvelope::uuidV4(),
            'causation_event_uuid' => null,
            'idempotency_key' => substr($idempotency, 0, 255),
            'payload' => self::sanitizePayload($payload),
            'metadata' => [
                'producer' => $payload['producer'] ?? 'FlowReview',
                'environment' => getenv('APP_ENV') ?: 'local',
            ],
        ];
    }

    private static function sanitizePayload(array $payload): array
    {
        unset($payload['producer'], $payload['operation_id'], $payload['actor_id']);
        foreach (['observacao', 'comentario', 'erro_tecnico_seguro'] as $field) {
            if (isset($payload[$field])) {
                $payload[$field] = self::truncate((string) $payload[$field], $field === 'erro_tecnico_seguro' ? 240 : 800);
            }
        }
        return $payload;
    }

    private static function truncate(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        return mb_strlen($value, 'UTF-8') <= $limit ? $value : mb_substr($value, 0, $limit - 1, 'UTF-8') . '…';
    }

    private static function keyToken(string $value): string
    {
        $value = strtolower(trim($value));
        $value = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return trim(preg_replace('/[^a-z0-9_-]+/', '_', $value) ?? '', '_');
    }
}
