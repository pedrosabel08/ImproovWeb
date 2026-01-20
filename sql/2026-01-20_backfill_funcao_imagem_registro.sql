-- Backfill para `funcao_imagem_registro_mensal` usando `log_alteracoes`
-- 1) Dry-run: quantas linhas seriam inseridas (linhas que ainda não existam)
-- 2) INSERT IGNORE: inserção efetiva (respeita UNIQUE(funcao_imagem_id, ano, mes))

-- Ajuste os nomes do banco/usuário se for executar fora do ambiente padrão.

-- ===== DRY RUN: veja quantas seriam inseridas =====
SELECT
    COUNT(*) AS possiveis_insercoes
FROM (
    -- 1) status relevantes a partir de log_alteracoes
    SELECT
        la.funcao_imagem_id,
        COALESCE(la.colaborador_id, f.colaborador_id) AS colaborador_id,
        f.imagem_id,
        f.funcao_id,
        la.status_novo AS status_registrado,
        CASE
            WHEN f.funcao_id = 4 AND la.data < '2025-11-06' THEN
                CASE WHEN i.status_id = 1 THEN 'Finalização Parcial' ELSE 'Finalização Completa' END
            ELSE ''
        END AS observacao,
        la.data AS data_evento,
        YEAR(la.data) AS ano,
        MONTH(la.data) AS mes
    FROM log_alteracoes la
    JOIN funcao_imagem f ON f.idfuncao_imagem = la.funcao_imagem_id
    LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
    WHERE la.status_novo IN ('Em aprovação','Finalizado','Aprovado com ajustes','Aprovado')
      AND (f.funcao_id <> 4 OR la.data < '2025-11-06')

    UNION ALL

    -- 2) finalização parcial/completa a partir de pagamento_itens (a partir de 2025-11-06)
    SELECT
        fi.idfuncao_imagem AS funcao_imagem_id,
        fi.colaborador_id AS colaborador_id,
        fi.imagem_id,
        fi.funcao_id,
        'Finalizado' AS status_registrado,
        CASE
            WHEN pi.observacao IN ('Finalização Parcial', 'Parcial') THEN 'Finalização Parcial'
            WHEN pi.observacao IN ('Pago Completa', 'Finalização Completa', 'Completa') THEN 'Finalização Completa'
            ELSE ''
        END AS observacao,
        LAST_DAY(DATE_SUB(pi.criado_em, INTERVAL 1 MONTH)) AS data_evento,
        YEAR(LAST_DAY(DATE_SUB(pi.criado_em, INTERVAL 1 MONTH))) AS ano,
        MONTH(LAST_DAY(DATE_SUB(pi.criado_em, INTERVAL 1 MONTH))) AS mes
    FROM pagamento_itens pi
    JOIN funcao_imagem fi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi.idfuncao_imagem
    WHERE fi.funcao_id = 4
      AND pi.observacao IN ('Finalização Parcial', 'Parcial', 'Pago Completa', 'Finalização Completa', 'Completa')
            AND pi.criado_em >= '2025-11-06'
) AS cand
LEFT JOIN funcao_imagem_registro_mensal rim
    ON rim.funcao_imagem_id = cand.funcao_imagem_id
    AND rim.ano = cand.ano
    AND rim.mes = cand.mes
    AND rim.status_registrado = cand.status_registrado COLLATE utf8mb4_unicode_ci
    AND rim.observacao = cand.observacao COLLATE utf8mb4_unicode_ci
WHERE rim.id IS NULL;

-- ===== INSERT efetivo: use isso quando estiver confortável com o DRY RUN =====
-- IMPORTANTE: este INSERT IGNORE não duplicará entradas já existentes

INSERT IGNORE INTO funcao_imagem_registro_mensal (
    funcao_imagem_id,
    colaborador_id,
    imagem_id,
    funcao_id,
    status_registrado,
    observacao,
    data_evento,
    ano,
    mes,
    criado_em
)
SELECT
    la.funcao_imagem_id,
    COALESCE(la.colaborador_id, f.colaborador_id) AS colaborador_id,
    f.imagem_id,
    f.funcao_id,
    la.status_novo AS status_registrado,
    CASE
        WHEN f.funcao_id = 4 AND la.data < '2025-11-06' THEN
            CASE WHEN i.status_id = 1 THEN 'Finalização Parcial' ELSE 'Finalização Completa' END
        ELSE ''
    END AS observacao,
    la.data AS data_evento,
    YEAR(la.data) AS ano,
    MONTH(la.data) AS mes,
    NOW() AS criado_em
FROM log_alteracoes la
JOIN funcao_imagem f ON f.idfuncao_imagem = la.funcao_imagem_id
LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
WHERE la.status_novo IN ('Em aprovação','Finalizado','Aprovado com ajustes','Aprovado')
  AND (f.funcao_id <> 4 OR la.data < '2025-11-06');

INSERT IGNORE INTO funcao_imagem_registro_mensal (
    funcao_imagem_id,
    colaborador_id,
    imagem_id,
    funcao_id,
    status_registrado,
    observacao,
    data_evento,
    ano,
    mes,
    criado_em
)
SELECT
    fi.idfuncao_imagem AS funcao_imagem_id,
    fi.colaborador_id AS colaborador_id,
    fi.imagem_id,
    fi.funcao_id,
    'Finalizado' AS status_registrado,
    CASE
        WHEN pi.observacao IN ('Finalização Parcial', 'Parcial') THEN 'Finalização Parcial'
        WHEN pi.observacao IN ('Pago Completa', 'Finalização Completa', 'Completa') THEN 'Finalização Completa'
        ELSE ''
    END AS observacao,
    LAST_DAY(DATE_SUB(pi.criado_em, INTERVAL 1 MONTH)) AS data_evento,
    YEAR(LAST_DAY(DATE_SUB(pi.criado_em, INTERVAL 1 MONTH))) AS ano,
    MONTH(LAST_DAY(DATE_SUB(pi.criado_em, INTERVAL 1 MONTH))) AS mes,
    NOW() AS criado_em
FROM pagamento_itens pi
JOIN funcao_imagem fi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi.idfuncao_imagem
WHERE fi.funcao_id = 4
    AND pi.observacao IN ('Finalização Parcial', 'Parcial', 'Pago Completa', 'Finalização Completa', 'Completa')
    AND pi.criado_em >= '2025-11-06';

-- Fim do script
