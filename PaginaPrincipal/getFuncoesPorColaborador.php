<?php
header('Content-Type: application/json');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Conectar ao banco de dados
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/pendencias_operacionais_helper.php';

$colaboradorId = intval($_GET['colaborador_id']);
$nivelAcesso = isset($_SESSION['nivel_acesso']) ? (int) $_SESSION['nivel_acesso'] : 0;
date_default_timezone_set('America/Sao_Paulo');

// ====================
// FUNÇÕES (SEM FILTROS)
// ====================
$sql = "SELECT
    ico.idimagens_cliente_obra AS imagem_id,
    ico.imagem_nome,
    fi.status,
    fi.prazo,
    CASE
        WHEN fi.funcao_id = 4 AND si.nome_status = 'P00'
            THEN 'Escolha de Ângulos'
        ELSE
            f.nome_funcao
    END AS nome_funcao,
    fi.observacao,
    COALESCE(pc.prioridade, 3) AS prioridade,
    fi.idfuncao_imagem,
    fi.funcao_id,
    ico.obra_id,
    o.nomenclatura,
    o.nome_obra,
    ico.prazo AS imagem_prazo,
    ico.substatus_id AS imagem_status_id,
    ico.tipo_imagem,
    ico.subtipo_id,
    ico.idimagens_cliente_obra AS idimagem,
    si.nome_status,
    CASE
        WHEN fi.funcao_id IN (4, 7)
         AND ico.subtipo_id IS NOT NULL
         AND ico.subtipo_id <> 0
         AND LOWER(COALESCE(ico.tipo_imagem, '')) LIKE '%humanizada%'
         AND NOT EXISTS (
             SELECT 1
             FROM imagens_cliente_obra ico_sub
             JOIN funcao_imagem fi_comp
               ON fi_comp.imagem_id = ico_sub.idimagens_cliente_obra
              AND fi_comp.funcao_id = 3
             WHERE ico_sub.obra_id = ico.obra_id
               AND ico_sub.subtipo_id = ico.subtipo_id
               AND ico_sub.idimagens_cliente_obra <> ico.idimagens_cliente_obra
               AND fi_comp.status NOT IN ('Finalizado', 'Aprovado', 'Aprovado com ajustes')
             LIMIT 1
         )
            THEN 1
        WHEN fi.funcao_id IN (4, 7)
         AND ico.subtipo_id IS NOT NULL
         AND ico.subtipo_id <> 0
         AND LOWER(COALESCE(ico.tipo_imagem, '')) LIKE '%humanizada%'
            THEN 0
        ELSE 1
    END AS planta_humanizada_subtipo_composicoes_ok,
    (
        SELECT sh.justificativa
        FROM status_hold sh
        WHERE sh.imagem_id = ico.idimagens_cliente_obra
        ORDER BY sh.id DESC
        LIMIT 1
    ) AS hold_justificativa_recente,
    fi.file_uploaded_at,
    fi.requires_file_upload,
    CASE
        WHEN fi.funcao_id IN (4, 6)
         AND fi.status IN ('Aprovado', 'Aprovado com ajustes')
         AND (
             fi.funcao_id <> 6
             OR ico.tipo_imagem IS NULL
             OR LOWER(ico.tipo_imagem) NOT LIKE '%humanizada%'
         )
         AND EXISTS (
             SELECT 1
             FROM historico_aprovacoes ha
             WHERE ha.funcao_imagem_id = fi.idfuncao_imagem
               AND ha.status_novo IN ('Aprovado', 'Aprovado com ajustes')
               AND ha.responsavel IN (21, 2, 9, 31)
               AND NOT EXISTS (
                   SELECT 1
                   FROM historico_aprovacoes ha2
                   WHERE ha2.funcao_imagem_id = ha.funcao_imagem_id
                     AND ha2.id > ha.id
               )
             LIMIT 1
         )
         AND NOT EXISTS (
             SELECT 1
             FROM render_alta ra
             WHERE ra.imagem_id = ico.idimagens_cliente_obra
               AND ra.status_id = ico.status_id
             LIMIT 1
         )
        THEN 1
        ELSE 0
    END AS requires_render_send,
    TIMESTAMPDIFF(
        MINUTE,
        (SELECT la.data FROM log_alteracoes la
         WHERE la.funcao_imagem_id = fi.idfuncao_imagem
           AND la.status_novo = 'Em andamento'
         ORDER BY la.data ASC LIMIT 1),
        (SELECT la.data FROM log_alteracoes la
         WHERE la.funcao_imagem_id = fi.idfuncao_imagem
           AND la.status_novo = 'Em aprovação'
         ORDER BY la.data ASC LIMIT 1)
    ) AS tempo_em_andamento,
    (
        SELECT COUNT(*)
        FROM comentarios_imagem ci
        JOIN historico_aprovacoes_imagens hi2
          ON ci.ap_imagem_id = hi2.id
        WHERE hi2.funcao_imagem_id = fi.idfuncao_imagem
          AND hi2.indice_envio = (
              SELECT MAX(hi3.indice_envio)
              FROM historico_aprovacoes_imagens hi3
              WHERE hi3.funcao_imagem_id = fi.idfuncao_imagem
          )
    ) AS comentarios_ultima_versao,
    (
        SELECT MAX(hi.indice_envio)
        FROM historico_aprovacoes_imagens hi
        WHERE hi.funcao_imagem_id = fi.idfuncao_imagem
    ) AS indice_envio_atual
    ,(
        SELECT COALESCE(COUNT(*),0)
        FROM notificacoes_gerais n
        WHERE n.funcao_imagem_id = fi.idfuncao_imagem
          AND n.lida = 0
          AND n.colaborador_id = ?
    ) AS notificacoes_nao_lidas,
        (
            CASE WHEN fi.funcao_id = 4 THEN
                COALESCE(
                    -- Se houver arquivo da categoria 7 (ângulo definido) para esta imagem, use-o (mais recente)
                    (SELECT a.caminho FROM arquivos a WHERE a.imagem_id = ico.idimagens_cliente_obra AND a.categoria_id = 7 AND a.status = 'atualizado' ORDER BY a.idarquivo DESC LIMIT 1),
                    -- Senão, fallback para o histórico de aprovações como antes
                    (SELECT MAX(hi.caminho_imagem) FROM historico_aprovacoes_imagens hi WHERE hi.funcao_imagem_id = fi.idfuncao_imagem AND hi.caminho_imagem NOT LIKE '%imagem_%')
                )
            ELSE
                (SELECT MAX(hi2.caminho_imagem) FROM historico_aprovacoes_imagens hi2 WHERE hi2.funcao_imagem_id = fi.idfuncao_imagem AND hi2.caminho_imagem NOT LIKE '%imagem_%')
            END
        ) AS ultima_imagem
FROM funcao_imagem fi
JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
LEFT JOIN status_imagem si ON ico.status_id = si.idstatus
JOIN obra o ON o.idobra = ico.obra_id
JOIN funcao f ON fi.funcao_id = f.idfuncao
LEFT JOIN prioridade_funcao pc ON fi.idfuncao_imagem = pc.funcao_imagem_id
WHERE fi.colaborador_id = ?
  AND o.status_obra = 0
ORDER BY requires_file_upload DESC, notificacoes_nao_lidas DESC, prioridade ASC, prazo DESC, imagem_id, obra_id,
    FIELD(fi.status,
          'Não iniciado','HOLD','Em andamento','Ajuste',
          'Em aprovação','Aguardando Direção','Aprovado com ajustes','Aprovado','Finalizado')";

$stmt = $conn->prepare($sql);
// há dois placeholders: um no subquery (colaborador_id) e outro no WHERE fi.colaborador_id
$stmt->bind_param("ii", $colaboradorId, $colaboradorId);
$stmt->execute();
$result = $stmt->get_result();
$funcoes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ====================
// FUNCAO_ANIMACAO (para o Kanban)
// ====================
$sqlAnimFuncoes = "SELECT
    fa.id                AS idfuncao_imagem,
    fa.animacao_id,
    fa.funcao_id,
    fa.status,
    fa.prazo,
    fa.observacao,
    fa.valor,
    0                    AS requires_file_upload,
    NULL                 AS file_uploaded_at,
    f.nome_funcao,
    a.imagem_id,
    a.tipo_animacao,
    CONCAT(
        ico.imagem_nome,
        ' - ',
        UCASE(SUBSTRING(a.tipo_animacao, 1, 1)),
        LOWER(SUBSTRING(a.tipo_animacao, 2))
    ) AS nome_animacao,
    ico.obra_id,
    ico.prazo            AS imagem_prazo,
    ico.substatus_id     AS imagem_status_id,
    si.nome_status,
    o.nomenclatura,
    o.nome_obra
FROM funcao_animacao fa
JOIN animacao a             ON a.idanimacao = fa.animacao_id
JOIN funcao f               ON f.idfuncao   = fa.funcao_id
JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = a.imagem_id
JOIN obra o                 ON o.idobra = ico.obra_id
LEFT JOIN status_imagem si  ON si.idstatus = ico.status_id
WHERE fa.colaborador_id = ?
  AND o.status_obra = 0
ORDER BY fa.prazo ASC";

$stmtAnim = $conn->prepare($sqlAnimFuncoes);
$stmtAnim->bind_param('i', $colaboradorId);
$stmtAnim->execute();
$animFuncoes = $stmtAnim->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtAnim->close();

// ====================
// TAREFAS
// ====================
$sqlTarefas = "SELECT 
    id,
    titulo,
    descricao,
    prazo,
    status,
    prioridade,
    CASE 
        WHEN status = 'Finalizado' THEN 'Finalizada'
        WHEN prazo < CURDATE() THEN 'Atrasada'
        WHEN prazo = CURDATE() THEN 'Hoje'
        ELSE 'Dentro do prazo'
    END AS situacao
FROM tarefas
WHERE colaborador_id = ?
  AND status <> 'Finalizado'
ORDER BY prazo ASC";
$stmtTarefas = $conn->prepare($sqlTarefas);
$stmtTarefas->bind_param("i", $colaboradorId);
$stmtTarefas->execute();
$resultTarefas = $stmtTarefas->get_result();
$tarefas = $resultTarefas->fetch_all(MYSQLI_ASSOC);
$stmtTarefas->close();

// ====================
// PENDENCIAS FLOW REVIEW
// ====================
$pendenciasFlowReview = [];
$mostrarColunaPendencias = false;
$isNicollePendencias = ($colaboradorId === 1);
$isPedroPendencias = ($colaboradorId === 21);
$isDirecaoPendencias = in_array($colaboradorId, [9, 31], true);
$isFinalizadorPendencias = false;

if (!$isPedroPendencias && !$isNicollePendencias && !$isDirecaoPendencias) {
    $sqlFinalizadorPendencias = "SELECT 1
        FROM funcao_imagem fi_final
        JOIN imagens_cliente_obra ico_final ON ico_final.idimagens_cliente_obra = fi_final.imagem_id
        JOIN obra o_final ON o_final.idobra = ico_final.obra_id
        WHERE fi_final.colaborador_id = ?
          AND fi_final.funcao_id = 4
          AND o_final.status_obra = 0
        LIMIT 1";

    if ($stmtFinalizadorPendencias = $conn->prepare($sqlFinalizadorPendencias)) {
        $stmtFinalizadorPendencias->bind_param('i', $colaboradorId);
        $stmtFinalizadorPendencias->execute();
        $stmtFinalizadorPendencias->store_result();
        $isFinalizadorPendencias = $stmtFinalizadorPendencias->num_rows > 0;
        $stmtFinalizadorPendencias->close();
    }
}

$mostrarPendenciasFlowReview = $isPedroPendencias || $isNicollePendencias || $isDirecaoPendencias || $isFinalizadorPendencias;
$mostrarColunaPendencias = true;
$hasSlaFuncao = false;
$resSla = $conn->query("SHOW TABLES LIKE 'sla_funcao'");
if ($resSla && $resSla->num_rows > 0) {
    $hasSlaFuncao = true;
}
if ($resSla) {
    $resSla->close();
}

$slaSelect = $hasSlaFuncao ? 'sf.limite_horas' : 'NULL';
$slaJoin = $hasSlaFuncao ? 'LEFT JOIN sla_funcao sf ON sf.funcao_id = fi.funcao_id' : '';

if (!function_exists('pendenciasFlowReviewInicioSlaUtil')) {
    function pendenciasFlowReviewInicioSlaUtil(DateTime $data): DateTime
    {
        $inicioDia = (clone $data)->setTime(8, 0, 0);
        $fimDia = (clone $data)->setTime(18, 0, 0);

        if ($data < $inicioDia) {
            return $inicioDia;
        }

        if ($data >= $fimDia) {
            return (clone $data)->modify('+1 day')->setTime(8, 0, 0);
        }

        return clone $data;
    }
}

if (!function_exists('pendenciasFlowReviewSomarMinutosUteis')) {
    function pendenciasFlowReviewSomarMinutosUteis(?string $dataBase, int $minutos): ?string
    {
        if (empty($dataBase) || $minutos <= 0) {
            return $dataBase ?: null;
        }

        try {
            $cursor = pendenciasFlowReviewInicioSlaUtil(new DateTime($dataBase));
        } catch (Exception $e) {
            return null;
        }

        $restante = $minutos;
        while ($restante > 0) {
            $fimDia = (clone $cursor)->setTime(18, 0, 0);
            $disponivel = max(0, (int) floor(($fimDia->getTimestamp() - $cursor->getTimestamp()) / 60));

            if ($restante <= $disponivel) {
                $cursor->modify('+' . $restante . ' minutes');
                return $cursor->format('Y-m-d H:i:s');
            }

            $restante -= $disponivel;
            $cursor = (clone $cursor)->modify('+1 day')->setTime(8, 0, 0);
        }

        return $cursor->format('Y-m-d H:i:s');
    }
}

if (!function_exists('pendenciasFlowReviewMinutosUteisDecorridos')) {
    function pendenciasFlowReviewMinutosUteisDecorridos(?string $dataBase, ?string $dataFim = null): ?int
    {
        if (empty($dataBase)) {
            return null;
        }

        try {
            $cursor = pendenciasFlowReviewInicioSlaUtil(new DateTime($dataBase));
            $fim = $dataFim ? new DateTime($dataFim) : new DateTime();
        } catch (Exception $e) {
            return null;
        }

        if ($fim <= $cursor) {
            return 0;
        }

        $total = 0;
        while ($cursor < $fim) {
            $fimDia = (clone $cursor)->setTime(18, 0, 0);
            $fimTrecho = ($fim < $fimDia) ? clone $fim : $fimDia;

            if ($fimTrecho > $cursor) {
                $total += (int) floor(($fimTrecho->getTimestamp() - $cursor->getTimestamp()) / 60);
            }

            $cursor = (clone $cursor)->modify('+1 day')->setTime(8, 0, 0);
        }

        return $total;
    }
}

if ($mostrarPendenciasFlowReview) {
    $pendenciasExtraJoin = '';
    $pendenciasWhere = '';
    $pendenciasTipo = "'Aprovação'";
    $pendenciasBindTypes = '';
    $pendenciasBindValues = [];

    if ($isPedroPendencias) {
        $pendenciasWhere = "(
              (fi.funcao_id IN (1, 2, 3, 8) AND fi.status = 'Em aprovação')
              OR (fi.funcao_id = 4 AND fi.status IN ('Em aprovação', 'Aguardando Direção'))
              OR (fi.funcao_id = 6 AND fi.status IN ('Em aprovação', 'Aguardando Direção'))
              OR (fi.funcao_id = 5 AND fi.status = 'Em aprovação')
          )";
        $pendenciasTipo = "CASE
            WHEN fi.funcao_id = 5 THEN 'Pós-produção'
            WHEN fi.status = 'Aguardando Direção' THEN 'Direção'
            WHEN fi.funcao_id = 6 THEN 'Revisão'
            ELSE 'Aprovação'
        END";
    } elseif ($isNicollePendencias) {
        $pendenciasWhere = "(
              (fi.funcao_id IN (8, 1, 2, 3) AND fi.status = 'Em aprovação')
              OR (fi.funcao_id = 4 AND fi.status = 'Em aprovação' AND ico.status_id <> 1)
              OR (fi.funcao_id = 6 AND fi.status = 'Em aprovação')
          )
          AND NOT EXISTS (
              SELECT 1
              FROM historico_aprovacoes ha_nicolle
              WHERE ha_nicolle.funcao_imagem_id = fi.idfuncao_imagem
                AND ha_nicolle.responsavel = 1
                AND ha_nicolle.data_aprovacao >= COALESCE(hi_latest.data_envio, al_latest.criado_em)
                AND ha_nicolle.status_novo IN ('Aprovado', 'Aprovado com ajustes', 'Ajuste', 'Finalizado', 'Reprovado')
              LIMIT 1
          )";
        $pendenciasTipo = "CASE WHEN fi.funcao_id = 6 THEN 'Revisão' ELSE 'Aprovação' END";
    } elseif ($isDirecaoPendencias) {
        $pendenciasWhere = "(
              (fi.funcao_id = 4 AND ico.status_id = 1 AND fi.status = 'Em aprovação')
              OR (fi.funcao_id = 4 AND fi.status = 'Aguardando Direção')
              OR (fi.funcao_id = 6 AND fi.status = 'Aguardando Direção')
              OR (fi.funcao_id = 5 AND fi.status = 'Em aprovação')
          )";
        $pendenciasTipo = "CASE
            WHEN fi.funcao_id = 5 THEN 'Pós-produção'
            WHEN fi.status = 'Aguardando Direção' THEN 'Direção'
            ELSE 'Aprovação'
        END";
    } else {
        $pendenciasWhere = "fi.funcao_id = 5
  AND fi.status = 'Em aprovação'
  AND (
      (
          EXISTS (
              SELECT 1
              FROM funcao_imagem fi_rev
              WHERE fi_rev.imagem_id = fi.imagem_id
                AND fi_rev.funcao_id = 6
          )
          AND EXISTS (
              SELECT 1
              FROM funcao_imagem fi_rev_user
              WHERE fi_rev_user.imagem_id = fi.imagem_id
                AND fi_rev_user.funcao_id = 6
                AND fi_rev_user.colaborador_id = ?
          )
      )
      OR
      (
          NOT EXISTS (
              SELECT 1
              FROM funcao_imagem fi_rev_exists
              WHERE fi_rev_exists.imagem_id = fi.imagem_id
                AND fi_rev_exists.funcao_id = 6
          )
          AND EXISTS (
              SELECT 1
              FROM funcao_imagem fi_fin_user
              WHERE fi_fin_user.imagem_id = fi.imagem_id
                AND fi_fin_user.funcao_id = 4
                AND fi_fin_user.colaborador_id = ?
          )
      )
  )
  AND NOT EXISTS (
      SELECT 1
      FROM historico_aprovacoes ha_finalizador
      WHERE ha_finalizador.responsavel = ?
        AND ha_finalizador.data_aprovacao >= COALESCE(hi_latest.data_envio, al_latest.criado_em)
        AND ha_finalizador.status_novo IN ('Aprovado', 'Aprovado com ajustes', 'Ajuste', 'Finalizado', 'Reprovado')
        AND ha_finalizador.funcao_imagem_id IN (
            SELECT fi_resp.idfuncao_imagem
            FROM funcao_imagem fi_resp
            WHERE fi_resp.imagem_id = fi.imagem_id
              AND fi_resp.colaborador_id = ?
              AND fi_resp.funcao_id IN (4, 6)
        )
      LIMIT 1
  )";

        $pendenciasTipo = "'Pós-produção'";
        $pendenciasBindTypes = 'iiii';
        $pendenciasBindValues[] = $colaboradorId; // função 6
        $pendenciasBindValues[] = $colaboradorId; // função 4 fallback
        $pendenciasBindValues[] = $colaboradorId; // histórico responsável
        $pendenciasBindValues[] = $colaboradorId; // função do histórico
    }

    $sqlPendencias = "SELECT DISTINCT
    fi.idfuncao_imagem,
    fi.imagem_id,
    fi.funcao_id,
    fi.status,
    fi.prazo,
    CASE
        WHEN fi.funcao_id = 4 AND si.nome_status = 'P00'
            THEN 'Escolha de Ângulos'
        ELSE f.nome_funcao
    END AS nome_funcao,
    ico.imagem_nome,
    ico.obra_id,
    ico.status_id AS imagem_status_id,
    o.nomenclatura,
    o.nome_obra,
    c.idcolaborador AS responsavel_id,
    c.nome_colaborador AS responsavel_nome,
    hi_latest.id AS historico_imagem_id,
    al_latest.id AS arquivo_log_id,
    CASE
        WHEN hi_latest.id IS NULL AND al_latest.id IS NOT NULL THEN 'PDF'
        ELSE hi_latest.indice_envio
    END AS indice_envio_atual,
    COALESCE(hi_latest.data_envio, al_latest.criado_em) AS data_postagem_flowreview,
    TIMESTAMPDIFF(MINUTE, COALESCE(hi_latest.data_envio, al_latest.criado_em), NOW()) AS tempo_decorrido_minutos,
    $slaSelect AS sla_limite_horas,
    CASE
        WHEN $slaSelect IS NULL THEN NULL
        ELSE DATE_ADD(COALESCE(hi_latest.data_envio, al_latest.criado_em), INTERVAL $slaSelect HOUR)
    END AS prazo_sla,
    CASE
        WHEN $slaSelect IS NOT NULL
         AND TIMESTAMPDIFF(HOUR, COALESCE(hi_latest.data_envio, al_latest.criado_em), NOW()) >= $slaSelect
            THEN 1
        ELSE 0
    END AS atrasada,
    (
        CASE
            WHEN hi_latest.id IS NULL AND al_latest.id IS NOT NULL THEN (
                SELECT COUNT(*)
                FROM comentarios_imagem ci_pdf
                WHERE ci_pdf.arquivo_log_id = al_latest.id
            )
            ELSE (
                SELECT COUNT(*)
                FROM comentarios_imagem ci
                JOIN historico_aprovacoes_imagens hi_comments ON hi_comments.id = ci.ap_imagem_id
                WHERE hi_comments.funcao_imagem_id = fi.idfuncao_imagem
                  AND hi_comments.indice_envio = hi_latest.indice_envio
            )
        END
    ) AS comentarios_ultima_versao,
    (
        CASE
            WHEN hi_latest.id IS NULL AND al_latest.id IS NOT NULL THEN al_latest.caminho
            ELSE (
                SELECT MAX(hi_img.caminho_imagem)
                FROM historico_aprovacoes_imagens hi_img
                WHERE hi_img.funcao_imagem_id = fi.idfuncao_imagem
                  AND hi_img.caminho_imagem NOT LIKE '%imagem_%'
            )
        END
    ) AS ultima_imagem,
    $pendenciasTipo AS tipo_pendencia
FROM funcao_imagem fi
$pendenciasExtraJoin
JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
LEFT JOIN status_imagem si ON ico.status_id = si.idstatus
JOIN obra o ON o.idobra = ico.obra_id
JOIN funcao f ON fi.funcao_id = f.idfuncao
LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
$slaJoin
LEFT JOIN historico_aprovacoes_imagens hi_latest
  ON hi_latest.id = (
      SELECT hi2.id
      FROM historico_aprovacoes_imagens hi2
      WHERE hi2.funcao_imagem_id = fi.idfuncao_imagem
      ORDER BY hi2.data_envio DESC, hi2.id DESC
      LIMIT 1
  )
LEFT JOIN arquivo_log al_latest
  ON al_latest.id = (
      SELECT al2.id
      FROM arquivo_log al2
      WHERE al2.funcao_imagem_id = fi.idfuncao_imagem
        AND UPPER(al2.tipo) = 'PDF'
      ORDER BY al2.criado_em DESC, al2.id DESC
      LIMIT 1
  )
WHERE o.status_obra = 0
  AND (
      (fi.funcao_id IN (1, 8) AND al_latest.id IS NOT NULL)
      OR (fi.funcao_id NOT IN (1, 8) AND hi_latest.id IS NOT NULL)
  )
  AND $pendenciasWhere
ORDER BY atrasada DESC, COALESCE(hi_latest.data_envio, al_latest.criado_em) ASC, fi.prazo ASC";

    $stmtPendencias = $conn->prepare($sqlPendencias);
    if ($stmtPendencias) {
        if ($pendenciasBindTypes !== '') {
            $stmtPendencias->bind_param($pendenciasBindTypes, ...$pendenciasBindValues);
        }
        $stmtPendencias->execute();
        $pendenciasFlowReview = $stmtPendencias->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtPendencias->close();

        foreach ($pendenciasFlowReview as &$pendenciaFlowReview) {
            $slaHoras = isset($pendenciaFlowReview['sla_limite_horas']) && is_numeric($pendenciaFlowReview['sla_limite_horas'])
                ? (float) $pendenciaFlowReview['sla_limite_horas']
                : 0.0;
            $dataPostagemFlowReview = $pendenciaFlowReview['data_postagem_flowreview'] ?? null;

            if ($slaHoras > 0 && $dataPostagemFlowReview) {
                $slaMinutos = (int) round($slaHoras * 60);
                $prazoSlaUtil = pendenciasFlowReviewSomarMinutosUteis($dataPostagemFlowReview, $slaMinutos);
                $tempoUtilDecorrido = pendenciasFlowReviewMinutosUteisDecorridos($dataPostagemFlowReview);

                $pendenciaFlowReview['prazo_sla'] = $prazoSlaUtil;
                $pendenciaFlowReview['tempo_decorrido_minutos'] = $tempoUtilDecorrido;
                $pendenciaFlowReview['atrasada'] = 0;

                if ($prazoSlaUtil) {
                    try {
                        $pendenciaFlowReview['atrasada'] = (new DateTime() > new DateTime($prazoSlaUtil)) ? 1 : 0;
                    } catch (Exception $e) {
                        $pendenciaFlowReview['atrasada'] = 0;
                    }
                }
            }
        }
        unset($pendenciaFlowReview);

        usort($pendenciasFlowReview, function ($a, $b) {
            $atrasadaA = (int) ($a['atrasada'] ?? 0);
            $atrasadaB = (int) ($b['atrasada'] ?? 0);
            if ($atrasadaA !== $atrasadaB) {
                return $atrasadaB <=> $atrasadaA;
            }

            $prazoA = strtotime((string) ($a['prazo_sla'] ?? '')) ?: PHP_INT_MAX;
            $prazoB = strtotime((string) ($b['prazo_sla'] ?? '')) ?: PHP_INT_MAX;
            if ($prazoA !== $prazoB) {
                return $prazoA <=> $prazoB;
            }

            return strcmp((string) ($a['data_postagem_flowreview'] ?? ''), (string) ($b['data_postagem_flowreview'] ?? ''));
        });
    }
}

// ====================
// MÉDIA TEMPO EM ANDAMENTO
// ====================
$sqlMedia = "SELECT 
    f.nome_funcao,
    fi.funcao_id,
    ROUND(AVG(TIMESTAMPDIFF(
        MINUTE,
        (SELECT la1.data FROM log_alteracoes la1
         WHERE la1.funcao_imagem_id = fi.idfuncao_imagem
           AND la1.status_novo = 'Em andamento'
         ORDER BY la1.data ASC LIMIT 1),
        (SELECT la2.data FROM log_alteracoes la2
         WHERE la2.funcao_imagem_id = fi.idfuncao_imagem
           AND la2.status_novo = 'Em aprovação'
         ORDER BY la2.data ASC LIMIT 1)
    ))) AS media_tempo
FROM funcao_imagem fi
JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
JOIN obra o ON o.idobra = ico.obra_id
JOIN funcao f ON f.idfuncao = fi.funcao_id
WHERE fi.colaborador_id = ? 
  AND o.status_obra = 0
GROUP BY fi.funcao_id";

$stmtMedia = $conn->prepare($sqlMedia);
$stmtMedia->bind_param("i", $colaboradorId);
$stmtMedia->execute();
$resultMedia = $stmtMedia->get_result();
$mediaTemposPorFuncao = [];
while ($row = $resultMedia->fetch_assoc()) {
    $mediaTemposPorFuncao[$row['funcao_id']] = intval($row['media_tempo']);
}
$stmtMedia->close();

// ====================
// Função para calcular tempo por status
// ====================
function calcularTempo($logs, $statusAtual)
{
    $tempoCalculado = null;
    switch ($statusAtual) {
        case 'Em aprovação':
            $dataAprovacao = null;

            // Ordena os logs por data decrescente (mais recente primeiro)
            usort($logs, function ($a, $b) {
                return strtotime($b['data']) <=> strtotime($a['data']);
            });

            // Busca a primeira ocorrência de "Em aprovação" mais recente
            foreach ($logs as $log) {
                if ($log['status_novo'] === 'Em aprovação') {
                    $dataAprovacao = new DateTime($log['data']);
                    break;
                }
            }

            if ($dataAprovacao) {
                $diff = $dataAprovacao->diff(new DateTime()); // tempo até agora
                $tempoCalculado = $diff->days * 1440 + $diff->h * 60 + $diff->i;
            }
            break;

        case 'Não iniciado':
            foreach ($logs as $log) {
                if ($log['status_novo'] === 'Não iniciado' && $log['status_anterior'] === null) {
                    $dataInicio = new DateTime($log['data']);
                    $diff = $dataInicio->diff(new DateTime());
                    $tempoCalculado = $diff->days * 1440 + $diff->h * 60 + $diff->i;
                    break; // usa o registro da trigger (status_anterior NULL) = momento da criação
                }
            }
            break;

        case 'Em andamento':
            foreach ($logs as $log) {
                if ($log['status_novo'] === 'Em andamento') {
                    $dataInicio = new DateTime($log['data']);
                    $diff = $dataInicio->diff(new DateTime());
                    $tempoCalculado = $diff->days * 1440 + $diff->h * 60 + $diff->i;
                    break;
                }
            }
            break;

        case 'Ajuste':
        case 'HOLD':
            foreach ($logs as $log) {
                if ($log['status_novo'] === $statusAtual) {
                    $dataStatus = new DateTime($log['data']);
                    $diff = $dataStatus->diff(new DateTime());
                    $tempoCalculado = $diff->days * 1440 + $diff->h * 60 + $diff->i;
                }
            }
            break;

        case 'Finalizado':
        case 'Aprovado':
        case 'Aprovado com ajustes':
            if (count($logs) > 1) {
                $primeira = new DateTime($logs[0]['data']);
                $ultima   = new DateTime(end($logs)['data']);
                $diff = $primeira->diff($ultima);
                $tempoCalculado = $diff->days * 1440 + $diff->h * 60 + $diff->i;
            }
            break;

        default:
            $tempoCalculado = null;
            break;
    }

    return $tempoCalculado;
}

// ====================
// Consulta única para logs de todas funções
// ====================
$funcaoImagemIds = array_column($funcoes, 'idfuncao_imagem');
$logsPorFuncao = [];

if (count($funcaoImagemIds) > 0) {
    $inIds = implode(',', array_fill(0, count($funcaoImagemIds), '?'));
    $sqlLogsAll = "SELECT funcao_imagem_id, status_anterior, status_novo, data 
                   FROM log_alteracoes 
                   WHERE funcao_imagem_id IN ($inIds) 
                   ORDER BY data ASC";
    $stmtLogsAll = $conn->prepare($sqlLogsAll);
    $types = str_repeat('i', count($funcaoImagemIds));
    $stmtLogsAll->bind_param($types, ...$funcaoImagemIds);
    $stmtLogsAll->execute();
    $resultLogsAll = $stmtLogsAll->get_result();
    while ($row = $resultLogsAll->fetch_assoc()) {
        $logsPorFuncao[$row['funcao_imagem_id']][] = $row;
    }
    $stmtLogsAll->close();
}

// ====================
// Ajusta Funções (liberação, ordem, etc.)
// ====================
$imagemIds = array_unique(array_column($funcoes, 'imagem_id')); // <- unique para evitar placeholders duplicados
$todasFuncoes = [];

// ====================
// Consulta liberar_modelagem por obra
// ====================
$obraIds = array_unique(array_column($funcoes, 'obra_id'));
$liberarModelagemPorObra = [];
if (count($obraIds) > 0) {
    $inObra = implode(',', array_fill(0, count($obraIds), '?'));
    $stmtLM = $conn->prepare("SELECT idobra, liberar_modelagem FROM obra WHERE idobra IN ($inObra)");
    $typesLM = str_repeat('i', count($obraIds));
    $stmtLM->bind_param($typesLM, ...$obraIds);
    $stmtLM->execute();
    $resultLM = $stmtLM->get_result();
    while ($row = $resultLM->fetch_assoc()) {
        $liberarModelagemPorObra[intval($row['idobra'])] = intval($row['liberar_modelagem']);
    }
    $stmtLM->close();
}

if (count($imagemIds) > 0) {
    $inImagem = implode(',', array_fill(0, count($imagemIds), '?'));
    $sqlTodasFuncoes = "SELECT fi.imagem_id, fi.funcao_id, fi.status, fi.prazo, fi.colaborador_id,
                        COALESCE(c.nome_colaborador, '') AS nome_colaborador
                        FROM funcao_imagem fi
                        LEFT JOIN colaborador c ON fi.colaborador_id = c.idcolaborador
                        WHERE fi.imagem_id IN ($inImagem)";
    $stmtTodas = $conn->prepare($sqlTodasFuncoes);
    $typesTodas = str_repeat('i', count($imagemIds));
    $stmtTodas->bind_param($typesTodas, ...$imagemIds);
    $stmtTodas->execute();
    $resultTodas = $stmtTodas->get_result();
    while ($row = $resultTodas->fetch_assoc()) {
        $todasFuncoes[$row['imagem_id']][$row['funcao_id']] = $row;
    }
    $stmtTodas->close();
}

$ordemFuncoes = [
    1 => 'Caderno',
    8 => 'Filtro de assets',
    2 => 'Modelagem',
    3 => 'Composição',
    9 => 'Pré-Finalização',
    4 => 'Finalização',
    5 => 'Pós-produção',
    6 => 'Alteração',
    7 => 'Planta Humanizada'
];

$funcoesFinal = [];
$ordemIds = array_keys($ordemFuncoes);

// ====================
// Descobre a primeira função REAL de cada imagem (USANDO todasFuncoes)
// ====================
$primeiraFuncaoImagem = [];
foreach ($todasFuncoes as $img => $listaFuncoes) {
    $menorPos = PHP_INT_MAX;
    $primeira = null;
    foreach ($listaFuncoes as $funcaoId => $dados) {
        $pos = array_search($funcaoId, $ordemIds);
        if ($pos !== false && $pos < $menorPos) {
            $menorPos = $pos;
            $primeira = $funcaoId;
        }
    }
    if ($primeira !== null) {
        $primeiraFuncaoImagem[$img] = $primeira;
    }
}

// ====================
// Agora processa as funções (usando primeiraFuncaoImagem calculada corretamente)
// ====================
foreach ($funcoes as $funcao) {
    $funcaoAtualId = $funcao['funcao_id'];
    $imagemId      = $funcao['imagem_id'];
    $indiceAtual   = array_search($funcaoAtualId, $ordemIds);
    $nomeStatusImagem = isset($funcao['nome_status']) ? trim($funcao['nome_status']) : '';
    $nomeStatusImagemLower = function_exists('mb_strtolower') ? mb_strtolower($nomeStatusImagem) : strtolower($nomeStatusImagem);
    $imagemStatusId = isset($funcao['imagem_status_id']) ? intval($funcao['imagem_status_id']) : null;
    $imagemEmHold = ($nomeStatusImagemLower === 'hold') || ($imagemStatusId === 7);
    $tipoImagem = isset($funcao['tipo_imagem']) ? (string)$funcao['tipo_imagem'] : '';
    $tipoImagemLower = function_exists('mb_strtolower') ? mb_strtolower($tipoImagem) : strtolower($tipoImagem);
    $subtipoId = isset($funcao['subtipo_id']) ? intval($funcao['subtipo_id']) : 0;
    $isPlantaHumanizadaAtual = in_array((int)$funcaoAtualId, [4, 7], true)
        && strpos($tipoImagemLower, 'humanizada') !== false;

    $statusAnterior   = null;
    $liberada         = false;
    $funcaoAnteriorId = null;
    $prazoAnterior    = null;

    // HOLD da imagem é universal: bloqueia todas as funções da imagem
    if ($imagemEmHold) {
        $liberada = false;
    }
    // Se for Alteração (funcao_id == 6), sempre libera
    elseif ($funcaoAtualId == 6) {
        $liberada = true;
    }
    // Se for Modelagem (funcao_id == 2) e a obra tem liberar_modelagem=1, libera direto
    elseif ($funcaoAtualId == 2 && !empty($liberarModelagemPorObra[intval($funcao['obra_id'])])) {
        $liberada = true;
    }
    // Se for Composição (funcao_id == 3) e a obra tem liberar_modelagem=1,
    // exige que tanto Modelagem (2) quanto Filtro de assets (8) estejam finalizados
    elseif ($funcaoAtualId == 3 && !empty($liberarModelagemPorObra[intval($funcao['obra_id'])])) {
        $statusFinalizado = ['Finalizado', 'Aprovado', 'Aprovado com ajustes'];
        $modelagem    = isset($todasFuncoes[$imagemId][2]) ? $todasFuncoes[$imagemId][2] : null;
        $filtroAssets = isset($todasFuncoes[$imagemId][8]) ? $todasFuncoes[$imagemId][8] : null;

        // Se modelagem não está atribuída à imagem, não bloqueia
        $modelagemOk  = $modelagem === null || in_array($modelagem['status'], $statusFinalizado);
        // Se filtro de assets não está atribuído à imagem, não bloqueia
        $filtroOk     = $filtroAssets === null || in_array($filtroAssets['status'], $statusFinalizado);

        if ($modelagemOk && $filtroOk) {
            $liberada = true;
        }
    }
    // Se esta é a primeira função REAL da imagem, libera sempre
    elseif (isset($primeiraFuncaoImagem[$imagemId]) && $primeiraFuncaoImagem[$imagemId] == $funcaoAtualId) {
        $liberada = true;
    }
    // Caso contrário, aplica a regra normal (procura anterior EXISTENTE na ordem oficial)
    elseif ($indiceAtual !== false && $indiceAtual > 0 && isset($todasFuncoes[$imagemId])) {
        for ($i = $indiceAtual - 1; $i >= 0; $i--) {
            $funcaoAnteriorId = $ordemIds[$i];
            if (isset($todasFuncoes[$imagemId][$funcaoAnteriorId])) {
                $rowAnterior    = $todasFuncoes[$imagemId][$funcaoAnteriorId];
                $statusAnterior = $rowAnterior['status'];
                $prazoAnterior  = $rowAnterior['prazo'];

                // Additionally liberate if the previous function's collaborator is 'Não se aplica'
                // or its colaborador_id equals 15
                $prevCollabId = isset($rowAnterior['colaborador_id']) ? intval($rowAnterior['colaborador_id']) : null;
                $prevCollabName = isset($rowAnterior['nome_colaborador']) ? $rowAnterior['nome_colaborador'] : '';
                $prevCollabNameLower = (function ($s) {
                    return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
                })($prevCollabName);

                $naoSeAplica = function_exists('mb_strtolower') ? mb_strtolower('Não se aplica') : strtolower('Não se aplica');

                if (
                    in_array($statusAnterior, ['Finalizado', 'Aprovado', 'Aprovado com ajustes'])
                    || $prevCollabId === 15
                    || $prevCollabNameLower === $naoSeAplica
                ) {
                    $liberada = true;
                }
                break;
            }
        }
    }

    if (
        $liberada
        && $isPlantaHumanizadaAtual
        && $subtipoId > 0
        && empty($funcao['planta_humanizada_subtipo_composicoes_ok'])
    ) {
        $liberada = false;
    }

    // Calcular tempo por status usando logs já consultados
    $funcaoId       = $funcao['idfuncao_imagem'];
    $logs           = isset($logsPorFuncao[$funcaoId]) ? $logsPorFuncao[$funcaoId] : [];
    $tempoCalculado = calcularTempo($logs, $funcao['status']);

    $funcoesFinal[] = [
        'imagem_id'                  => $funcao['imagem_id'],
        'imagem_nome'                => $funcao['imagem_nome'],
        'status'                     => $funcao['status'],
        'prazo'                      => $funcao['prazo'],
        'nome_funcao'                => $funcao['nome_funcao'],
        'prioridade'                 => $funcao['prioridade'],
        'funcao_id'                  => $funcao['funcao_id'],
        'tipo_imagem'                => $funcao['tipo_imagem'],
        'subtipo_id'                 => $subtipoId > 0 ? $subtipoId : null,
        'nome_status'                  => $funcao['nome_status'],
        'hold_justificativa_recente' => $funcao['hold_justificativa_recente'] ?? null,
        'justificativa'              => $funcao['hold_justificativa_recente'] ?? null,
        'descricao'                  => $funcao['hold_justificativa_recente'] ?? null,
        'status_funcao_anterior'     => $statusAnterior,
        'prazo_funcao_anterior'      => $prazoAnterior,
        'liberada'                   => $liberada,
        'funcaoAnteriorId'           => $funcaoAnteriorId,
        'obra_id'                    => $funcao['obra_id'],
        'nomenclatura'               => $funcao['nomenclatura'],
        'nome_obra'                  => $funcao['nome_obra'],
        'idfuncao_imagem'            => $funcao['idfuncao_imagem'],
        'imagem_status_id'           => isset($funcao['imagem_status_id']) ? intval($funcao['imagem_status_id']) : null,
        'tempo_em_andamento'         => $funcao['tempo_em_andamento'],
        'imagem_prazo'               => $funcao['imagem_prazo'],
        'comentarios_ultima_versao'  => $funcao['comentarios_ultima_versao'],
        'indice_envio_atual'         => $funcao['indice_envio_atual'],
        'ultima_imagem'              => $funcao['ultima_imagem'],
        'observacao'                 => $funcao['observacao'],
        'tempo_calculado'            => $tempoCalculado,
        'notificacoes_nao_lidas'     => isset($funcao['notificacoes_nao_lidas']) ? intval($funcao['notificacoes_nao_lidas']) : 0,
        'file_uploaded_at'           => $funcao['file_uploaded_at'],
        'requires_file_upload'       => $funcao['requires_file_upload'],
        'requires_render_send'       => isset($funcao['requires_render_send']) ? intval($funcao['requires_render_send']) : 0
    ];
}

// ====================
// MESCLAR FUNCAO_ANIMACAO NO funcoesFinal
// ====================
foreach ($animFuncoes as $af) {
    $funcoesFinal[] = [
        'imagem_id'                  => $af['imagem_id'],
        'imagem_nome'                => $af['nome_animacao'],
        'status'                     => $af['status'],
        'prazo'                      => $af['prazo'],
        'nome_funcao'                => $af['nome_funcao'],
        'prioridade'                 => 3,
        'funcao_id'                  => $af['funcao_id'],
        'nome_status'                => $af['nome_status'],
        'hold_justificativa_recente' => null,
        'justificativa'              => null,
        'descricao'                  => $af['observacao'],
        'status_funcao_anterior'     => null,
        'prazo_funcao_anterior'      => null,
        'liberada'                   => true,
        'funcaoAnteriorId'           => null,
        'obra_id'                    => $af['obra_id'],
        'nomenclatura'               => $af['nomenclatura'],
        'nome_obra'                  => $af['nome_obra'],
        'idfuncao_imagem'            => $af['idfuncao_imagem'],
        'imagem_status_id'           => isset($af['imagem_status_id']) ? intval($af['imagem_status_id']) : null,
        'tempo_em_andamento'         => null,
        'imagem_prazo'               => $af['imagem_prazo'],
        'comentarios_ultima_versao'  => 0,
        'indice_envio_atual'         => null,
        'ultima_imagem'              => null,
        'observacao'                 => $af['observacao'],
        'tempo_calculado'            => null,
        'notificacoes_nao_lidas'     => 0,
        'file_uploaded_at'           => $af['file_uploaded_at'] ?? null,
        'requires_file_upload'       => $af['requires_file_upload'] ?? 0,
        'requires_render_send'       => 0,
        'is_animacao'                => true,
        'animacao_id'                => $af['animacao_id'],
        'tipo_animacao'              => $af['tipo_animacao'],
    ];
}

// ====================
// DETECÇÃO DE PARES UNIFICADOS
// ====================
// Par primária → secundária: Caderno(1)+Filtro(8)
$paresPossiveis = [
    1 => ['secundaria' => 8, 'par_tipo' => 'caderno_filtro'],
];

// Consultar pares já separados
$todasImagemIdsFinal = array_unique(array_column($funcoesFinal, 'imagem_id'));
$paresSeparados = [];
if (count($todasImagemIdsFinal) > 0) {
    try {
        $inImgFinal = implode(',', array_fill(0, count($todasImagemIdsFinal), '?'));
        $stmtSep = $conn->prepare(
            "SELECT imagem_id, par_tipo FROM funcao_par_separado WHERE imagem_id IN ($inImgFinal)"
        );
        $typesSep = str_repeat('i', count($todasImagemIdsFinal));
        $stmtSep->bind_param($typesSep, ...$todasImagemIdsFinal);
        $stmtSep->execute();
        $resSep = $stmtSep->get_result();
        while ($rowSep = $resSep->fetch_assoc()) {
            $paresSeparados[$rowSep['imagem_id'] . ':' . $rowSep['par_tipo']] = true;
        }
        $stmtSep->close();
    } catch (Exception $e) {
        // funcao_par_separado table may not exist yet; treat all as unseparated
    }
}

// Agrupar funcoesFinal por imagem_id → funcao_id → índice
$funcoesPorImagemFuncao = [];
foreach ($funcoesFinal as $idx => $fn) {
    $funcoesPorImagemFuncao[$fn['imagem_id']][$fn['funcao_id']] = $idx;
}

$suppressedIndexes = [];

foreach ($paresPossiveis as $funcaoPrimId => $parConfig) {
    $funcaoSecId = $parConfig['secundaria'];
    $parTipoUnif = $parConfig['par_tipo'];

    foreach ($funcoesPorImagemFuncao as $imgId => $funcaoMap) {
        if (!isset($funcaoMap[$funcaoPrimId]) || !isset($funcaoMap[$funcaoSecId])) continue;
        if (isset($paresSeparados[$imgId . ':' . $parTipoUnif])) continue;

        $idxPrim = $funcaoMap[$funcaoPrimId];
        $idxSec  = $funcaoMap[$funcaoSecId];

        // Se primária = Finalizado → emite sempre a secundária (independente do status da secundária)
        $primFinalizado = $funcoesFinal[$idxPrim]['status'] === 'Finalizado';

        if ($primFinalizado) {
            $funcoesFinal[$idxSec]['par_tipo']         = $parTipoUnif;
            $funcoesFinal[$idxSec]['unified_with']     = [
                'idfuncao_imagem' => $funcoesFinal[$idxPrim]['idfuncao_imagem'],
                'funcao_id'       => $funcaoPrimId,
                'nome_funcao'     => $funcoesFinal[$idxPrim]['nome_funcao'],
                'status'          => $funcoesFinal[$idxPrim]['status'],
            ];
            $funcoesFinal[$idxSec]['par_representative'] = 'secondary';
            $suppressedIndexes[] = $idxPrim;
        } else {
            $funcoesFinal[$idxPrim]['par_tipo']         = $parTipoUnif;
            $funcoesFinal[$idxPrim]['unified_with']     = [
                'idfuncao_imagem' => $funcoesFinal[$idxSec]['idfuncao_imagem'],
                'funcao_id'       => $funcaoSecId,
                'nome_funcao'     => $funcoesFinal[$idxSec]['nome_funcao'],
                'status'          => $funcoesFinal[$idxSec]['status'],
            ];
            $funcoesFinal[$idxPrim]['par_representative'] = 'primary';
            $suppressedIndexes[] = $idxSec;
        }
    }
}

if (!empty($suppressedIndexes)) {
    foreach ($suppressedIndexes as $idx) {
        unset($funcoesFinal[$idx]);
    }
    $funcoesFinal = array_values($funcoesFinal);
}

// ====================
// RESPONSE FINAL ÚNICO
// ====================
$pendenciasOperacionais = pendencias_operacionais_fetch($conn, $colaboradorId, $nivelAcesso, $pendenciasFlowReview);

// foreach ($funcoesFinal as &$funcaoFinal) {
//     $imagemIdChecklist = isset($funcaoFinal['imagem_id']) ? (int) $funcaoFinal['imagem_id'] : 0;
//     $checklistImagem = $imagemIdChecklist > 0
//         ? pendencias_operacionais_image_checklist_for_card($conn, $imagemIdChecklist)
//         : null;

//     if ($checklistImagem) {
//         $funcaoFinal['imagem_checklist_pendente'] = 1;
//         $funcaoFinal['imagem_checklist_id'] = (int) $checklistImagem['checklist_id'];
//         $funcaoFinal['imagem_checklist_items'] = $checklistImagem['items'];
//     } else {
//         $funcaoFinal['imagem_checklist_pendente'] = 0;
//         $funcaoFinal['imagem_checklist_id'] = null;
//         $funcaoFinal['imagem_checklist_items'] = [];
//     }
// }
unset($funcaoFinal);

$response = [
    "funcoes"                 => $funcoesFinal,
    "tarefas"                 => $tarefas,
    "mostrar_coluna_pendencias" => $mostrarColunaPendencias,
    "pendencias_flowreview"   => $pendenciasFlowReview,
    "pendencias_operacionais" => $pendenciasOperacionais,
    "media_tempo_em_andamento" => $mediaTemposPorFuncao
];

echo json_encode($response);

$conn->close();
