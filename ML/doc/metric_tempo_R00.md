# Documentação — Métrica de Tempo P00 (Planta Humanizada)

## Objetivo

Medir o **tempo de finalização** de imagens do tipo **Planta Humanizada** na etapa **P00**, considerando o intervalo entre a **primeira entrada em P00** e a **primeira chegada ao status final (RVW)**, **filtrando imagens finalizadas nos últimos 3 meses**. A métrica serve como base para análise de SLA, identificação de gargalos e futura previsão de entregas.

---

## Escopo e Definições

* **Etapa (status_id):** representa a fase do fluxo (ex.: P00).
* **Status (substatus_id):** estado dentro da etapa (ex.: RVW, DRV, etc.).
* **Início da contagem:** primeira ocorrência histórica da imagem em P00.
* **Fim da contagem:** primeira ocorrência histórica da imagem em RVW (na P00).
* **Filtro temporal:** imagens cujo **data_fim** ocorreu nos **últimos 3 meses**.
* **Independência do estado atual:** o status atual da imagem **não** é usado como filtro.

---

## Tabelas Utilizadas

* **imagens_cliente_obra**

  * idimagens_cliente_obra (PK)
  * tipo_imagem
  * (demais campos cadastrais)
* **historico_imagens**

  * imagem_id (FK)
  * status_id (etapa)
  * substatus_id (status)
  * data_movimento (timestamp)

---

## Premissas Importantes

1. **Histórico é a fonte de verdade** para tempos (não usar apenas o estado atual).
2. **Eventos ≠ Imagens**: contagens devem usar `DISTINCT imagem_id` quando necessário.
3. **Outliers existem** (tempos longos por pausas, revisões, bloqueios). A média sozinha não representa bem o processo.

---

## Query Base (Últimos 3 Meses)

Calcula estatísticas agregadas do tempo (em horas) para P00 → RVW.

```sql
WITH inicio_p00 AS (
    SELECT
        hi.imagem_id,
        MIN(hi.data_movimento) AS data_inicio
    FROM historico_imagens hi
    WHERE hi.status_id = 2
    GROUP BY hi.imagem_id
),

fim_p00 AS (
    SELECT
        hi.imagem_id,
        MIN(hi.data_movimento) AS data_fim
    FROM historico_imagens hi
    WHERE hi.status_id = 2
      AND hi.substatus_id = 6
      AND hi.data_movimento >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    GROUP BY hi.imagem_id
),

tempos AS (
    SELECT
        ico.idimagens_cliente_obra AS imagem_id,
        TIMESTAMPDIFF(
            HOUR,
            i.data_inicio,
            f.data_fim
        ) AS tempo_horas
    FROM imagens_cliente_obra ico
    JOIN inicio_p00 i
        ON i.imagem_id = ico.idimagens_cliente_obra
    JOIN fim_p00 f
        ON f.imagem_id = ico.idimagens_cliente_obra
    WHERE ico.tipo_imagem LIKE 'Planta%'
)
SELECT
    COUNT(*) AS total_imagens,
    MIN(tempo_horas) AS min_horas,
    MAX(tempo_horas) AS max_horas,
    ROUND(AVG(tempo_horas), 2) AS media_horas,
    ROUND(STDDEV_POP(tempo_horas), 2) AS desvio_padrao
FROM tempos
WHERE tempo_horas >= 0;
```

---

## Validação Rápida

Confere o universo de imagens finalizadas (não eventos):

```sql
SELECT COUNT(DISTINCT imagem_id)
FROM historico_imagens
WHERE status_id = 2
  AND substatus_id = 6
  AND data_movimento >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH);
```

---

## Ranking de Outliers (Top N Mais Lentas)

Identifica imagens com maior tempo P00 → RVW.

```sql
WITH inicio_p00 AS (
    SELECT imagem_id, MIN(data_movimento) AS data_inicio
    FROM historico_imagens
    WHERE status_id = 2
    GROUP BY imagem_id
),

fim_p00 AS (
    SELECT imagem_id, MIN(data_movimento) AS data_fim
    FROM historico_imagens
    WHERE status_id = 2
      AND substatus_id = 6
      AND data_movimento >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    GROUP BY imagem_id
)
SELECT
    ico.idimagens_cliente_obra AS imagem_id,
    ico.nome AS nome_imagem,
    ico.tipo_imagem,
    TIMESTAMPDIFF(HOUR, i.data_inicio, f.data_fim) AS tempo_horas,
    i.data_inicio,
    f.data_fim
FROM imagens_cliente_obra ico
JOIN inicio_p00 i ON i.imagem_id = ico.idimagens_cliente_obra
JOIN fim_p00 f ON f.imagem_id = ico.idimagens_cliente_obra
WHERE ico.tipo_imagem LIKE 'Planta%'
ORDER BY tempo_horas DESC
LIMIT 10;
```

---

## Interpretação dos Resultados

* **Média alta + desvio alto** indica processo instável.
* **Tempos = 0** sugerem saltos de status ou registros inconsistentes.
* **Outliers** geralmente refletem gargalos de fluxo (aprovação, retrabalho, espera).

---

## Próximos Passos Recomendados

1. Calcular **percentis (P50, P75, P90)** para previsão mais realista.
2. Segmentar por **cliente, tipo de entrega ou carga**.
3. Marcar imagens problemáticas (flag) para uso futuro como feature em ML.
4. Avaliar exclusão controlada de outliers para métricas operacionais.

---

## Observação Final

Esta métrica estabelece o **baseline confiável**. Antes de aplicar ML, estabilize o processo, use percentis e reduza ruído. O ganho virá mais do **modelo de negócio** do que do algoritmo.
