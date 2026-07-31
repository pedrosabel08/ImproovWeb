# Roteiro de testes Flow Connect / FlowReview

## Automatizado nesta entrega

`php FlowConnect/tests/run.php` cobre contratos, campos proibidos, chaves de idempotência, menção em comentário/resposta, duplicidade de mencionado, self-mention, três decisões de ângulo, três decisões reais de revisão, animação, direção, falha SFTP, SLA, precedência das flags, bypass seguro, templates, escape/truncamento, retry, 429, erro permanente, claim concorrente e constraints únicas.

`FlowConnect/tests/integration_after_migration.sql` contém as verificações de banco que só podem ser executadas depois da aprovação da migration.

## Manual após migration aprovada

### Menções

- Comentário com uma menção: uma linha de evento e uma delivery lógica.
- Resposta com uma menção: chave usa `resposta_id`.
- Duas menções: dois eventos e dois destinatários.
- Colaborador repetido: um evento efetivo pela chave de comentário/resposta + colaborador.
- Autor mencionando a si próprio: evento auditável e delivery lógica para o `mencionado_id`, quando a identidade estiver ativa.
- Usuário sem identidade Slack: delivery `UNRESOLVED`/shadow e dead-letter seguro.

### Ângulos

- `escolhido`, `escolhido_com_ajustes` e `ajustes`.
- Observação vazia e preenchida; conferir truncamento e escape.
- Em `shadow`, comparar responsável e texto sem entrega Flow Connect.

### Revisão

- Aprovação simples, ajuste e aprovação com ajustes.
- Confirmar que não existe produtor de reprovação.
- Envio para direção: dois fatos correlacionados, apenas a solicitação gera DM.
- Animação: mesma família semântica com `tipo_fluxo=animacao` e destinatário responsável.
- Repetir resolução de conflito SFTP: mesma chave quando o `historico_id` for preservado.

### Técnico

- Simular falha SFTP somente em ambiente controlado.
- Confirmar rollback do negócio, evento técnico persistido depois do rollback e nenhum caminho/segredo no payload.
- Simular timeout, 429, erro permanente e esgotamento até dead-letter.
- Confirmar que destinatário é `technical_admins`, nunca canal operacional.

### SLA

- Tarefa abaixo e acima do limite.
- Rodar scheduler duas vezes na mesma janela: um evento pela chave única.
- Resolver tarefa antes do worker: schedule `RESOLVED`, sem evento novo.
- Preencher `cancelled_at` e `silence_until`: sem cobrança.
- Comparar scheduler e `sla_check_cron.php` em shadow; ambos devem convergir para a mesma chave.

### Modos

- `off`: nenhuma consulta/insert Flow Connect e legado intacto.
- `shadow`: evento, plano e preview; nenhuma delivery enviada; legado intacto.
- `active`: Flow Connect entrega e somente o legado correspondente é ignorado.
- Override específico vence a flag geral.
- Falha ao persistir em active: legado funciona como fallback sem entrega simultânea Flow Connect.

### Concorrência

- Executar dois event workers e dois delivery workers simultâneos.
- Confirmar `SKIP LOCKED`, lease e ausência de duplicidade.
- Expirar manualmente um claim em banco de teste e confirmar recuperação.
