# Planejamento de entregas de alteração no Flow

> **Nota de atualização — 29/10/2026:** este documento preserva o diagnóstico anterior à implementação. Expressões como “ainda não implementadas” e propostas no futuro descrevem o estado daquela análise. Para o que foi efetivamente entregue, consultar [fundação e atualização do Planejamento R01+](planejamento-alteracoes-r01-implementacao.md) e [evolução por pontos e dia](planejamento-alteracoes-pontos.md).

Data do diagnóstico: 28/10/2026. Código de referência: commit `a35aa14b`.

Este documento consolida o diagnóstico e a proposta de arquitetura definidos para o planejamento de R01, R02, R03 e demais etapas de alteração, com possibilidade de extensão para EF e Render em Alta. É um plano técnico; as mudanças propostas ainda não estão implementadas.

O diagnóstico foi feito pela leitura do código versionado. Não houve execução de endpoints, migrations ou rotinas de alteração de dados. O PHP não estava disponível no ambiente de análise. Índices, triggers e migrations efetivamente aplicados no banco implantado ainda precisam ser verificados antes da implementação.

## Decisão e princípios

O planejamento R01+ será uma especialização do planejamento de produção existente. Compartilhará cabeçalho, confirmação, versões, baseline, auditoria, calendário e integração operacional, acrescentando persistência dos compromissos por imagem.

```text
R00: entrega → função → imagens
R01+: entrega → imagens → responsável e conclusão planejada
```

O cálculo por etapas do R00 não pode ser habilitado para Alterações apenas removendo a restrição de etapa: a função Alteração não participa atualmente de sua classificação, capacidade global ou projeção.

A ordem de decisão é:

1. Reaproveitar corretamente estruturas existentes.
2. Manter uma única fonte da verdade para cada informação.
3. Priorizar simplicidade operacional.
4. Preservar compatibilidade.
5. Permitir evolução futura.
6. Evitar abstrações excessivas.

A primeira entrega funcional deve incluir planejamento manual, confirmação e atribuição integrada à execução. Capacidade quantitativa e sugestões serão evoluções da mesma fundação.

## A. Diagnóstico atual

### A.1. Pré-alteração e classificação

O fluxo principal usa lotes de Review e itens de triagem. As estruturas estão em [pre_alt_helpers.php](../PreAlteracao/pre_alt_helpers.php) e [migration_lotes.sql](../PreAlteracao/migration_lotes.sql).

| Tabela                            | Responsabilidade                                                           |
| --------------------------------- | -------------------------------------------------------------------------- |
| `pre_alt_lote`                    | Agrupa triagem por obra, etapa de origem e data de finalização do cliente. |
| `pre_alt_lote_batches`            | Relaciona o lote aos `review_batch`.                                       |
| `pre_alt_itens`                   | Resultado e classificação de cada imagem daquele retorno.                  |
| `pre_alt_lote_historico`          | Auditoria de alterações da triagem.                                        |
| `pre_alt_cliente_interacoes`      | Solicitações e retornos do cliente.                                        |
| `pre_alt_cliente_interacao_itens` | Imagens envolvidas em cada interação.                                      |
| `pre_alt_liberacoes`              | Cada liberação das imagens prontas.                                        |
| `pre_alt_liberacao_itens`         | Vínculo entre o item da triagem e a entrega de destino.                    |

`pre_alt_criar_de_review_batch()` é chamada pela resolução de Review em [review_batch_action.php](../Entregas/review_batch_action.php). Existe também uma entrada específica para retorno de EF em [adicionar_lote_pre_alteracao.php](../Entregas/adicionar_lote_pre_alteracao.php).

Cada `pre_alt_itens` possui `imagem_id`, `review_batch_item_id`, `entrega_id` e `entrega_item_id` da entrega de origem, além de resultado, nível, tipo, instrução, necessidade de retorno, reanálise e responsável pela triagem. Esse responsável não representa o executante da alteração.

Os endpoints [save_pre_analise.php](../PreAlteracao/save_pre_analise.php) e [save_pre_analise_batch.php](../PreAlteracao/save_pre_analise_batch.php) trabalham com:

```text
ALTERACAO           → exige nível de 1 a 5
SEM_ALTERACAO       → remove o nível
AGUARDANDO_CLIENTE  → exige retorno e não mantém nível de alteração
```

Itens já liberados não podem ser editados nesses endpoints. `pre_alt_recalcular_status_lote()` deriva os estados `EM_TRIAGEM`, `AGUARDANDO_CLIENTE`, `PRONTO_PLANEJAMENTO`, `PLANEJADO` e `CANCELADO`.

Há duas diferenças em relação ao fluxo inicialmente descrito:

- A implementação permite liberar apenas as imagens prontas, mantendo as demais em triagem. A nova entrega pode existir antes da conclusão de todo o lote.
- `pre_alt_lote.status = PLANEJADO` significa que as imagens foram liberadas pela triagem; não comprova alocação de produção.

### A.2. Criação da próxima entrega

[concluir_triagem.php](../PreAlteracao/concluir_triagem.php) abre transação, bloqueia lote e itens, calcula o resumo, separa os grupos, cria entregas e registra as liberações. Atualiza imagens e tarefas e encerra o lote quando não existem remanescentes.

As funções centrais de [conclusao_helpers.php](../PreAlteracao/conclusao_helpers.php) são:

- `pre_alt_fetch_conclusao_summary()`;
- `pre_alt_next_status_id()`;
- `pre_alt_criar_entrega_conclusao()`;
- `pre_alt_upsert_funcao_alteracao()`.

A progressão de etapas está codificada por IDs:

```text
2 → 3 → 4 → 5 → 14 → 15 → 15
```

O código não gera revisões ilimitadas incrementando o texto `Rxx`. Qualquer expansão além das etapas cadastradas precisa considerar esse limite.

A nova entrega utiliza `entregas` e `entregas_itens`. A origem da classificação pode ser rastreada por:

```text
pre_alt_itens
  → pre_alt_liberacao_itens.pre_alt_item_id
  → pre_alt_liberacao_itens.entrega_destino_id
  → entregas_itens da entrega de destino, pela mesma imagem
```

Não se deve interpretar `pre_alt_itens.entrega_id` como a nova entrega: esse campo identifica a origem do retorno.

### A.3. Níveis N1–N5

A fonte principal é `pre_alt_itens.nivel_complexidade`. Na liberação, o nível é copiado para `alteracoes.nivel_complexidade`.

`pre_alt_nivel_label()` define:

| Nível | Significado atual                  |
| ----- | ---------------------------------- |
| N1    | Muito baixa: ajustes superficiais. |
| N2    | Baixa: ajustes de acabamento.      |
| N3    | Média: revisão de composição.      |
| N4    | Alta: revisão estrutural.          |
| N5    | Muito alta: alteração de projeto.  |

Não foram encontrados pesos ou durações N1–N5 nos motores investigados. O nível é adequado para evidenciar complexidade e apoiar a decisão manual, sem conversão automática em esforço neste momento.

A triagem identifica o retorno por item de Review e entrega de origem. `alteracoes`, por sua vez, é consultada pela tarefa e etapa, sem vínculo explícito com `entregas_itens.id`.

Existe um risco em [alteracoes_helper.php](../helpers/alteracoes_helper.php): `alteracoes_upsert_registro()` substitui recebimento e nível no conflito do upsert. Chamadas genéricas, como a de [insereFuncao.php](../insereFuncao.php), omitem o nível e podem substituí-lo por `NULL`, além de atualizar o recebimento.

O planejamento deve ler a classificação da triagem vinculada à entrega e preservar seu valor no snapshot confirmado. A cópia em `alteracoes` não é fonte suficiente para histórico do plano.

Existe ainda o fluxo anterior `pre_alt_analise`, com categorias `S/M/C/TA`, utilizado por [update_complexidade.php](../PreAlteracao/update_complexidade.php). Ele não corresponde ao modelo atual N1–N5.

### A.4. Alterações e responsáveis

[getAlteracao.php](../Alteracao/getAlteracao.php) relaciona `alteracoes.funcao_id` a `funcao_imagem.idfuncao_imagem`. Apesar do nome, aquele campo referencia a tarefa, não o cadastro `funcao`. A função operacional Alteração é `funcao_imagem.funcao_id = 6`.

A fila considera a etapa atual da imagem por `alteracoes.status_id = imagens_cliente_obra.status_id`. O responsável atual fica em `funcao_imagem.colaborador_id`.

Em [script.js](../Alteracao/script.js), `atualizarStatusLote()` envia `atribuir_logado = true` ao mover para `Em andamento`. [updateStatusLote.php](../Alteracao/updateStatusLote.php) então atualiza o responsável para o colaborador da sessão. Esse caminho pode substituir uma atribuição existente; preencher o responsável pelo planejamento, isoladamente, não protege a decisão.

Outra diferença importante: `pre_alt_upsert_funcao_alteracao()` cria tarefas novas sem colaborador, mas, ao reutilizar uma tarefa, redefine status e prazo sem limpar o responsável anterior. Uma rodada pode herdar o executante da anterior sem decisão de planejamento.

### A.5. Execução, HOLD e transferência

Existe infraestrutura operacional compartilhada em:

- [inicio_operacional_helper.php](../helpers/inicio_operacional_helper.php);
- [janela_operacional_helper.php](../helpers/janela_operacional_helper.php);
- [unidade_trabalho_helper.php](../helpers/unidade_trabalho_helper.php).

Ela oferece início com previsão, requisitos, WIP, ciclos de execução, pausas, retomadas, transferência, encerramento, reabertura e eventos auditáveis.

As tabelas são `janela_operacional_perfil`, `janela_operacional_motivo`, `janela_operacional_ciclo`, `janela_operacional_ciclo_item`, `janela_operacional_evento` e `janela_operacional_pausa`.

O ciclo guarda início, encerramento, responsável original e atual, previsão original e atual, prazo necessário e versão de planejamento em snapshot, ciclo anterior, numeração, origem, qualidade dos dados e status de saída. As migrations de referência são [janela operacional](../sql/2026-09-11_janela_operacional_v1.sql) e [ciclos oficiais](../sql/2026-09-21_ciclos_execucao_oficial.sql).

`flow_janela_transferir_responsavel()` registra a saída, encerra o ciclo por transferência, altera o responsável operacional e cria um novo ciclo ligado ao anterior. Se a tarefa estava em HOLD, o novo ciclo permanece pausado.

O arraste em massa de Alterações ainda atualiza status e responsável diretamente. Nesse endpoint não existem chamadas equivalentes para início de ciclo, transferência ou pausa. Eventuais efeitos de triggers no banco implantado não foram verificados.

O perfil `ALTERACAO` já existe, configurado sem limite fixo de duração. Isso não representa uma estimativa por nível N1–N5.

### A.6. Motor R00

[planejamento_producao_helper.php](../helpers/planejamento_producao_helper.php) concentra regras, consultas e persistência em funções PHP. Não há separação uniforme entre classes de service e repository nesse conjunto.

O motor aceita somente R00 (`status_id = 2`), carrega tarefas de `funcao_imagem` e funções ainda não materializadas de `imagem_funcao_planejada`, classifica função/tipo de imagem e calcula volumes, durações, dependências, fim e margem contra o prazo.

As etapas reconhecidas são:

```text
CADERNO_FILTRO
MODELAGEM_FACHADA
MODELAGEM_INTERNA
COMPOSICAO
FINALIZACAO_EXTERNA
FINALIZACAO_INTERNA
FINALIZACAO_PLANTA
FINALIZACAO_GLOBAL
POS_PRODUCAO
```

O motor não representa automaticamente todas as funções do Flow. Alteração não é classificada; Planta Humanizada operacional recebe tratamento específico; Animação não integra esse grafo.

| Estratégia        | Uso atual                                       |
| ----------------- | ----------------------------------------------- |
| Histórico         | Caderno/Filtro, Modelagem Interna e Composição. |
| Janela fixa       | Modelagem da Fachada: sete dias úteis.          |
| Regra operacional | Finalizações: uma tarefa/dia/pessoa.            |
| Regra operacional | Pós-produção: cinco tarefas/dia/pessoa.         |
| Marco virtual     | Finalização Global.                             |

Essas taxas pertencem ao modelo R00 e não devem ser transferidas para Alteração.

O histórico usa `log_alteracoes`, reconstrução de ciclos, mediana, remoção de outliers e classificação de confiança. Trabalha com janela de 540 dias, mínimo de oito ciclos para confiança e descarte de ciclos com HOLD ou duração extrema. É uma base metodológica futura, não uma estimativa N1–N5 pronta.

### A.7. Persistência, baseline e replanejamento

| Tabela                                      | Papel                                                                       |
| ------------------------------------------- | --------------------------------------------------------------------------- |
| `entrega_planejamento_producao`             | Cabeçalho único por entrega, estado, referências às versões e concorrência. |
| `entrega_planejamento_versao`               | Baseline e replanejamentos confirmados.                                     |
| `entrega_planejamento_funcao`               | Etapas de cada versão.                                                      |
| `entrega_planejamento_evento`               | Auditoria do plano.                                                         |
| `entrega_planejamento_capacidade_validacao` | Validação excepcional de sobrecarga.                                        |
| `entrega_planejamento_fila_operacional`     | Ordem operacional confirmada.                                               |
| `entrega_planejamento_projecao_operacional` | Snapshot de projeção.                                                       |
| `entrega_planejamento_projecao_etapa`       | Detalhamento da projeção por etapa.                                         |

Referências: [planejamento versionado](../sql/2026-08-20_planejamento_producao_versionado.sql), [alocação](../sql/2026-08-26_planejamento_alocacao_v14.sql) e [fila operacional](../sql/2026-08-26_planejamento_fila_operacional_v15.sql).

`flow_planejamento_persistir_confirmacao()` utiliza transação, bloqueios da entrega e cabeçalho, `lock_version` e fingerprint. A primeira confirmação cria a baseline; replanejamentos criam novas versões e exigem motivo.

A confirmação R00 define quantidade de pessoas por etapa. A atribuição nominal é tratada pela Central de Alocação. O snapshot atual remove os itens das etapas e não preserva sozinho a distribuição nominal por imagem exigida para R01+.

Não foram encontradas referências a `cronograma_entrega` ou `cronograma_funcao_imagem` na árvore versionada pesquisada. Sua existência no banco implantado não foi confirmada.

[get_cronograma.php](../Entregas/get_cronograma.php) implementa outra simulação: sem persistência, com dias corridos e durações simplificadas. Não será o motor oficial do novo planejamento.

### A.8. Capacidade e sugestões existentes

Os mecanismos estão em [capacidade global](../helpers/planejamento_capacidade_global_helper.php), [alocação](../helpers/planejamento_alocacao_helper.php) e [simulação de capacidade](../helpers/planejamento_capacidade_simulacao_helper.php).

As fontes são `colaborador`, `funcao_colaborador`, `funcao_colaborador_tipo_finalizacao`, `planejamento_capacidade_etapa`, `planejamento_capacidade_etapa_periodo`, versões confirmadas e tarefas reais.

`PRINCIPAL/SECUNDARIA` continua vigente. A capacidade principal vem de colaboradores ativos e elegíveis; a secundária é potencial, não disponibilidade garantida. Existem ajustes temporários por etapa, mas não foi encontrada integração completa desses motores com férias e indisponibilidades individuais.

Devem permanecer distintas a capacidade agregada por etapa/dia e a alocação nominal por pessoa. A carga aproximada por unidade da alocação atual parte de:

```text
duração da etapa × pessoas planejadas ÷ volume
```

Essa fórmula não é aplicável a Alteração sem estimativa ou reserva explícita de esforço.

`flow_alocacao_sugerir_distribuicao()` já testa candidatos para reduzir sobrecarga, considerando elegibilidade e atuação principal/secundária. Entretanto, os planos de capacidade são filtrados por R00, os pools carregam funções `1, 2, 3, 4, 5, 8` e a classificação ignora a função 6.

A rotina de aplicação compara o responsável anterior por igualdade SQL. A primeira atribuição com `colaborador_id = NULL` precisa de adaptação; a realocação existente não deve ser reutilizada sem essa revisão.

### A.9. Prazos e calendário

[prazo_entrega_helper.php](../Entregas/prazo_entrega_helper.php) já define sete dias úteis para R01–R05 e EF. O calendário exclui fins de semana e os feriados fixos e móveis definidos no helper, sem contar o dia inicial. Não há ali um cadastro completo de feriados locais.

O planejamento R00 compartilha a regra por `flow_planejamento_adicionar_dias_uteis()` e `flow_planejamento_dias_uteis_entre()`.

Na conclusão da Pré-alteração, porém, o prazo sugerido parte de `data_triagem`, também gravada como `data_recebimento` da nova entrega e do registro de alteração. A data de retorno existe em `pre_alt_lote.data_finalizacao_cliente` e nas evidências de resolução de Review/Cobrança, mas não é a base efetiva daquele cálculo.

A proposta deve corrigir a escolha da data-base para usar o recebimento do retorno do cliente, preservando sua origem. Não deve recalcular prazos históricos automaticamente.

O prazo comercial oficial permanece em `entregas.data_prevista`. Datas nos itens, na imagem e na tarefa têm usos existentes que precisam ser respeitados. Não será criado outro prazo comercial editável.

No R00 já há separação entre prazo cliente e fim interno calculado. Não foi encontrada uma regra geral de margem interna específica para alterações.

### A.10. Planejado x executado, projeções e jobs

As implementações relevantes são [execução](../helpers/planejamento_execucao_helper.php), [fila operacional](../helpers/planejamento_fila_operacional_helper.php), [fila confirmada](../helpers/planejamento_fila_confirmada_helper.php), [entrega real](../helpers/planejamento_entrega_real_helper.php) e [contexto da tarefa](../helpers/tarefa_planejamento_contexto_helper.php).

Já existem acompanhamento por tarefa/log, comparação de datas, baseline e plano vigente, projeção pela fila de responsáveis e snapshots históricos. A projeção exibida no R00 vem da infraestrutura de fila confirmada; o monitor anterior serve aos detalhes de execução.

`funcao_imagem_previsao_conclusao` e `funcao_imagem_previsao_historico` guardam previsão declarada e eventos de conclusão. O resolvedor do contexto de planejamento ainda busca R00.

| Fato                               | Fonte                                                     |
| ---------------------------------- | --------------------------------------------------------- |
| Trabalho humano iniciado/encerrado | Ciclos e eventos operacionais; logs como apoio histórico. |
| Função aprovada/finalizada         | Status e histórico da tarefa.                             |
| Imagem entregue ao cliente         | `entregas_itens.data_entregue`.                           |

[registrar_entrega.php](../Entregas/registrar_entrega.php) preserva o primeiro timestamp dos itens no fluxo padrão. `entregas.data_conclusao` isoladamente não comprova entrega completa, pois também pode ser preenchida em entrega parcial. O helper de entrega real do planejamento ainda limita sua interpretação a R00.

Os scripts [planejamento_producao_r00_runner.php](../scripts/planejamento_producao_r00_runner.php) e [planejamento_capacidade_global_runner.php](../scripts/planejamento_capacidade_global_runner.php) são ferramentas de diagnóstico. Não foi identificado job dedicado a confirmar ou alocar automaticamente os planos de produção.

## B. Fluxo atual e ponto de entrada

```text
Entrega registrada
  → imagem entra em Review
  → review_batch / review_batch_items
  → retorno/resolução registrado em review_batch_action.php
  → pre_alt_criar_de_review_batch()
  → lote e itens de Pré-alteração
  → resultado, classificação N1–N5 e retorno/reanálise, quando necessário
  → liberação das imagens prontas em concluir_triagem.php
  → criação de entrega de alteração e/ou EF e seus itens
  → registro de pre_alt_liberacoes / pre_alt_liberacao_itens
  → criação/reutilização da tarefa da função 6
  → redefinição para Não iniciado e atualização de etapa/prazo
  → registro em alteracoes
  → disponibilidade na fila de Alterações
  → execução, aprovação, render e demais passagens aplicáveis
  → registro efetivo da entrega
```

O novo planejamento entra na transação que cria a entrega de destino e os itens, antes de permitir início operacional. Como existem liberações parciais, planeja-se a entrega daquela liberação, sem esperar o encerramento do lote inteiro.

## C. Arquitetura proposta

### C.1. Núcleo compartilhado e granularidades

```text
Planejamento de produção
  ├── núcleo: cabeçalho, rascunho, confirmação, versões, baseline,
  │           eventos, calendário e concorrência
  ├── modelo R00: etapas por função
  └── modelo R01+: itens por imagem
```

O carregamento e o cálculo devem selecionar explicitamente o modelo. Um despacho simples é suficiente inicialmente; não é necessário introduzir um framework de estratégias.

### C.2. Fluxo futuro

```text
Imagens prontas na Pré-alteração
  → criação da entrega R01+
  → cabeçalho em RASCUNHO e imagens pendentes de planejamento
  → gestão salva atribuições parciais e conclusões planejadas
  → validação de completude e prazo
  → confirmação atômica
      → versão BASELINE e itens planejados imutáveis
      → atribuição operacional e liberação para início
  → execução e reatribuições auditadas
  → conclusão das alterações humanas
  → passagens posteriores aplicáveis
  → registro efetivo da entrega
```

| Módulo              | Responsabilidade                                                            |
| ------------------- | --------------------------------------------------------------------------- |
| Pré-alteração       | Classificar, registrar retorno e liberar imagens para a entrega de destino. |
| Entregas            | Escopo, prazo comercial, itens e entrega efetiva.                           |
| Planejamento        | Compromissos por imagem, confirmação, versões e comparação.                 |
| Alterações          | Exibição e execução do trabalho atribuído.                                  |
| Serviço operacional | Início, WIP, requisitos, HOLD, transferência e ciclos.                      |
| Capacidade          | Consolidar compromissos conforme a qualidade das estimativas.               |
| Flow Connect        | Notificar decisões confirmadas e mudanças relevantes.                       |

### C.3. Datas por imagem

Responsável e fim planejado serão obrigatórios para confirmar. Início planejado será opcional. O cabeçalho mantém o conceito existente de início do período.

É útil permitir início por imagem no armazenamento, mas o fluxo básico não deve obrigar duas datas para cada ajuste. A distância entre início e fim não representa esforço nem ocupação integral. Reservas quantitativas virão depois, com estimativa manual explícita ou dados calibrados.

## D. Matriz de reaproveitamento

Os elementos de frontend são, em geral, funções de renderização nos scripts de página, não componentes independentes prontos para importar. Adaptar inclui extrair somente o necessário.

| Recurso atual                  | Local                                                                                                     | Uso atual                     | Novo uso                                                     | Ação                            |
| ------------------------------ | --------------------------------------------------------------------------------------------------------- | ----------------------------- | ------------------------------------------------------------ | ------------------------------- |
| Calendário e prazo             | [prazo_entrega_helper.php](../Entregas/prazo_entrega_helper.php)                                          | Dias úteis e regra comercial  | Mesmo calendário e sete dias úteis                           | REUTILIZAR DIRETAMENTE          |
| Cabeçalho, versões e eventos   | [planejamento_producao_helper.php](../helpers/planejamento_producao_helper.php)                           | Ciclo do plano R00            | Ciclo compartilhado por entrega                              | ADAPTAR                         |
| Locks e fingerprint            | Mesmo helper                                                                                              | Confirmação concorrente       | Confirmação das alocações                                    | ADAPTAR                         |
| Grafo e taxas R00              | Mesmo helper                                                                                              | Cálculo por função            | Sem aplicação no fluxo básico R01                            | NÃO UTILIZAR                    |
| Funções não materializadas     | [imagem_funcao_planejada](../sql/2026-05-26_imagem_funcao_planejada.sql)                                  | Declaração de funções futuras | R01 já possui tarefa de alteração                            | NÃO UTILIZAR como novo cadastro |
| Triagem e destino              | [conclusao_helpers.php](../PreAlteracao/conclusao_helpers.php)                                            | Rastreio de liberação         | Origem da classificação por rodada                           | REUTILIZAR DIRETAMENTE          |
| Histórico da triagem           | [pre_alt_helpers.php](../PreAlteracao/pre_alt_helpers.php)                                                | Auditoria de classificação    | Evidência de origem                                          | REUTILIZAR DIRETAMENTE          |
| Responsável da tarefa          | `funcao_imagem.colaborador_id`                                                                            | Responsabilidade operacional  | Receber a decisão confirmada                                 | REUTILIZAR DIRETAMENTE          |
| Início e transferência         | [inicio_operacional_helper.php](../helpers/inicio_operacional_helper.php)                                 | Execução canônica             | Operação com contexto da entrega                             | ADAPTAR                         |
| Ciclos e pausas                | [janela_operacional_helper.php](../helpers/janela_operacional_helper.php)                                 | Histórico operacional         | Realizado da rodada correta                                  | ADAPTAR                         |
| Elegibilidade                  | [capacidade_colaborador_helper.php](../helpers/capacidade_colaborador_helper.php)                         | Ativo/elegível                | Seleção de candidatos                                        | REUTILIZAR DIRETAMENTE          |
| Pools e disponibilidade        | [capacidade global](../helpers/planejamento_capacidade_global_helper.php)                                 | Capacidade R00                | Incluir Alteração                                            | ADAPTAR                         |
| Distribuição e simulação       | [alocação helper](../helpers/planejamento_alocacao_helper.php)                                            | Distribuir tarefas R00        | Distribuir imagens e sugerir quando houver esforço confiável | ADAPTAR                         |
| Fila e snapshots               | [fila confirmada](../helpers/planejamento_fila_confirmada_helper.php)                                     | Ordem/projeção R00            | Identificação da rodada                                      | ADAPTAR                         |
| Entrega efetiva                | [entrega real helper](../helpers/planejamento_entrega_real_helper.php)                                    | Encerramento R00              | Encerramento R01+                                            | ADAPTAR                         |
| Cronograma simplificado        | [get_cronograma.php](../Entregas/get_cronograma.php)                                                      | Simulação em dias corridos    | Não usar como motor oficial                                  | NÃO UTILIZAR                    |
| Cabeçalho, margens e histórico | [página R00](../PlanejamentoProducao/index.php) e [script](../PlanejamentoProducao/script.js)             | Resumo e versões              | Resumo R01                                                   | ADAPTAR                         |
| Gantt e dependências R00       | Mesmos arquivos                                                                                           | Navegação por etapas          | Desnecessários inicialmente                                  | NÃO UTILIZAR inicialmente       |
| Cards e seleção múltipla       | [alocacao.js](../PlanejamentoCapacidade/alocacao.js), `personMarkup()`, `taskMarkup()`, `projectMarkup()` | Realocação de tarefas         | Distribuição de imagens e ações em massa                     | ADAPTAR                         |
| Heatmap e timeline             | [capacidade/script.js](../PlanejamentoCapacidade/script.js)                                               | Etapas/semanas                | Consolidado futuro                                           | ADAPTAR                         |
| Filtros, badges e conferência  | [Alteração/script.js](../Alteracao/script.js)                                                             | Fila e contexto da imagem     | Nível, responsável e conferência                             | ADAPTAR                         |
| Inputs de data                 | [Pré-alteração/script.js](../PreAlteracao/script.js)                                                      | Datas de triagem e prazos     | Datas individuais/em massa                                   | ADAPTAR                         |
| Loading, erro e vazio          | Scripts de planejamento/alocação                                                                          | Feedback das páginas          | Mesmo padrão visual                                          | ADAPTAR                         |
| Plano nominal por imagem       | Ainda inexistente                                                                                         | —                             | Compromisso por rodada                                       | NOVO NECESSÁRIO                 |
| Completude do plano            | Ainda inexistente nesse modelo                                                                            | —                             | Contadores de pendências                                     | NOVO NECESSÁRIO                 |

### D.1. APIs existentes relevantes

Os caminhos abaixo são endpoints atuais relativos à raiz do repositório.

| Área          | Endpoints                                                                                                                                                   |
| ------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Triagem       | `PreAlteracao/get_conclusao_lote.php`, `save_pre_analise.php`, `save_pre_analise_batch.php`, `concluir_triagem.php`, `registrar_interacao_cliente.php`.     |
| Alterações    | `Alteracao/getAlteracao.php`, `getConferenciaAlteracao.php`, `updateStatusLote.php` e `insereFuncao.php` na raiz.                                           |
| Plano R00     | `PlanejamentoProducao/preview.php`, `confirm.php`, `history.php`.                                                                                           |
| Alocação      | `PlanejamentoCapacidade/alocacao_consultar.php`, `alocacao_simular.php`, `alocacao_sugerir.php`, `alocacao_aplicar.php`, `alocacao_validar_capacidade.php`. |
| Capacidade    | `PlanejamentoCapacidade/consultar.php`, `simular.php`, `aplicar_cenario.php`.                                                                               |
| Fila/projeção | `PlanejamentoCapacidade/projecao_operacional.php`, `fila_operacional_simular.php`, `fila_operacional_sugerir.php`, `fila_operacional_confirmar.php`.        |
| Entregas      | `Entregas/save_entrega.php`, `update_entrega.php`, `add_imagem_entrega_id.php`, `remove_imagem_entrega.php`, `registrar_entrega.php`.                       |

O módulo `PlanejamentoProducao` deve ser mantido, carregando o modelo apropriado. Salvar rascunho por imagem será uma nova operação; confirmação e histórico compartilharão a infraestrutura existente.

## E. Banco de dados

### E.1. Estruturas e campos a preservar

| Estrutura                       | Campos e relações relevantes                                                                   |
| ------------------------------- | ---------------------------------------------------------------------------------------------- |
| `entregas`                      | `id`, `obra_id`, `status_id`, `data_recebimento`, `data_prevista`, `status`, `data_conclusao`. |
| `entregas_itens`                | `id`, `entrega_id`, `imagem_id`, `data_prevista`, `status`, `data_entregue`.                   |
| `pre_alt_itens`                 | Classificação e vínculo com Review/entrega anterior.                                           |
| `pre_alt_liberacao_itens`       | `pre_alt_item_id`, `entrega_destino_id`, `status_destino_id`, `prazo`.                         |
| `funcao_imagem`                 | Tarefa, responsável atual, status e prazo operacional legado.                                  |
| `alteracoes`                    | Registro operacional por tarefa/etapa.                                                         |
| `entrega_planejamento_producao` | Entrega única, estado, versões de referência, `lock_version`, fingerprint.                     |
| `entrega_planejamento_versao`   | Baseline, vigência, datas, motivo, autor e snapshot.                                           |
| `entrega_planejamento_evento`   | Auditoria do planejamento.                                                                     |
| `janela_operacional_*`          | Ciclos, pausas e eventos de execução.                                                          |
| `funcao_imagem_previsao_*`      | Previsão declarada e eventos associados.                                                       |

### E.2. Mudanças mínimas propostas

Os nomes abaixo representam o desenho proposto, não campos já implementados.

**Discriminador no cabeçalho existente:** `modelo = FUNCOES_R00 | IMAGENS_ALTERACAO`, com default `FUNCOES_R00`. Seleciona carregamento, cálculo e confirmação. A existência de um cabeçalho do novo modelo identifica entregas sujeitas ao planejamento; entregas antigas sem ele permanecem no legado.

**Rascunho no cabeçalho:** `rascunho_json`, protegido por `lock_version`, guarda decisões manuais ainda não confirmadas. Permite planejamento parcial sem alterar a execução ou editar versões confirmadas. Depois da confirmação, o rascunho é descartado; a versão passa a ser a fonte oficial.

**Uma nova tabela filha:** `entrega_planejamento_item`, relacionada à mesma versão existente:

```text
id
versao_id
entrega_item_id
funcao_imagem_id
pre_alt_item_id               nullable
responsavel_planejado_id
inicio_planejado             nullable
fim_planejado
nivel_complexidade_snapshot  nullable
```

Essa tabela é necessária porque `entrega_planejamento_funcao` não representa compromissos por imagem. Deve haver unicidade de `(versao_id, entrega_item_id)` no modelo inicial, FKs com tipos compatíveis e validação de que item, tarefa e triagem pertencem à mesma imagem e entrega. Referências históricas devem ser protegidas contra exclusão.

O nível é um snapshot histórico, não outra classificação editável. `pre_alt_item_id` pode ser nulo na adoção de dados legados sem origem inequívoca; isso não dispensa o nível em novas alterações classificadas pelo fluxo normal.

**Contexto da rodada na execução:** acrescentar `entrega_item_id` opcional em `janela_operacional_ciclo`. A tarefa é reutilizada entre revisões e seu último ciclo pode pertencer a outra rodada. Um item pode ter vários ciclos, ajustes e transferências; não deve existir unicidade simples entre item e ciclo. O vínculo existente com a versão de planejamento será aproveitado.

### E.3. Estruturas que não serão duplicadas

Não criar novo responsável operacional, tabela de execução, histórico geral, calendário, pesos N1–N5 ou status de planejamento em `entregas.status`. Não copiar todas as etapas R00 para cada imagem e não construir capacidade paralela.

`entrega_planejamento_versao.prazo_r00` possui nome específico, mas representa um snapshot. O contrato da aplicação pode expor um nome neutro, preservando a coluna física inicialmente. Não criar outra coluna concorrente apenas para corrigir nomenclatura. Snapshots confirmados não são prazos comerciais editáveis.

## F. Modelo conceitual

```text
Entrega R01
│ prazo cliente: entregas.data_prevista
│
├── Planejamento de produção
│   ├── modelo, estado e lock_version
│   ├── baseline
│   └── versão vigente
│
├── Item da entrega: Imagem 1
│   ├── origem: item da triagem liberado para esta entrega
│   ├── nível: N3
│   ├── plano confirmado
│   │   ├── responsável: João
│   │   ├── início: opcional
│   │   └── fim: 28/10
│   └── execução
│       ├── tarefa Alteração existente
│       ├── responsável atual: funcao_imagem.colaborador_id
│       ├── ciclos vinculados a este item
│       ├── início, pausas, transferências e conclusão
│       └── entrega efetiva: entregas_itens.data_entregue
│
└── Item da entrega: Imagem 2
    └── mesma estrutura
```

A identidade histórica é o item da entrega, não somente `imagem_id`, `status_id` ou `funcao_imagem_id`. Isso suporta repetições de etapa e novas EFs com retorno.

## G. Estados do planejamento

Reutilizar os estados do cabeçalho: `RASCUNHO`, `CONFIRMADO`, `DESATUALIZADO`, `REPLANEJAMENTO` e `CONCLUIDO`. Não criar outro enum para pendência, parcialidade e execução.

| Condição                                     | Apresentação derivada                    |
| -------------------------------------------- | ---------------------------------------- |
| Rascunho sem itens completos                 | Planejamento pendente.                   |
| Rascunho com parte completa                  | Planejamento parcial — 8/10.             |
| Todos completos, sem confirmação             | Pronto para confirmar.                   |
| Versão confirmada válida                     | Planejada.                               |
| Mudança de escopo/prazo ou perda de alocação | Revisão necessária.                      |
| Ciclos iniciados                             | Em execução, como indicador operacional. |
| Todos os trabalhos humanos encerrados        | Alterações concluídas.                   |
| Todos os itens efetivamente entregues        | Entrega concluída.                       |

Um item completo para confirmação tem responsável válido e fim planejado; início é opcional. Ter 10/10 responsáveis não comprova confirmação. Finalizar tarefas humanas também não conclui a entrega se render, pós-produção ou envio continuarem pendentes.

## H. Integração com Alterações

### H.1. Confirmação e atribuição

Uma única transação deve bloquear entrega, cabeçalho, itens e tarefas, validar versão/fingerprint/escopo, validar todas as decisões, criar versão e itens, preencher `funcao_imagem.colaborador_id`, registrar eventos e liberar início operacional.

O rascunho não altera o responsável da tarefa. Em novas entregas sujeitas à regra, a criação da rodada deve deixá-la sem responsável, verificando antes se existe ciclo anterior ativo que seria indevidamente sobrescrito. A reutilização não pode misturar duas rodadas operacionais da mesma tarefa.

### H.2. Fontes de responsabilidade

| Informação                        | Fonte                                 |
| --------------------------------- | ------------------------------------- |
| Responsável operacional atual     | `funcao_imagem.colaborador_id`.       |
| Responsável comprometido no plano | Item da versão confirmada.            |
| Participantes reais               | Ciclos e eventos de execução.         |
| Autor da decisão                  | Evento de planejamento/transferência. |

O responsável do plano é um fato histórico necessário à comparação, não outra fonte concorrente para o responsável operacional atual.

### H.3. Reatribuição e divergência

Antes do início, a gestão pode alterar o responsável atual com evento contendo origem, destino, item, versão e motivo. Durante execução, usar `flow_inicio_operacional_transferir()` e seus ciclos.

Se a decisão muda o compromisso futuro, confirmar replanejamento sem alterar a baseline. Uma transferência não apaga o planejado original. A análise poderá mostrar baseline Maria, plano vigente João e execução Maria → João.

### H.4. Proteção e flexibilidade

Em entregas novas sujeitas ao planejamento, pendências podem ficar visíveis à gestão, mas início exige confirmação. O atribuído pode iniciar; outro colaborador precisa de reatribuição autorizada. Arrastar para `Em andamento` não substitui silenciosamente o responsável.

Essa validação deve existir no servidor, incluindo `updateStatusLote.php`, `insereFuncao.php` e demais entradas operacionais. Entregas legadas preservam o pull.

O [diagrama de Pré-alteração](../PreAlteracao/planejamento.php) pode continuar como contexto de dependências. Sua publicação atualiza diagrama e auditoria, mas não confirma atribuições em `funcao_imagem`. Não será outra fonte operacional de responsável.

## I. Capacidade e sugestões

### I.1. Reuso imediato

Reaproveitar elegibilidade, vínculos com funções, principal/secundária, compromissos R00, filas, quantidades atribuídas, níveis, prazos e infraestrutura de simulação. A consulta de candidatos precisa incluir Alteração sem assumir que todo finalizador é elegível.

O carregamento compartilhado deverá considerar R01+ por imagem. Consultas que procuram a R00 associada à imagem precisam passar a resolver a entrega real do trabalho.

Evitar disponibilidade duplicada da mesma pessoa em pools, dupla contagem de tarefa e uso da janela até o prazo como ocupação integral. Sem estimativa, não declarar capacidade suficiente.

```text
João
4 imagens: N1 × 1, N3 × 2, N5 × 1
Compromissos R00 no período: disponíveis para consulta
Esforço das alterações: ainda não estimado
```

### I.2. Evolução de N1–N5 para esforço

Preservar classificação por rodada, registrar execução e transferências, separar espera/HOLD/aprovação, avaliar amostra e só então calibrar estimativas e sugestões quantitativas.

Ainda faltam evidências confiáveis de esforço produtivo, participação de cada executante, retrabalho, esperas, disponibilidade e continuidade no projeto. Os ciclos medem tempo transcorrido com informações de pausa; não equivalem automaticamente a horas trabalhadas.

A metodologia de amostragem e confiança do R00 pode ser adaptada, sem reutilizar suas taxas ou inventar pesos por nível.

### I.3. Sugestões assistidas

Adaptar o mecanismo existente de simular, comparar e aplicar para oferecer concentração em uma pessoa ou divisão entre várias. Não impor equilíbrio, divisão mínima ou aplicação automática.

Continuidade de projeto pode ser um critério futuro; não foi encontrada como preferência implementada no algoritmo atual de distribuição. A decisão final permanece da gestão.

## J. Interface conceitual

### J.1. Acesso e formato

Uma página dedicada no módulo `PlanejamentoProducao` é mais adequada que um modal para seleção em massa, distribuição e histórico. Entradas: card/modal da entrega, retorno da liberação na Pré-alteração e detalhe da obra com escolha da entrega.

A navegação deve transportar `entrega_id`; escolher automaticamente a última R00 não atende ao novo uso.

```text
Projeto X · R01
Retorno recebido: ...
Prazo cliente: ...
Planejamento: 8/10 completos
Sem responsável: 2
Sem conclusão planejada: 1

[Salvar rascunho] [Confirmar planejamento]
```

A tabela mostra seleção, imagem, nível, responsável, início opcional, conclusão e situação. Ações em massa: selecionar todas ou filtradas, atribuir colaborador, definir datas e limpar decisões do rascunho. Deve ser possível atribuir todas a uma pessoa ou dividir livremente.

Filtros: sem responsável, sem data, nível, colaborador e texto. O resumo lateral agrupa quantidade, níveis, datas e compromissos existentes por colaborador.

### J.2. Indicadores e componentes

Separar prazo cliente, fim planejado das alterações humanas, projeção operacional calculável e entrega efetiva. `MAX(fim_planejado)` representa o fim das alterações, não comprova sozinho render, pós-produção e envio.

```text
10/10 planejadas
0 sem responsável
Fim planejado das alterações: 28/10
Prazo cliente: 30/10
Datas planejadas dentro do prazo
Capacidade quantitativa: ainda não estimada
```

Com planejamento incompleto, mostrar o risco antes de qualquer indicador positivo de prazo.

Combinar cabeçalho/margens/histórico R00, cards/seleção da Central de Alocação, badges/conferência de Alterações, datas e estados de loading/erro/vazio existentes. Não incluir Gantt na primeira versão.

## K. Impactos, riscos e EF

| Área               | Impacto ou risco                                      | Tratamento                                                                         |
| ------------------ | ----------------------------------------------------- | ---------------------------------------------------------------------------------- |
| Pré-alteração      | Liberação parcial não coincide com conclusão do lote. | Planejar cada entrega de destino.                                                  |
| Classificação      | Upsert pode apagar nível e alterar recebimento.       | Preservar classificação e separar atualização operacional.                         |
| Alterações         | Arraste substitui responsável.                        | Validar alocação confirmada no servidor.                                           |
| Execução           | Atualização direta diverge dos ciclos.                | Unificar transições críticas no serviço operacional.                               |
| Rodadas            | Tarefa e responsável são reutilizados.                | Contexto por item e snapshots históricos.                                          |
| Prazos             | Triagem é usada como recebimento.                     | Usar retorno do cliente com origem rastreável.                                     |
| R00                | Generalização muda cálculos existentes.               | Despacho explícito e testes de compatibilidade.                                    |
| Banco              | Exclusão de item/entrega apaga histórico.             | Proteger referências confirmadas e privilegiar arquivamento.                       |
| Capacidade         | Falta de esforço gera falsa viabilidade.              | Mostrar compromissos sem inventar carga.                                           |
| Histórico          | Último ciclo pode ser de outra revisão.               | Vincular ao item da entrega.                                                       |
| Concorrência       | Gestores e colaboradores agem simultaneamente.        | Locks, versão, fingerprint e comparação do responsável anterior, inclusive `NULL`. |
| Escopo             | Novas imagens tornam plano incompleto.                | Marcar revisão e exigir planejamento adicional.                                    |
| Mudança de prazo   | Compromisso pode ficar inválido.                      | Desatualização e replanejamento.                                                   |
| HOLD               | Pausa confundida com novo prazo cliente.              | Não alterar prazo comercial automaticamente.                                       |
| Notificações       | Rascunhos geram avisos prematuros/duplicados.         | Notificar decisões efetivadas com deduplicação.                                    |
| Legado em execução | Bloqueio retroativo interrompe trabalho.              | Manter legado até adoção explícita.                                                |

### K.1. Flow Connect

Já existe `pre_alteracao.planejamento_liberado`, mas a publicação encontrada está no fluxo antigo de complexidade. Não reutilizar esse significado como confirmação do novo plano.

Referências: [eventos imediatos](../FlowConnect/config/events/immediate_legacy.php) e [PreAlteracaoPendingProvider.php](../FlowConnect/application/PendingSummary/Providers/PreAlteracaoPendingProvider.php).

A integração deve representar confirmação de planejamento de produção e reatribuições relevantes, com deduplicação. Publicar apenas após commit ou por outbox transacional, conforme o mecanismo existente. O encerramento da pendência de triagem não significa encerramento da pendência de planejamento.

### K.2. EF e Render em Alta

Já existem [render_ef.php](../Render/render_ef.php), [deadline_render_ef.py](../Render/deadline_render_ef.py), [deadline_worker.py](../Render/deadline_worker.py) e a [migration de Render EF](../sql/2026-10-20_render_ef.sql). Utilizam `render_ef_solicitacoes`, `render_alta`, `render_tentativas`, comandos e acompanhamento de jobs.

A Pré-alteração distingue aprovação que gera EF, alteração retornando para EF e aprovação de imagem já em EF mantida na entrega de origem.

```text
EF com alteração
  → trabalho humano planejado → render → pós/conferência → entrega

EF somente alta
  → render → pós/conferência → entrega
```

Compartilhar entrega, versão, prazo e auditoria. O recurso consumido muda: pessoa no trabalho humano; fila/máquina no render. Não construir agora um modelo genérico de recursos nem exigir N1–N5 para render sem alteração.

A futura integração deve identificar item/rodada. A unicidade atual de `render_ef_solicitacoes` por imagem não é identidade histórica suficiente para múltiplas EFs.

## L. Compatibilidade e implantação

Entregas antigas sem cabeçalho `IMAGENS_ALTERACAO` mantêm o fluxo legado. Novas entregas criadas com a funcionalidade habilitada recebem cabeçalho obrigatório em `RASCUNHO` e dependem de confirmação para iniciar.

Para novas entregas sujeitas à regra, entrega, itens e cabeçalho devem nascer na mesma transação. Falhar ao criar o planejamento não pode liberar silenciosamente a execução.

A migration será aditiva: discriminador, rascunho, itens planejados, vínculo opcional dos ciclos e índices/FKs. Defaults:

- Planos existentes continuam no modelo R00.
- Ciclos antigos ficam sem `entrega_item_id`.
- Níveis ausentes permanecem desconhecidos.
- Não criar baseline automática para trabalho passado.

Não fazer backfill em massa inventando responsáveis ou datas. Uma reconciliação pode sugerir vínculos com evidência inequívoca; casos ambíguos continuam sem associação e com qualidade histórica limitada.

Na adoção voluntária de uma R01 antiga, importar o responsável atual apenas como proposta, indicar que o plano foi registrado após o início, preservar ciclos/datas e confirmar compromissos futuros. Não tratar essa adoção como prova de planejamento anterior.

Corrigir a data-base de novos registros não recalcula prazos antigos automaticamente.

## M. Plano de implementação

### Fase 0 — Contratos e pontos de integração

- **Objetivo:** preparar as regras que o planejamento utilizará.
- **Módulos:** Pré-alteração, `alteracoes_helper.php`, serviços operacionais e entradas de Alterações.
- **Trabalho:** confirmar schema/índices/triggers implantados; definir origem do recebimento; preservar nível e recebimento nos upserts; resolver item ativo da rodada; cobrir atualizações diretas.
- **Dependências:** nenhuma fase anterior.
- **Risco:** médio, por envolver fluxos existentes.
- **Aceite:** mudar status não apaga classificação; novas entregas usam a data correta; transições críticas são consistentes.

### Fase 1 — Persistência compartilhada por imagem

- **Objetivo:** suportar rascunhos e versões por imagem sem mudar o cálculo R00.
- **Módulos:** migrations, `planejamento_producao_helper.php`, confirmação/histórico e vínculo de ciclos.
- **Dependências:** contratos da fase 0.
- **Risco:** médio.
- **Aceite:** salvar plano parcial, confirmar baseline completa e replanejar preservando versões anteriores; rejeitar conflito de confirmação; manter leitura R00 compatível.

### Fase 2 — Planejamento manual integrado à execução

- **Objetivo:** entregar o fluxo operacional mínimo completo.
- **Módulos:** `PreAlteracao/concluir_triagem.php`, criação manual em Entregas, página de planejamento, listagem e entradas de Alterações.
- **Dependências:** fase 1 e correções operacionais da fase 0.
- **Risco:** alto na integração, limitado por habilitação gradual.
- **Aceite:** R01 com dez imagens nasce com dez pendentes; permite atribuição individual ou em massa; só confirma com todas completas; responsáveis aparecem na execução e início respeita a alocação. Entregas antigas seguem funcionando.

### Fase 3 — Reatribuição e planejado x executado

- **Objetivo:** comparar compromisso e execução da rodada correta.
- **Módulos:** ciclos, contexto de planejamento da tarefa, histórico, leitura de entrega real e interface.
- **Dependências:** fase 2.
- **Risco:** médio, principalmente na identificação histórica.
- **Aceite:** mostrar responsável/prazo da baseline e plano vigente, participantes reais, início, conclusão humana e entrega efetiva; R02 não modifica a leitura histórica de R01; desvios identificam dias úteis pelo calendário compartilhado.

### Fase 4 — Agenda e capacidade compartilhadas

- **Objetivo:** incluir compromissos R01+ na visão comum dos colaboradores.
- **Módulos:** capacidade global, alocação, fila e Central de Alocação.
- **Dependências:** planejamento manual e contexto de execução estáveis.
- **Risco:** médio/alto, por afetar projeções.
- **Aceite:** R00 e R01 na mesma agenda sem dupla contagem; esforço desconhecido aparece como não estimado; carga quantitativa usa estimativas explícitas e rastreáveis.

### Fase 5 — Sugestões assistidas

- **Objetivo:** oferecer alternativas de distribuição à gestão.
- **Módulos:** simulação/sugestão existentes e interface de comparação.
- **Dependências:** capacidade compartilhada e estimativas com qualidade conhecida.
- **Risco:** médio.
- **Aceite:** comparar concentração e divisão, explicar critérios/impactos e aplicar somente após decisão da gestão. Pesos N1–N5 dependem de calibração fundamentada.

### Fase 6 — EF e Render em Alta

- **Objetivo:** estender planejamento para trabalho humano e processamento de render.
- **Módulos:** planejamento, Render/Deadline, pós-produção e conferência.
- **Dependências:** identidade por item/rodada e núcleo compartilhado estáveis.
- **Risco:** alto, por envolver processamento externo e tentativas.
- **Aceite:** EF com alteração usa planejamento humano aplicável; EF somente alta usa processamento; nenhum caso exige classificação N1–N5 artificial.

### Verificação prevista

Aproveitar [tests/planejamento_producao](../tests/planejamento_producao), [tests/janela_operacional](../tests/janela_operacional) e [Render/tests](../Render/tests). Cobrir rodadas sucessivas, atribuição inicial com `NULL`, concorrência, liberação parcial, mudança de escopo/prazo, transferência em HOLD, legado em andamento e distinção entre conclusão humana e entrega efetiva.

## Fora de escopo nesta proposta

- Algoritmo sofisticado de IA ou previsão automática por histórico.
- Pesos definitivos N1–N5 sem dados.
- Novo planejamento completo de render farm.
- Reformulação total de Alterações.
- Capacidade paralela.
- Redesign amplo ou substituição do planejamento R00.

## Referência complementar

[Plano de produção: fontes do realizado](plano-producao-entrega-real.md) documenta a distinção já adotada entre conclusão de tarefa, projeção e entrega efetiva. A especialização R01+ deve preservar essa semântica.
