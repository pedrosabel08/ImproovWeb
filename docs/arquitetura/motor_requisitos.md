# Motor de Requisitos

## Propósito

Este documento define a direção arquitetural para decidir se uma tarefa pode **iniciar**, **continuar** ou apenas precisa de acompanhamento operacional.

Ele separa três conceitos que hoje convivem no sistema, mas não são a mesma coisa:

1. **Requisito**: condição objetiva para uma transição da tarefa.
2. **Pendência Operacional**: obrigação do processo, resolvida pelo módulo dono e acompanhada por SLA.
3. **Flow Block**: registro de um impedimento percebido durante a execução.

O documento também registra o estado atual para que a evolução seja gradual e não trate comportamento legado como regra de negócio aprovada.

## Convenção de evidência

| Marca          | Significado                                                                                      |
| -------------- | ------------------------------------------------------------------------------------------------ |
| **Confirmada** | Regra observada no código e/ou banco atual.                                                      |
| **Inferida**   | Interpretação do comportamento existente; não é ainda uma regra de negócio formal.               |
| **Pendente**   | Decisão de negócio ou desenho técnico que precisa de definição antes de virar regra obrigatória. |

Quando houver conflito, uma regra **Confirmada** descreve o sistema atual; uma regra **Pendente** descreve o comportamento desejado ainda não implementado.

## Modelo conceitual

```text
Requisito de Projeto ou Produção
  -> avaliado para a transição solicitada
  -> não atendido: tarefa permanece Não iniciado e informa bloqueios
  -> atendido: tarefa pode iniciar

Tarefa em execução
  -> surge impedimento real
  -> Flow Block registra impacto e leva a tarefa para HOLD
  -> causa é resolvida no módulo de origem
  -> responsável confirma e replaneja
  -> tarefa volta para Em andamento

Pendência Operacional
  -> é criada e resolvida pelo módulo dono
  -> pode atender requisito de Projeto
  -> pode ser a causa registrada por um Flow Block
  -> não deve alterar status de tarefa sem avaliação do motor
```

### Tipos de requisito

| Tipo       | Quem normalmente atende                     | Exemplos                                                      |
| ---------- | ------------------------------------------- | ------------------------------------------------------------- |
| `PROJETO`  | Gestão, Arquitetura, Fotográfico ou cliente | Briefing, referências, links, arquivos técnicos, Fotográfico. |
| `PRODUCAO` | Etapa produtiva anterior                    | Tarefa concluída, arquivo final, aprovação, render aprovado.  |
| `SISTEMA`  | Regra interna automatizada                  | Subtipo válido, imagem ativa, configuração de fluxo.          |

### Tipos de bloqueio

| Tipo           | Efeito pretendido                                    |
| -------------- | ---------------------------------------------------- |
| `INICIO`       | Mantém a tarefa em `Não iniciado`.                   |
| `CONTINUIDADE` | Exige Flow Block e mantém a tarefa em `HOLD`.        |
| `INFORMATIVO`  | Gera alerta, mas não impede transição.               |
| `LEGADO`       | Representa HOLD ou regra anterior ainda não migrada. |

## Estado atual confirmado

### Campo `liberada`

**Confirmada.** Em `PaginaPrincipal/getFuncoesPorColaborador.php`, o campo `liberada` é calculado para tarefas de `funcao_imagem`.

Ele começa como `false` e pode ser liberado pela sequência fixa de funções, por exceções de Modelagem e Alteração, ou por ser a primeira função existente da imagem.

Ele não consulta Briefing, referências, Fotográfico, arquivos técnicos, arquivos da etapa anterior, Pendências Operacionais ou Flow Block.

**Inferida.** O significado atual é “elegível visualmente no Kanban pela sequência produtiva legada”, não “todos os requisitos atendidos”.

### HOLD de imagem

**Confirmada.** O cálculo atual considera a imagem em HOLD quando o status textual é `HOLD` ou quando `substatus_id = 7`. Nessa situação, `liberada` é `false` para todas as funções da imagem.

**Confirmada.** O campo de resposta chamado `imagem_status_id` contém, na verdade, `imagens_cliente_obra.substatus_id`. O status textual vem de `status_imagem.nome_status`.

### Flow Block

**Confirmada.** A API permite criar Flow Block para tarefa em qualquer status. Ao criar uma Issue bloqueante, altera `funcao_imagem.status` para `HOLD` quando a tarefa ainda não está nesse estado. A retomada exige ausência de Issues bloqueantes, resolução confirmada ou cancelamento e novo prazo.

### Aplicação das regras

**Confirmada.** O Kanban usa `liberada` para bloquear drag-and-drop, mas os endpoints de atualização de status não compartilham uma política única de requisitos. Há caminhos que alteram `funcao_imagem.status` diretamente.

**Pendente.** A validação definitiva de início precisa estar no backend e ser chamada por todos os comandos de transição de status.

## Política-alvo de avaliação

Para uma tarefa `T` e uma transição `A`, o motor deve devolver uma decisão estruturada, sem criar ou resolver pendências durante a leitura.

```text
avaliar(T, A):
  carregar requisitos aplicáveis a T, tipo de imagem e subtipo
  carregar evidências e fontes de cada requisito
  avaliar requisitos obrigatórios para A
  avaliar HOLD legado e Flow Block ativo
  retornar elegibilidade, bloqueios e avisos
```

Contrato sugerido:

```json
{
  "elegivel": false,
  "liberada": false,
  "transicao": "INICIAR",
  "bloqueios": [
    {
      "codigo": "ARQUIVO_MODELAGEM_AUSENTE",
      "tipo": "PRODUCAO",
      "origem": "funcao_anterior",
      "origem_id": 123,
      "responsavel_id": 45,
      "mensagem": "O arquivo final da Modelagem ainda não foi enviado.",
      "bloqueia": "INICIO"
    }
  ],
  "avisos": []
}
```

`liberada` pode permanecer como alias de compatibilidade de `elegivel` durante a migração. A lista de bloqueios é necessária para explicar a decisão ao usuário e permitir auditoria.

## Regras de transição pretendidas

| Transição                      | Regra pretendida                                            | Estado                                                             |
| ------------------------------ | ----------------------------------------------------------- | ------------------------------------------------------------------ |
| `Não iniciado -> Em andamento` | Todos os requisitos de início obrigatórios atendidos.       | **Pendente**: não há guarda central.                               |
| `Qualquer status -> HOLD`      | Impedimento real registrado por Flow Block.                 | **Confirmada** no fluxo Flow Block; há caminhos legados paralelos. |
| `HOLD -> Em andamento`         | Sem Issue bloqueante, resposta confirmada e replanejamento. | **Confirmada** em `FlowBlock/api.php`.                             |
| `Em andamento -> Em aprovação` | Entrega/arquivo da própria etapa conforme regra da função.  | **Pendente**: não há política unificada.                           |

## Relação entre requisito, pendência e Flow Block

| Situação                                          | Objeto principal                             | Estado pretendido da tarefa       |
| ------------------------------------------------- | -------------------------------------------- | --------------------------------- |
| Briefing ausente antes de Caderno                 | Requisito de Projeto + Pendência Operacional | `Não iniciado`                    |
| Arquivo de Modelagem ausente antes de Composição  | Requisito de Produção                        | `Não iniciado`                    |
| Referência deixa de ser válida durante Composição | Causa de origem + Flow Block                 | `HOLD`                            |
| Cliente demora a responder cobrança               | Pendência Operacional                        | Não altera tarefa automaticamente |
| Tarefa em HOLD legado sem Issue                   | Bloqueio `LEGADO`                            | Exibir e decidir migração         |

## Matriz mínima de direção

| Função            | Requisito                                            | Tipo     | Fonte provável                         | Estado       |
| ----------------- | ---------------------------------------------------- | -------- | -------------------------------------- | ------------ |
| Caderno           | Briefing validado                                    | Projeto  | briefing / checklist de projeto        | inicio       |
| Caderno           | Kickoff, se obrigatório                              | Projeto  | onboarding / evento                    | continuidade |
| Filtro            | Arquivos técnicos válidos                            | Projeto  | arquivos / briefing_requisitos_arquivo | inicio       |
| Modelagem         | Caderno ou filtro de assets concluído quando existir | Produção | `funcao_imagem`                        | inicio       |
| Modelagem         | Arquivos técnicos válidos quando aplicáveis          | Projeto  | arquivos                               | inicio       |
| Composição        | Modelagem concluída                                  | Produção | `funcao_imagem`                        | inicio       |
| Composição        | Arquivo final da Modelagem                           | Produção | arquivo vinculado à função             | inicio       |
| Finalização       | Composição concluída                                 | Produção | `funcao_imagem`                        | inicio       |
| Finalização       | Arquivo final da Composição                          | Produção | arquivo vinculado à função             | inicio       |
| Finalização       | Fotográfico concluído/aprovado                       | Projeto  | Fotográfico                            | inicio       |
| Finalização       | Referências disponíveis                              | Projeto  | módulo de referências                  | inicio       |
| Pós-produção      | Render aprovado                                      | Produção | Render / aprovação                     | inicio       |
| Alteração         | Comentários do cliente analisados                    | Produção | Pré-alteração                          | inicio       |
| Planta Humanizada | Subtipo e arquivos técnicos válidos                  | Projeto  | imagem / arquivos                      | inicio       |
| Planta Humanizada | Produção final do subtipo                            | Produção | funções/imagens do subtipo             | inicio       |
| Animação          | Imagens-base aprovadas                               | Produção | funções de imagem                      | inicio       |

## Decisões de negócio pendentes

1. Definir requisitos obrigatórios por função, tipo e subtipo de imagem.
2. Definir a evidência de cada requisito: campo, arquivo, status, aprovação ou conclusão de módulo.
3. Definir se Kickoff bloqueia, alerta ou é dispensável por obra.
4. Definir se ausência de predecessora significa “não se aplica” ou erro de planejamento.
5. Substituir o sentinela de colaborador “Não se aplica” por status ou motivo explícito.
6. Definir o que caracteriza Fotográfico, Referência e Render válidos.
7. Definir como requisitos são dispensados, reabertos e auditados.
8. Definir tratamento de HOLD de imagem, HOLD de tarefa e HOLD legado.
9. Definir se Flow Block pode ser criado antes do início e como aparece no Kanban.
10. Definir política para requisito que deixa de ser válido durante execução.

## Migração sugerida

1. Avaliar requisitos somente em leitura e devolver `bloqueios` ao lado de `liberada`.
2. Exibir motivos no Kanban e comparar decisões novas com a regra legada.
3. Aplicar a política no endpoint oficial de início.
4. Migrar endpoints que fazem `UPDATE funcao_imagem` direto.
5. Converter HOLDs legados em bloqueios identificáveis, sem perder histórico.
6. Remover exceções visuais somente após a política central estar em uso.

## Referências de implementação atual

- `PaginaPrincipal/getFuncoesPorColaborador.php`: cálculo legado de `liberada`.
- `PaginaPrincipal/scriptIndex.js`: bloqueio visual e exceção de Filtro.
- `helpers/pendencias_operacionais_helper.php`: agregação de Pendências.
- `helpers/flow_block_helper.php` e `FlowBlock/api.php`: Flow Block e HOLD da tarefa.
- `insereFuncao.php`, `atualizarFuncoesEmAndamento.php` e fluxos especializados: caminhos de alteração de status que precisam convergir para política central.

## Regras centralizadas de produção e Fotográfico

**Confirmada.** Para uma tarefa produtiva linear, o motor localiza a função
existente imediatamente anterior da mesma imagem. Ela precisa estar em
`Finalizado`, `Aprovado` ou `Aprovado com ajustes` e ter o arquivo enviado
(`file_uploaded_at` preenchido e sem upload pendente). A ausência de uma
função intermediária não interrompe a cadeia; a etapa é simplesmente ignorada.
Sem predecessora, o requisito é não aplicável. Uma tarefa dispensada permanece
dispensada.

Alteração e Pré-Finalização preservam suas exceções e requisitos próprios.
Finalização de `Fachada` e Composição de `Imagem Externa` usam, como
predecessora, a Modelagem da primeira Fachada cadastrada na obra. A ausência
dessa Modelagem-base é um bloqueio explícito.

**Confirmada.** O requisito `Fotográfico` consulta diretamente
`fotografico_plano`, sem depender de `requirements_version`: sem plano é não
aplicável; plano concluído é atendido; qualquer plano não concluído ou não
cancelado é pendente. Isso permite avaliar planos reais em checklists legados
sem migrar ou habilitar os demais requisitos históricos.

No Kanban, requisito de início pendente usa o destaque próprio de requisito;
vermelho/HOLD fica reservado para HOLD real ou Flow Block bloqueante.
