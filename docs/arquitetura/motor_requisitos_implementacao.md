# Motor de Requisitos — Projeto Técnico de Implementação

## Objetivo e limites

Este documento descreve como implementar, de forma incremental, a arquitetura já definida em motor_requisitos.md.

Ele preserva os conceitos aprovados:

- Requisito decide se uma transição pode ocorrer.
- Pendência Operacional continua pertencendo ao módulo que resolve a causa.
- Flow Block registra impedimento real durante execução; não substitui a causa nem a Pendência.

O escopo é unificar a decisão, a auditoria e a aplicação de transições. Não há código, migration ou alteração de regra de negócio neste documento.

## Premissas confirmadas

- O cálculo legado de liberada está em PaginaPrincipal/getFuncoesPorColaborador.php e é parcial.
- A Página Principal possui proteção visual própria em PaginaPrincipal/scriptIndex.js.
- Existem múltiplos endpoints que atualizam funcao_imagem.status diretamente.
- O Flow Block atual controla seu ciclo de HOLD, mas não é a guarda global de todos os endpoints.
- Pendências Operacionais são uma projeção agregada e devem permanecer leitura.
- Regras pendentes por função, tipo e subtipo não serão inventadas pelo motor.

---

# 1. Arquitetura geral

## 1.1 Componentes

    Página Principal / módulo de origem / API
                    |
                    v
          TaskTransitionService
                    |
          +---------+----------+
          |                    |
          v                    v
 RequirementEngine       TaskStatusWriter
          |
          +---------+----------+----------------+
          |                    |                |
          v                    v                v
 RequirementPolicy   RequirementProviders  ResultBuilder
          |
          v
 TransitionAuditRecorder + InvalidacaoDeRequisitos

| Componente                                 | Responsabilidade                                                                        | Não deve fazer                                   |
| ------------------------------------------ | --------------------------------------------------------------------------------------- | ------------------------------------------------ |
| Clientes: Página Principal, módulos e APIs | Solicitar avaliação, exibir motivos e solicitar transição.                              | Decidir localmente que uma tarefa está liberada. |
| TaskTransitionService                      | Validar ator, estado atual, requisitos e Flow Block; efetivar uma transição autorizada. | Reimplementar cada regra de requisito.           |
| RequirementEngine                          | Resolver política aplicável, coordenar providers e consolidar decisão.                  | Alterar status, criar Pendência ou criar Issue.  |
| RequirementPolicyCatalog                   | Informar quais requisitos se aplicam à função, imagem, subtipo e transição.             | Consultar fontes de evidência.                   |
| RequirementProvider                        | Consultar uma fonte de evidência e devolver estado normalizado.                         | Alterar a origem consultada.                     |
| RequirementResultBuilder                   | Montar resposta estável com bloqueios, avisos e evidências.                             | Conhecer SQL ou regras de tela.                  |
| TaskStatusWriter                           | Persistir somente a transição já autorizada.                                            | Decidir elegibilidade.                           |
| TransitionAuditRecorder                    | Registrar tentativa, política, evidências, decisão e resultado da escrita.              | Bloquear ou liberar.                             |
| InvalidacaoDeRequisitos                    | Marcar avaliação/projeção obsoleta quando uma evidência muda.                           | Alterar política ou a origem da evidência.       |

## 1.2 Separação entre leitura e comando

| Modo      | Entrada                            | Saída                                  | Efeito                                           |
| --------- | ---------------------------------- | -------------------------------------- | ------------------------------------------------ |
| Avaliação | tarefa e transição pretendida      | contrato completo de requisitos        | Somente leitura; auditoria diagnóstica opcional. |
| Transição | tarefa, ator, transição e contexto | status persistido ou decisão bloqueada | Valida, audita e grava somente se elegível.      |

O Kanban usará avaliação. Todo caminho que altera status deverá usar transição. Assim, a exibição e a proteção definitiva consultam a mesma política.

## 1.3 Política como dado de domínio

Cada definição de requisito deverá possuir:

| Informação         | Finalidade                                                   |
| ------------------ | ------------------------------------------------------------ |
| Código estável     | Identificar a regra, por exemplo BRIEFING_VALIDADO.          |
| Escopo             | Função, tipo de imagem, subtipo e demais filtros aplicáveis. |
| Transição afetada  | INICIAR, CONTINUAR, ENVIAR_APROVACAO ou outra aprovada.      |
| Tipo               | PROJETO, PRODUCAO ou SISTEMA.                                |
| Provider dono      | Fonte técnica da evidência.                                  |
| Severidade         | Bloqueio, aviso ou informação.                               |
| Responsável padrão | Pessoa ou papel responsável pela causa.                      |
| Versão             | Reproduzir decisão histórica.                                |
| Dispensa           | Se é permitida, por quem e por quanto tempo.                 |

Enquanto uma regra estiver pendente, ela pode ser apresentada como diagnóstico, mas não deve bloquear transição.

---

# 2. Fluxo completo de avaliação

## 2.1 Início de tarefa

    Usuário solicita Não iniciado -> Em andamento
      -> cliente envia comando com tarefa, ator e origem
      -> TaskTransitionService valida acesso e estado atual
      -> RequirementEngine avalia requisitos de INICIAR
      -> providers carregam evidências somente leitura
      -> motor consolida bloqueios, avisos e dispensas
      -> bloqueada: não altera status e devolve contrato detalhado
      -> elegível: TaskStatusWriter persiste Em andamento
      -> auditoria registra tentativa, decisão e escrita
      -> evento invalida projeções e atualiza clientes

### Etapas

1. Normalizar o pedido usando identidade da tarefa e transição sem depender de ID de coluna do frontend.
2. Validar sessão, permissão do ator, status persistido e conflito de concorrência.
3. Carregar contexto: tarefa, imagem, obra, função, tipo, subtipo e prazo proposto.
4. Selecionar somente requisitos aprovados para a transição solicitada.
5. Executar providers com leitura apenas.
6. Aplicar dispensas válidas antes da classificação final.
7. Converter requisito não atendido em bloqueio, aviso ou informação conforme política.
8. Quando elegível, gravar status em transação e validar estado novamente antes da escrita.
9. Publicar evento de invalidação e registrar auditoria.

## 2.2 Continuidade depois de HOLD

    Usuário solicita HOLD -> Em andamento
      -> TaskTransitionService carrega tarefa
      -> FlowBlockProvider informa Issues ativas e confirmação pendente
      -> RequirementEngine avalia CONTINUAR
      -> Issue bloqueante: devolve bloqueio de CONTINUIDADE
      -> requisitos atendidos: valida replanejamento exigido
      -> TaskStatusWriter executa retomada
      -> auditoria registra Issue e ciclo relacionados

O ciclo atual de confirmação e replanejamento do Flow Block é regra confirmada e será preservado. O novo serviço apenas se torna a porta comum da transição.

## 2.3 Avaliação somente leitura do Kanban

    getFuncoesPorColaborador
      -> carrega tarefas básicas
      -> solicita avaliação em lote para INICIAR das tarefas Não iniciado
      -> incorpora elegibilidade, bloqueios resumidos e versão da política
      -> preserva liberada para compatibilidade durante a migração

A avaliação em lote não pode usar uma regra simplificada. Providers deverão carregar dados por coleção ou reutilizar contexto pré-carregado, retornando a mesma decisão da avaliação individual.

---

# 3. RequirementEngine

## 3.1 Responsabilidades

- Resolver política aplicável a tarefa e transição.
- Coordenar providers e contexto compartilhado.
- Aplicar dispensa, severidade e precedência de bloqueios.
- Devolver resultado explicável e estável.
- Suportar avaliação individual e em lote.
- Informar versão da política e metadados para auditoria.

O motor não autoriza usuário, não grava status, não cria Pendência e não cria Flow Block.

## 3.2 Métodos públicos projetados

| Método conceitual   | Entrada                                    | Saída                           | Consumidor                    |
| ------------------- | ------------------------------------------ | ------------------------------- | ----------------------------- |
| avaliarTransicao    | tarefa, transição, ator e contexto         | avaliação completa              | Comando de mudança de status. |
| avaliarInicio       | tarefa e ator                              | avaliação para INICIAR          | Compatibilidade com liberada. |
| avaliarContinuidade | tarefa, ator e novo prazo quando aplicável | avaliação para CONTINUAR        | Retomada do Flow Block.       |
| avaliarEmLote       | tarefas, transição e ator                  | avaliações indexadas por tarefa | Kanban e dashboards.          |
| explicarBloqueio    | bloqueio e contexto                        | detalhe legível e auditável     | Modal e suporte.              |

## 3.3 Operações internas projetadas

| Operação              | Responsabilidade                                               |
| --------------------- | -------------------------------------------------------------- |
| Resolver contexto     | Carregar tarefa, imagem, obra, função, tipo, subtipo e estado. |
| Selecionar política   | Resolver regras aprovadas, escopo e versões.                   |
| Preparar providers    | Agrupar necessidades e viabilizar carga em lote.               |
| Avaliar definição     | Chamar provider e normalizar retorno.                          |
| Aplicar dispensa      | Verificar validade temporal, autorização e escopo.             |
| Classificar resultado | Transformar não atendimento em bloqueio, aviso ou informação.  |
| Ordenar bloqueios     | Priorizar sistema, Flow Block e requisitos de domínio.         |
| Montar contrato       | Produzir resposta determinística para API e auditoria.         |

## 3.4 Entradas obrigatórias

| Dado                        | Uso                                                                           |
| --------------------------- | ----------------------------------------------------------------------------- |
| Identidade da tarefa        | Inicialmente funcao_imagem_id; evoluir para tipo de tarefa quando necessário. |
| Tipo de tarefa              | Diferenciar imagem, animação e futuras famílias.                              |
| Transição                   | Obrigatória; nunca inferida do texto da coluna.                               |
| Ator                        | Autorização, visibilidade e dispensas.                                        |
| Contexto da operação        | Origem, correlação, prazo proposto e modo leitura/comando.                    |
| Versão de política opcional | Reprodução e rollout controlado.                                              |

---

# 4. Requirement Providers

## 4.1 Contrato comum

Todo provider recebe contexto normalizado e definição de requisito. Ele devolve:

- estado: ATENDIDO, NAO_ATENDIDO, NAO_APLICAVEL, INDETERMINADO ou ERRO_FONTE;
- evidências e identificadores de origem;
- responsável pela correção, quando identificável;
- mensagem de negócio e mensagem técnica;
- data de verificação, origem e versão dos dados;
- escopo de tarefas a invalidar quando sua fonte mudar.

INDETERMINADO e ERRO_FONTE não podem ser silenciosamente considerados atendidos. A política deverá definir se bloqueiam, avisam ou exigem ação operacional.

## 4.2 Providers iniciais

| Provider                     | Consulta / responsabilidade                                              | Requisitos atendidos                        | Situação                                  |
| ---------------------------- | ------------------------------------------------------------------------ | ------------------------------------------- | ----------------------------------------- |
| TaskSequenceProvider         | Funções aplicáveis, predecessoras configuradas e estados finais aceitos. | Tarefa anterior concluída.                  | Substitui gradualmente ordem fixa legada. |
| BriefingProvider             | Briefing, checklist/projeto e evidência de validação.                    | Briefing e Kickoff aprovados.               | Depende de fonte/aceite aprovados.        |
| ArquivoProvider              | Arquivos por obra, imagem, função, categoria, versão e estado.           | Arquivos técnicos e arquivo final anterior. | Depende do catálogo de categorias.        |
| ReferenciaProvider           | Referências/moodboard e validade.                                        | Referências de Projeto.                     | Depende de estado válido definido.        |
| FotograficoProvider          | Plano, execução e conferência.                                           | Fotográfico concluído/aprovado.             | Depende de definição de negócio.          |
| RenderProvider               | Render, lote e aprovação.                                                | Render aprovado.                            | Depende de regra por função.              |
| ReviewProvider               | Review, comentários e consolidação.                                      | Comentários consolidados.                   | Depende de critério de consolidação.      |
| FlowBlockProvider            | Issues, pausa, confirmação e elegibilidade de retomada.                  | Continuidade e bloqueios Flow Block.        | Reaproveita regra atual confirmada.       |
| ImageHoldProvider            | Status/substatus da imagem e HOLD legado.                                | Bloqueio de sistema/legado.                 | Requer convergência com Flow Block.       |
| PendenciaOperacionalProvider | Contrato canônico das fontes de Pendência.                               | Requisitos de Projeto ligados a Pendência.  | Nunca cria ou resolve Pendência.          |
| AnimacaoBaseProvider         | Imagens-base e seus estados.                                             | Imagens-base aprovadas.                     | Necessário antes de bloquear Animação.    |

## 4.3 Extensibilidade

A inclusão de novo requisito seguirá este processo:

    aprovar código e política
      -> designar provider responsável
      -> implementar provider aderente ao contrato
      -> criar testes de provider e política
      -> habilitar em observação
      -> somente depois habilitar como bloqueio

O núcleo conhece contrato e código de política; não deve ganhar condições específicas por função ou módulo.

## 4.4 Desempenho

Para o Kanban, providers devem avaliar em lote e agrupar consultas por obra, imagem, função/predecessora, subtipo, lote de Render/Fotográfico, responsáveis e Issues. O resultado não pode diferir da avaliação individual.

---

# 5. Contrato do resultado

## 5.1 Objeto de avaliação

    {
      "tarefa": {
        "id": 123,
        "tipo": "FUNCAO_IMAGEM",
        "funcao_id": 3,
        "imagem_id": 456,
        "obra_id": 789,
        "status_atual": "Não iniciado"
      },
      "transicao": "INICIAR",
      "elegivel": false,
      "liberada": false,
      "decisao": "BLOQUEADA",
      "politica": {
        "versao": "requisitos-v1",
        "avaliada_em": "2026-07-24T10:30:00-03:00"
      },
      "bloqueios": [],
      "avisos": [],
      "requisitos_avaliados": [],
      "resumo": {
        "total": 0,
        "atendidos": 0,
        "bloqueantes": 0,
        "dispensados": 0,
        "indeterminados": 0
      }
    }

liberada é alias de compatibilidade de elegivel durante a migração. Elegivel e decisao são os campos canônicos novos.

## 5.2 Estrutura de bloqueio ou aviso

    {
      "codigo": "ARQUIVO_MODELAGEM_AUSENTE",
      "tipo": "PRODUCAO",
      "severidade": "BLOQUEIO",
      "bloqueia": "INICIO",
      "origem": {
        "modulo": "ARQUIVOS",
        "entidade": "FUNCAO_IMAGEM",
        "id": 122,
        "url_acao": "..."
      },
      "responsavel": {
        "colaborador_id": 45,
        "papel": "MODELADOR"
      },
      "mensagem": "O arquivo final da Modelagem ainda não foi enviado.",
      "mensagem_tecnica": "Nenhum arquivo válido da categoria definida pela política foi encontrado.",
      "evidencias": [],
      "dispensavel": true,
      "dispensa_aplicada": false
    }

| Campo/regra      | Finalidade                                                 |
| ---------------- | ---------------------------------------------------------- |
| codigo           | Estável; não depende do texto exibido.                     |
| mensagem         | Texto de ação para usuário.                                |
| mensagem_tecnica | Suporte e auditoria.                                       |
| origem           | Aponta para a causa, não para o card bloqueado.            |
| responsavel      | Quem pode resolver a causa; pode não ser o dono da tarefa. |
| evidencias       | IDs, status e datas que sustentaram a decisão.             |
| severidade       | BLOQUEIO, AVISO ou INFORMATIVO.                            |
| dispensa         | Expõe se foi permitida e aplicada.                         |

## 5.3 Requisito avaliado

Todo requisito aparece no resultado, inclusive quando atendido.

| Campo                | Finalidade                                                                         |
| -------------------- | ---------------------------------------------------------------------------------- |
| código e versão      | Identificar definição aplicada.                                                    |
| provider             | Explicar origem técnica.                                                           |
| estado               | Atendido, não atendido, não aplicável, dispensado, indeterminado ou erro de fonte. |
| obrigatoriedade      | Distinguir bloqueio de aviso.                                                      |
| evidências           | Registrar fatos usados.                                                            |
| origem e responsável | Direcionar ação corretiva.                                                         |
| avaliado_em          | Auditoria e diagnóstico de desatualização.                                         |

## 5.4 Decisões possíveis

| Decisão            | Condição                                                                          |
| ------------------ | --------------------------------------------------------------------------------- |
| ELEGIVEL           | Nenhum bloqueio ativo para a transição.                                           |
| BLOQUEADA          | Há ao menos um bloqueio obrigatório.                                              |
| INDETERMINADA      | Fonte indisponível ou política insuficiente; severidade é definida pela política. |
| NAO_APLICAVEL      | Transição não se aplica ao estado/tipo atual.                                     |
| CONFLITO_DE_ESTADO | Tarefa mudou após avaliação ou transição não é permitida.                         |

---

# 6. Integração com o sistema

## 6.1 Consumidores obrigatórios

| Módulo                         | Papel futuro                                               | Integração                                             |
| ------------------------------ | ---------------------------------------------------------- | ------------------------------------------------------ |
| Página Principal / Kanban      | Exibir decisão e solicitar transição.                      | Avaliação em lote e comando via TaskTransitionService. |
| Detalhe de tarefa              | Exibir bloqueios, evidências e auditoria resumida.         | Avaliação individual.                                  |
| Flow Block                     | Validar continuidade pela mesma decisão e preservar ciclo. | CONTINUAR e FlowBlockProvider.                         |
| Flow Review                    | Delegar transições decorrentes de review.                  | Serviço de transição e ReviewProvider.                 |
| Fotográfico                    | Publicar fatos que alteram evidência.                      | FotograficoProvider e invalidação.                     |
| Render                         | Publicar fatos de render/aprovação.                        | RenderProvider e invalidação.                          |
| Pendências Operacionais        | Exibir causas e links; não mudar status.                   | Projeção do resultado e invalidações.                  |
| Arquivos / Flow Drive / Upload | Publicar alteração de evidência.                           | ArquivoProvider e invalidação.                         |
| Dashboard de obra              | Exibir bloqueios do mesmo contrato.                        | Avaliação contextual.                                  |
| Animação                       | Validar imagens-base quando política habilitar.            | AnimacaoBaseProvider.                                  |

## 6.2 Endpoints e fluxos a migrar

| Endpoint/arquivo atual                         | Uso atual                                      | Destino                                                      |
| ---------------------------------------------- | ---------------------------------------------- | ------------------------------------------------------------ |
| insereFuncao.php                               | Cria/atualiza função e aceita status recebido. | Delegar transição ao serviço.                                |
| insereFuncao2.php                              | Atualiza funções em lote.                      | Separar planejamento de mudança de status; validar por item. |
| atualizarFuncoesEmAndamento.php                | Retoma/inicia tarefas.                         | Usar INICIAR ou CONTINUAR.                                   |
| atribuir_flow_radar.php                        | Atribui e move para Em andamento.              | Separar atribuição de início e validar antes da transição.   |
| Arquitetura/update_funcao_caderno.php          | Atualiza status/prazo.                         | Encaminhar transição pelo serviço.                           |
| Alteracao/updateStatusLote.php                 | Atualiza status de várias funções.             | Retornar decisão por item.                                   |
| FlowBlock/api.php                              | Cria HOLD e continua tarefa.                   | Preservar ciclo e delegar retomada ao serviço.               |
| FlowReview/revisarTarefa.php                   | Aplica decisão de review.                      | Delegar transição da tarefa.                                 |
| uploadArquivos.php e uploadFinal.php           | Atualizam status após arquivo.                 | Publicar evidência e usar serviço quando houver transição.   |
| upload_enqueue.php e scripts/upload_worker.php | Gerenciam pendência/conclusão de upload.       | Publicar fato de evidência; não liberar localmente.          |
| FlowDrive/upload.php                           | Altera arquivo e, em casos atuais, status.     | Separar evidência de arquivo da transição.                   |
| Render/ajax.php e addRender.php                | Avançam/redefinem estados de Render.           | Usar transição e invalidação.                                |
| Entregas/review_batch_action.php               | Marca upload/status.                           | Publicar evidência e delegar transição.                      |
| PreAlteracao/conclusao_helpers.php             | Altera tarefa ao concluir fluxo.               | Validar transição resultante.                                |

atualizarFuncao.php já responde 410 para HOLD direto. Deve permanecer como compatibilidade até seus consumidores migrarem.

## 6.3 Compatibilidade inicial

- getFuncoesPorColaborador.php continuará devolvendo liberada.
- O novo contrato será anexado progressivamente, primeiro para tarefas Não iniciado.
- O Kanban exibirá motivos novos sem remover bolinha ou estilos legados na primeira fase.
- Nenhum endpoint será bloqueado pelo motor antes de estar migrado e observado.

---

# 7. Estratégia de invalidação

## 7.1 Princípios

A decisão autoritativa é sempre recalculável a partir das fontes. Cache ou projeção são otimização. Toda mudança de evidência deve identificar tarefas afetadas, marcar avaliações obsoletas, publicar evento e recalcular sob demanda.

## 7.2 Matriz de invalidação

| Evento de origem                                                         | Tarefas a invalidar                                               | Providers afetados                                        |
| ------------------------------------------------------------------------ | ----------------------------------------------------------------- | --------------------------------------------------------- |
| Briefing criado, validado ou alterado                                    | Funções da obra/tipo que exijam Briefing ou Kickoff.              | BriefingProvider                                          |
| Arquivo técnico enviado, trocado ou invalidado                           | Funções da imagem/obra/tipo dependentes da categoria.             | ArquivoProvider                                           |
| Arquivo final de etapa enviado ou removido                               | Sucessoras configuradas para essa função/imagem.                  | ArquivoProvider e TaskSequenceProvider                    |
| Status de tarefa anterior alterado                                       | Sucessoras da mesma imagem; regras por subtipo.                   | TaskSequenceProvider                                      |
| Tipo/subtipo alterado                                                    | Todas as funções da imagem e correlatas por subtipo.              | TaskSequenceProvider, ImageHoldProvider e ArquivoProvider |
| Imagem entra/sai de HOLD                                                 | Todas as funções da imagem.                                       | ImageHoldProvider                                         |
| Flow Block criado, pausado, resolvido, confirmado, reaberto ou cancelado | Tarefa da Issue e cartões de continuidade.                        | FlowBlockProvider                                         |
| Referência muda de estado                                                | Funções que dependam de referências da obra/imagem.               | ReferenciaProvider                                        |
| Fotográfico muda de estado                                               | Funções que dependam de Fotográfico.                              | FotograficoProvider                                       |
| Render criado, aprovado, reprovado ou removido                           | Pós-produção e funções definidas pela política.                   | RenderProvider                                            |
| Review/comentários consolidados ou reabertos                             | Alterações e tarefas definidas pela política.                     | ReviewProvider                                            |
| Pendência abre, conclui ou cancela                                       | Somente tarefas cujo requisito use essa Pendência como evidência. | PendenciaOperacionalProvider                              |
| Dispensa criada, vencida ou revogada                                     | Tarefa/requisito no escopo da dispensa.                           | Motor e provider correspondente                           |
| Política publicada                                                       | Todas as tarefas no escopo.                                       | RequirementPolicyCatalog                                  |

## 7.3 Granularidade e eventos

- Preferir tarefa individual quando a evidência tiver vínculo explícito.
- Usar obra, tipo ou subtipo apenas quando a política exigir escopo compartilhado.
- Não recarregar toda a carteira para evento de uma imagem.
- Eventos devem transportar IDs e versão de política, não resultado completo.
- O cliente deve buscar decisão atual após receber invalidação.

---

# 8. Auditoria

## 8.1 Eventos auditáveis

| Evento                     | Quando                                        |
| -------------------------- | --------------------------------------------- |
| Avaliação diagnóstica      | Modo observação para comparar motor e legado. |
| Tentativa de transição     | Sempre que houver comando de mudança.         |
| Transição bloqueada        | Bloqueio ou conflito de estado.               |
| Transição concluída        | Após escrita confirmada.                      |
| Dispensa aplicada/revogada | Sempre que afetar decisão.                    |
| Política publicada         | Quando alterar regras usadas para decidir.    |

## 8.2 Dados mínimos

| Grupo      | Dados                                                               |
| ---------- | ------------------------------------------------------------------- |
| Identidade | ID da avaliação, correlação, tarefa, imagem, obra e tipo de tarefa. |
| Ator       | colaborador, usuário, origem e permissão usada.                     |
| Transição  | status anterior, solicitado, final e prazo proposto.                |
| Decisão    | elegível, bloqueios, avisos e requisito decisivo.                   |
| Política   | versão, definições e dispensas aplicadas.                           |
| Evidência  | provider, IDs, status, datas e snapshot mínimo.                     |
| Escrita    | resultado da transação, erro, versão de estado e timestamps.        |

## 8.3 Segurança e retenção

- Auditoria registra IDs e referências, não duplica conteúdo de arquivos, comentários, Briefing ou Issue.
- A auditoria não amplia acesso a anexos, menções ou comentários de Flow Block.
- Política de retenção e acesso deverá seguir os controles existentes do sistema.

---

# 9. Plano de migração

## Fase 0 — Preparação e decisões

- Congelar conjunto inicial de requisitos realmente aprovados.
- Definir fontes de evidência, categorias de arquivo, dispensas e responsáveis.
- Inventariar consumidores de mudança de status por risco.

Risco: transformar regra incompleta em bloqueio.  
Mitigação: ativar apenas requisito com evidência e responsável definidos.

## Fase 1 — Motor somente leitura

- Criar catálogo e providers em modo observação.
- Avaliar INICIAR em lote para tarefas Não iniciado.
- Comparar elegivel novo com liberada legado sem impedir usuários.
- Registrar divergências e causas.

Risco: consultas extras degradarem Kanban.  
Mitigação: carga em lote, métricas por provider e limites de escopo.

## Fase 2 — Kanban exibe bloqueios

- Anexar contrato resumido ao JSON de getFuncoesPorColaborador.php.
- Mostrar bloqueios e links de ação sem remover regra legada.
- Exibir divergência apenas para gestão ou diagnóstico.

Risco: mensagens contraditórias.  
Mitigação: mensagem canônica por código e treinamento de transição.

## Fase 3 — Serviço de transição na Página Principal

- Introduzir TaskTransitionService no caminho principal.
- Fazer insereFuncao.php delegar mudança de status, preservando contrato externo temporário.
- Tornar bloqueios efetivos apenas para políticas aprovadas.

Risco: regressão em tarefas históricas ou exceções.  
Mitigação: feature flag por política/obra, fallback auditado e rollout por grupo.

## Fase 4 — Flow Block e continuidade

- Encaminhar retomada de FlowBlock/api.php pelo serviço.
- Preservar confirmação, replanejamento e histórico de ciclos.
- Classificar HOLD legado como LEGADO sem conversão automática.

Risco: quebra do fluxo de retomada.  
Mitigação: testes de Issue aberta, pausada, resolvida não confirmada, confirmada, cancelada e múltiplas Issues.

## Fase 5 — Endpoints paralelos

- Migrar Flow Radar, lotes, Arquitetura, Alteração, uploads, Flow Review, Render, Flow Drive e Pré-Alteração.
- Remover escrita direta de status de cada caminho já migrado.
- Medir tentativas restantes de contorno.

Risco: jobs assíncronos sem ator.  
Mitigação: ator de sistema, origem explícita e política específica para jobs.

## Fase 6 — Substituição definitiva de liberada

- Fazer liberada ser alias de elegivel devolvido pelo motor.
- Remover ordem fixa e exceções duplicadas após equivalência comprovada.
- Tornar motivos detalhados a fonte visual única do Kanban.

Risco: consumidores antigos de liberada.  
Mitigação: compatibilidade temporária, telemetria e remoção somente após inventário concluído.

---

# 10. Impactos previstos

## 10.1 Componentes e arquivos a criar

| Grupo        | Componentes previstos                                                                                 |
| ------------ | ----------------------------------------------------------------------------------------------------- |
| Núcleo       | Serviço de transição, motor, catálogo de política e construtor de resultado.                          |
| Providers    | Sequência, arquivos, briefing, referências, Fotográfico, Render, Review, Flow Block, HOLD e Animação. |
| Auditoria    | Repositório/serviço de auditoria e consulta administrativa.                                           |
| Invalidação  | Publicador e consumidor de eventos de evidência/requisito.                                            |
| Testes       | Testes de providers, matriz de políticas, transições e regressão de endpoints.                        |
| Banco futuro | Estruturas versionadas para políticas, dispensas, auditoria e projeção/cache, se aprovadas.           |

Os nomes e caminhos concretos seguirão o padrão PHP existente e não são criados nesta etapa.

## 10.2 Arquivos e módulos a modificar

| Arquivo/módulo                                                               | Mudança futura esperada                                                        |
| ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| PaginaPrincipal/getFuncoesPorColaborador.php                                 | Consumir avaliação em lote e expor novo contrato, preservando compatibilidade. |
| PaginaPrincipal/scriptIndex.js                                               | Renderizar bloqueios recebidos e remover decisão própria gradualmente.         |
| insereFuncao.php e insereFuncao2.php                                         | Delegar transições ao serviço central.                                         |
| atualizarFuncoesEmAndamento.php                                              | Usar INICIAR e CONTINUAR.                                                      |
| FlowBlock/api.php                                                            | Delegar retomada e registrar contexto da Issue.                                |
| helpers/pendencias_operacionais_helper.php                                   | Consumir somente projeções; não criar efeitos na avaliação.                    |
| Review, Render, arquivos, Flow Drive, Alteração, Arquitetura e Pré-Alteração | Publicar evidência/invalidação e delegar status.                               |
| assets/js/upload-ws.js e scripts/ws-server.js                                | Transportar eventos de invalidação quando necessário.                          |

## 10.3 Riscos prioritários

| Risco                      | Efeito                            | Controle                                                        |
| -------------------------- | --------------------------------- | --------------------------------------------------------------- |
| Regra nova mais restritiva | Tarefas deixam de iniciar.        | Observação, feature flags e rollout gradual.                    |
| Regra nova mais permissiva | Tarefas iniciam sem requisito.    | Comparação automática e auditoria.                              |
| Provider lento             | Kanban lento.                     | Avaliação em lote, métricas e cache somente de projeção.        |
| Endpoint não migrado       | Contorno da validação.            | Inventário, telemetria e retirada gradual de UPDATE direto.     |
| Fonte inconsistente        | Decisão errada/indeterminada.     | Estado INDETERMINADO, evidência auditável e política explícita. |
| Concorrência               | Estado muda após avaliação.       | Verificar versão/estado na mesma transação.                     |
| HOLD legado                | Bloqueio sem causa identificável. | Classificação LEGADO e migração sem perda de histórico.         |

## Critério de prontidão

Antes de implementar um requisito bloqueante, precisam estar definidos:

1. código estável e escopo de função/transição;
2. fonte de evidência e provider dono;
3. estado que representa atendimento;
4. responsável pela correção;
5. regra de dispensa;
6. severidade e mensagem;
7. estratégia de invalidação;
8. testes de cenário atendido, bloqueado, dispensado e fonte indisponível.

Sem esses itens, a regra permanece Pendente ou, no máximo, diagnóstico. Ela não deve bloquear operação.

