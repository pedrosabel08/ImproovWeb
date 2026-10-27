# Plano de produção: fontes do realizado

Mapeamento realizado antes de alterações de persistência, em 27/10/2026.

| Informação | Fonte existente | Semântica |
| --- | --- | --- |
| Prazo R00 | `entregas.data_prevista`, com `status_id = 2` | Compromisso da entrega. A versão do plano preserva `prazo_r00` e `snapshot_json.data_entrega`. |
| Status da entrega | `entregas.status` e `entregas_itens.status` | `registrar_entrega.php` registra entregas parciais e completas; tarefas a 100% não entregam a R00. |
| Instante real | `entregas_itens.data_entregue` | Registro explícito de entrega de cada imagem; o maior timestamp fecha o lote quando todos os itens têm registro. |
| Timestamp agregado | `entregas.data_conclusao` | O fluxo legado também preenche este campo em entregas parciais. Isoladamente, ele não comprova entrega completa. Só é fallback com status final de entrega. |
| Conclusão por função | `funcao_imagem` e `log_alteracoes` via `planejamento_execucao_helper.php` | Datas de conclusão de tarefas; separadas do evento de entrega R00. |
| Versões do plano | `entrega_planejamento_versao`, `entrega_planejamento_funcao`, `entrega_planejamento_evento` | Baseline e replanejamentos confirmados. |
| Histórico da projeção | `entrega_planejamento_projecao_operacional`, `entrega_planejamento_projecao_etapa` | Snapshots já persistidos na confirmação de fila, com data, autor e versão. A consulta comum não grava snapshots. |

## Decisão

Reutilizar os timestamps dos itens, sem nova coluna nem nova tabela de entrega. Todos os itens com timestamp válido comprovam o registro efetivo de entrega, mesmo se as funções forem reabertas depois. Para registros legados incompletos, aceitar `data_conclusao` somente com status final (`Entregue no prazo`, `Entregue com atraso` ou `Entrega antecipada`). Uma data de entrega parcial nunca encerra o plano.

O registro normal de entrega passa a ser idempotente: preserva o primeiro timestamp de cada item e a data agregada de uma entrega já encerrada. Não há edição automática ou nova interface para corrigir datas históricas. Correções administrativas devem ser explícitas e auditadas no fluxo responsável; não se corrige uma entrega replanejando seu plano.

A consulta do plano entregue preserva a versão confirmada e não executa invalidação estrutural nem simulação de replanejamento. A confirmação de plano também valida o encerramento no servidor, sob bloqueio da entrega, inclusive para chamadas feitas fora desta tela.

O cabeçalho compara separadamente o desvio em dias úteis contra o fim interno e a antecedência/atraso contra o prazo R00. O texto em dias corridos é identificado como tal. Projeções históricas existentes continuam consultáveis no Histórico; ausência de snapshot é informada, sem reconstruir uma projeção passada a partir de dados atuais.

## Apresentação

Preservar Inter, fundo azul escuro (`#0b111a`), superfícies do Flow, azul das etapas preparatórias e as cores atuais das funções. Roxo (`#8b5cf6`) representa previsão, verde (`#22c997`) representa entrega/conclusão e coral (`#ff4d60`) representa atraso. O Gantt mantém escala de calendário e dependências, com limite, data observada/projetada e desvio sempre visíveis. No estado encerrado, as ações e a leitura principal são históricas.
