# Flow Connect — relatório de eventos temporais

Data: 2026-08-03. Escopo executado sem ativar policies, chamar Slack/webhook, aplicar migration ou alterar dados de negócio.

## Resumo executivo

O núcleo temporal foi endurecido para armazenar datas em UTC, pausar o relógio, confirmar a fonte antes de emitir marcos e executar o scheduler em modo `--once` ou `--daemon`. Foram ligados produtores reais para checklists Projeto/Imagem, Flow Block e a rota contínua do Render. Os demais módulos já possuem contratos, templates e destinatários centralizados, mas ainda não têm todos os produtores de ciclo ligados; não são candidatos a `active`.

O teste integrado em banco foi deliberadamente bloqueado: não existe ambiente/fixture isolado fornecido e a base configurada é compartilhada. Não foram inseridos eventos de teste nela.

## Matriz de prontidão

| Módulo | Policy | SLA | Criação / resolução | Provider | SHADOW | Situação |
|---|---|---:|---|---|---|---|
| Render | `render.aprovacao.v1` | 1h | `deadline_monitor.py`; fechamento pelo provider | `funcao_imagem.status` | unitário | PRECISA MAIS SHADOW |
| Projeto | `projeto.checklist.v1` | 24h | helper de checklist | `checklist_operacional` | unitário | PRECISA MAIS SHADOW |
| Imagem | `imagem.checklist.v1` | 4h | helper de checklist | `checklist_operacional` | unitário | PRECISA MAIS SHADOW |
| Flow Block | `flow_block.bloqueio.v1` | 2h | API criar/resolver/cancelar/reabrir/pausar | `flow_issue.status` | unitário | PRECISA MAIS SHADOW |
| Pré-Alteração | `pre_alteracao.triagem.v1` | prazo do lote | contrato existe; produtor pendente | `pre_alt_lote.status` | não executado | BLOQUEADO |
| Cobrança cliente | `cobranca_cliente.review.v1` | 3 dias (regra de negócio) | contrato existe; produtor pendente | `cobranca_review.status` | não executado | BLOQUEADO |
| Fotográfico | `fotografico.plano.v1` | `proxima_cobranca_em` | contrato existe; produtor pendente | `fotografico_pendencia.status` | não executado | BLOQUEADO |
| Flow Review | `flow_review.aprovacao.v1` | `sla_funcao` | scheduler legado permanece fonte | `funcao_imagem.status` | não migrado | PRECISA MAIS SHADOW |
| Arquivo | `upload_pendente.resumo.v1` | 09:00, 13:00, 17:00 | worker de resumo existente | não aplicável | unitário | PRECISA MAIS SHADOW |
| Links | `links.pendencia.v1` | sem SLA | helper existente | `pendencias_links_obra` | não executado | NÃO APLICÁVEL |

## Implementado

- `OperationalCycleRepository`: ciclo idempotente, conversão de datas de negócio para UTC e extensão do prazo ao retomar uma pausa.
- `OperationalStateProvider`: confirmação para checklists, Flow Block, tarefa de Render/Review, Links, Pré-Alteração, Fotográfico e Cobrança de Cliente. Estado desconhecido não gera cobrança.
- `operational_scheduler_worker.php`: `--once`, `--daemon`, sinais, espera sem lock, `SKIP LOCKED`, backoff de banco e marcos únicos por `(cycle_id,milestone)`.
- `WARNING_90` somente DM; `EXPIRED`, `OVERDUE_100` e `OVERDUE_200` também recebem delivery lógica de webhook por chave de ambiente. Em SHADOW não há tentativa externa.
- Render: o monitor contínuo publica `render.pendencia.criada` por `render_tentativas.id` quando disponível; `script.py` não publica esse contrato para evitar duplicidade.
- Flow Block: cria, pausa, resolve, cancela e reabre usando `flow_issue_ciclo.id` como `cycle_id` estável.
- Projeto/Imagem: criação, resolução e cancelamento do checklist publicam payload temporal completo.
- Arquivo: o worker existente usa a coluna real `requires_file_upload` (singular), agrupa por responsável, limita a cinco itens e protege uma janela por colaborador.

## Divergências e riscos

- A especificação menciona `requires_files_upload`; a coluna real auditada é `requires_file_upload`.
- O Render possui `deadline_monitor.py` e `script.py` com comportamento legado sobreposto. Só o primeiro foi conectado; falta validação em ambiente integrado para confirmar que é o processo ativo na VPS.
- O SLA de Render é 1h conforme matriz; foi parametrizado por `FLOW_CONNECT_RENDER_APPROVAL_SLA_SECONDS`, mas o produtor Python ainda precisa consumir a variável para não divergir caso ela seja alterada.
- Flow Review continua no scheduler/cron legado. Não foi desligado nem recebeu uma segunda fonte temporal.
- Não há retomada explícita de Issue pausada na API atual do Flow Block; o provider respeita `PAUSADA`, mas a ação de produto para retomá-la precisa ser definida antes de marcar esse caso como aprovado.
- Pré-Alteração, Cobrança e Fotográfico ainda precisam de hooks transacionais em suas fontes de criação/resolução. O provider sozinho não cria ciclos.

## Testes executados

| Teste | Resultado |
|---|---|
| `php FlowConnect/tests/run.php` | PASS — 123 asserções |
| `php -l` dos novos/alterados PHP | PASS |
| `python -m py_compile Render/deadline_monitor.py` | PASS |
| delivery, attempts, preview e webhook em SHADOW por módulo | BLOCKED — sem fixture/banco isolado autorizado |
| migração | não executada por instrução |

Nenhum token, webhook ou valor secreto foi impresso, persistido nos novos payloads ou usado em teste.

## Ativação gradual recomendada

1. Render em `shadow`, validar uma tentativa, os quatro marcos e aprovação.
2. Projeto e Imagem em `shadow` com fixtures de checklist.
3. Flow Block, incluindo uma decisão explícita para retomada.
4. Arquivo, validar as três janelas e filtros de tarefas regularizadas.
5. Pré-Alteração, Fotográfico e Cobrança após ligar seus produtores transacionais.
6. Flow Review por comparação com o cron legado; somente então decidir a fonte temporal oficial.

Nenhum módulo é recomendado para `active` nesta execução.
