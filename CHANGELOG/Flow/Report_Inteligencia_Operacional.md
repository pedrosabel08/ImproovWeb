 # FlowTrack — Evolução para Inteligência Operacional

 Este documento resume a proposta de evolução da tela `FlowTrack/Report` de um painel de acompanhamento para um sistema de inteligência operacional. Contém: evolução conceitual, dados necessários, lógica de previsão, sugestões de interface, ideias inovadoras e um roteiro de implementação.

 ---

 ## Sumário
 - Evolução conceitual
 - Dados necessários
 - Lógica de previsão
 - Sugestões de interface e visualizações
 - Funcionalidades avançadas propostas
 - Ideias não convencionais (vantagem competitiva)
 - Roteiro de implementação

 ## 1. Evolução conceitual da página

 **Hoje:** uma matriz de status (obra × tipo de imagem × função) que responde "onde estamos agora?".

 **Fase 1 — Painel Tático (curto prazo):** adicionar contexto temporal, tempos por etapa, indicadores de aderência ao prazo e alertas básicos.

 **Fase 2 — Painel Preditivo (médio prazo):** usar histórico para projetar datas reais de conclusão, incluir previsão de esforço e indicadores de risco.

 **Fase 3 — Sistema Operacional Inteligente (longo prazo):** cruzar capacidade da equipe, perfil de complexidade das imagens e dependências para simular cenários, emitir recomendações operacionais e permitir replanejamento automático.

 ---

 ## 2. Dados necessários (coleção automática)

 1. Logs de transição de status
    - Registrar toda mudança de status em `funcao_imagem` com timestamp, colaborador, status anterior e novo.
    - Tabela sugerida: `funcao_imagem_log(funcao_id, imagem_id, status_de, status_para, colaborador_id, criado_em)`.

 2. Tempo por etapa por tipo de imagem
    - Tempo médio e desvio padrão por etapa (ex.: modelagem, composição, pós) e por tipo de imagem.

 3. Histórico de revisões e retrabalho
    - Número de ciclos de revisão por imagem e se houve retrocesso (Finalizado → Em andamento).

 4. Capacidade da equipe
    - Horas disponíveis por colaborador por período, ausências programadas.

 5. Filas e dependências
    - Contagem de itens em `Não iniciado` e em cada etapa; ordem/sequência de funções por tipo de imagem.

 6. Metadados da imagem
    - Tipo, complexidade estimada (inicial), cliente, entrega associada e prioridade.

 7. Entregas e prazos
    - Data de entrega alvo, prioridade, impacto de atraso.

 ---

 ## 3. Lógica de previsão

 ### 3.1 Previsão simples por imagem
 - Para cada imagem, identificar etapas restantes e somar tempos médios históricos:
   - T_restante = Σ tempo_médio(etapa_i, tipo_imagem) × fator_revisão
 - Data prevista = hoje + (T_restante / capacidade_efetiva)

 ### 3.2 Fator de risco de atraso (obra)
 - risco_atraso = T_restante_total_obra / (dias_úteis_ate_entrega × capacidade_diária_equipe)
 - Thresholds: <0.7 (verde), 0.7–1.0 (amarelo), >1.0 (vermelho).

 ### 3.3 Detecção de gargalo
 - Gargalo se: fila_entrada > média_saida_semana OR tempo_médio_etapa >= 2× média_outros.

 ### 3.4 Sobrecarga de colaborador
 - carga_colaborador = Σ tempo_médio_restante(imagens_alocadas)
 - score_sobrecarga = carga_colaborador / horas_disponíveis_semana
 - score > 1.0 indica sobrecarga.

 ### 3.5 Modelo incremental (sem ML inicial)
 - Tempo estimado(etapa, tipo) = 0.5×média_últimas_10 + 0.3×média_últimas_30 + 0.2×média_histórica
 - Ajustes por colaborador (curva de aprendizado) e por complexidade da imagem.

 ---

 ## 4. Indicadores e métricas sugeridas

 - % concluído por obra (funções finalizadas / total)
 - Previsão de data de entrega (e intervalo de confiança)
 - Risco de atraso (score) por obra
 - Load por colaborador (horas previstas vs disponíveis)
 - Tempo médio por etapa (última semana / último mês)
 - Taxa de retrabalho (percentual de imagens com retorno)
 - Número médio de revisões por tipo de imagem
 - Fila por etapa (itens `Não iniciado` / `Em andamento`)

 ---

 ## 5. Funcionalidades avançadas

 - Previsão automática de data de entrega (por imagem e por obra) com margem de erro baseada no desvio padrão histórico.
 - Alerta de risco de atraso em tempo real (push, e-mail, Slack) com explicação do motivo (ex.: fila grande, equipe reduzida).
 - Estimativa de esforço restante (horas) por colaborador e por obra.
 - Simulador de capacidade: "E se eu adicionar 1 colaborador de modelagem?" — recalcula previsões.
 - Recomendações automáticas de realocação de tarefas para minimizar risco (por heurística: mover itens críticos para colaboradores com menor carga e histórico de melhor performance para aquele tipo).
 - Exportação e API: permitir consumo por dashboards externos ou integrações com BI.

 ---

 ## 6. Visualizações úteis

 - Matriz atual aprimorada (obra × tipo × função) com colunas de `% concluído` e `previsão`
 - Heatmap de carga (colaborador × próximas 6 semanas)
 - Cumulative Flow Diagram (CFD) por obra / por etapa
 - Linha do tempo das entregas com intervalo de confiança
 - Painel de alertas ordenado por criticidade
 - Gráfico de Pareto de retrabalhos (identificar 20% das imagens que geram 80% do retrabalho)
 - Mini-simulador interativo (side panel) para testar cenários

 ---

 ## 7. Ideias não convencionais (vantagem competitiva)

 - Índice de Complexidade por Imagem: score automático que ajusta estimativas antes do início.
 - Curva de Aprendizado por Colaborador × Tipo: uso do histórico para prever que certos colaboradores serão mais rápidos em tarefas específicas.
 - Detecção automática de "stall" (imagem sem transições por X dias) que gera alerta de bloqueio silencioso.
 - Obra Health Index (0–100) que agrega progresso, retrabalho e carga da equipe para priorização executiva.
 - Fingerprint de tipo de imagem: perfis reutilizáveis para orçamentos e planejamento de novas obras.

 ---

 ## 8. Roteiro de implementação sugerido (sprints)

 - Sprint 1: Logar transições de status (criar `funcao_imagem_log`) — base para todas as métricas.
 - Sprint 2: Calcular e exibir `% concluído` e previsão simples por obra na tabela atual.
 - Sprint 3: Alertas básicos de risco de atraso e visão de entregas pendentes com cor de risco.
 - Sprint 4: Heatmap de carga da equipe e cálculo de carga por colaborador.
 - Sprint 5: CFD e detecção de gargalos automatizada.
 - Sprint 6+: Simulador de cenários, refinamento de modelos (ML opcional), integrações BI.

 ---

 ## Observações finais

 - Recomenda-se começar por métricas simples e confiáveis (tempos médios e logs) antes de investir em modelos ML. A qualidade dos dados é crítica.
 - Estruturar uma pequena API interna e endpoints de cache (ex.: 30–60s) para reduzir carga no banco de dados ao exibir o painel.

 ---

 *Gerado em: 2026-03-16 — proposta baseada na análise do módulo `FlowTrack/Report` e práticas de operações produtivas.*
