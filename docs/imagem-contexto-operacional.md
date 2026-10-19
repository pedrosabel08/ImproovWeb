# Contexto operacional da imagem — contrato v1

Entrada principal: `flow_imagem_contexto_operacional(mysqli $conn, int $imagemId): ?array`.
Entrada em lote: `flow_imagens_contextos_operacionais_lote(mysqli $conn, array $imagemIds): array`.
Uma imagem inexistente retorna `null`; uma imagem existente sem tarefas retorna `etapas: []`.
O helper apenas lê. Não foi criado endpoint HTTP nem alterada a UI do Kanban ou do Mapa de Pavimento. Um futuro endpoint deve validar sessão e acesso à obra/imagem antes de expor o contexto.

## Estrutura

`imagem` preserva ID, nome, obra, tipo, subtipo, status_id e substatus_id. Cada elemento de `etapas` representa exatamente um registro de `funcao_imagem` e contém:

- `funcao_id`, `funcao_nome`, `funcao_imagem_id`, `imagem_id`;
- `status`: valor original do banco, sem substituição por estado visual ou sigla;
- `responsavel_id`, `responsavel_nome`, `prazo`: prazo operacional original, normalizado para data ou null;
- `estado_visual`, `ordem`, `origem_ordem`, `participa_operacao`;
- `execucao_individual`: conclusão, HOLD, início e conclusão real calculados pelo helper de execução existente;
- `requisitos_inicio`: decisão e evidências do Motor de Requisitos existente;
- `contexto_planejamento`: projeção dos campos de planejamento permitidos;
- `ultimo_ciclo`: último ciclo oficial, se existir, sem reconstruir nova timeline histórica;
- `avisos`: conflitos e falta de evidência explícitos.

`etapas_ativas`, `etapas_concluidas`, `etapas_pendentes`, `etapas_bloqueadas` e `etapas_nao_operacionais` são listas de **funcao_imagem_id**, referenciando `etapas`. Não são IDs de função nem índices de array. `resumo` contém as contagens correspondentes.

Não há `funcao_atual` singular. A análise não encontrou um resolvedor oficial único por imagem: a variável `funcaoAtualId` no Kanban representa a tarefa sendo avaliada, não a seleção global de uma função da imagem.

## Estados visuais

| Condição                                     | Apresentação                                                       |
| -------------------------------------------- | ------------------------------------------------------------------ |
| Finalizado / Aprovado / Aprovado com ajustes | CONCLUIDA, conforme `flow_execucao_resumir_tarefa`                 |
| HOLD                                         | BLOQUEADA, conforme helper existente                               |
| Trabalho efetivo não concluído               | ATIVA, conforme `flow_execucao_status_trabalho_efetivo`            |
| Não iniciado e início inelegível no motor    | BLOQUEADA                                                          |
| Não iniciado e início elegível               | PENDENTE                                                           |
| Cancelado / dispensado                       | estado_visual null; registro preservado em etapas_nao_operacionais |

Dispensa respeita o responsável “Não se aplica”/ID 15 reconhecido pelo motor, ou o status explícito “Não se aplica”. Isso não significa conclusão. Os quatro estados visuais não são forçados sobre registros sem participação operacional.

O motor decide permissão para **iniciar**. Uma tarefa que já está “Em andamento” não é reclassificada como parada apenas porque faltam requisitos: continua ATIVA e recebe `ATIVA_COM_RESTRICAO_DE_INICIO`. HOLD mantém BLOQUEADA. Os status originais continuam disponíveis em ambos os casos.

Os aliases de UI TEA/APR/FIN não foram inventados nem persistidos neste helper. O banco consultado contém os nomes completos; o componente visual compartilhado futuro deverá reaproveitar o mapeamento de badges do Flow.

## Planejamento separado da operação

`contexto_planejamento.escopo = ENTREGA`. Só são projetados identificação da etapa/versão, ordem, início, prazo planejado, compromisso (inclusive histórico), previsão do colaborador e desvio/status temporal **individual** recalculado com a conclusão da própria tarefa.

`planejamento.timeline` não é devolvida. Seu estado ATUAL, conclusões coletivas, conclusão da entrega e desvio acumulado coletivo não participam da classificação operacional da imagem. Da timeline existente utiliza-se exclusivamente a posição da etapa para ordenar a apresentação.

Sem correspondência planejada, a ordem vem da mesma função `flow_tarefa_ordem_funcoes_operacionais()` usada pelo fallback do Kanban. Com plano, etapas correspondentes seguem o plano; as não correspondentes ficam depois, na ordem operacional. Empates usam o ID da tarefa apenas para apresentação, nunca para decidir qual função está ativa.

O prazo operacional `prazo` nunca é substituído pelo compromisso planejado. Datas ausentes não são sintetizadas. A conclusão sem evidência de data tem aviso explícito. O rótulo temporal existente do planejamento é preservado; ele pode considerar concluída no prazo uma tarefa finalizada sem data, e o aviso de ausência de evidência deve acompanhar esse caso.

## Reaberturas e repetição

O helper de execução usa o status atual da tarefa para determinar conclusão. Após reabertura, a conclusão real exposta passa a null mesmo quando existe um evento finalizado antigo. Os logs e ciclos oficiais continuam em suas fontes existentes.

Nenhum registro repetido é fundido ou descartado. Mesmo `funcao_id`, diferentes `funcao_imagem_id`: duas etapas distintas, podendo ambas estar ativas. O contexto sinaliza `FUNCAO_COM_MULTIPLOS_REGISTROS` com os IDs envolvidos. Não seleciona o registro de maior ID como vencedor e não atribui rodada por suposição. Não foram encontradas repetições por imagem/função na amostra atual do banco; a preservação foi validada por fixture.

## Validação

`tests/imagem_contexto_operacional_test.php` cobre finais, simultaneidade, HOLD, requisitos, tarefa ativa com bloqueio, reabertura, funções repetidas, canceladas/dispensadas, ausência de tarefas, datas e isolamento entre imagens (inclusive planejamento em lote).

`tools/validar_imagem_contexto_operacional.php` é exclusivo de CLI. Usa transação READ ONLY e rollback, executa os casos A–E e uma imagem com plano confirmado e gera `docs/imagem-contexto-operacional-exemplos.json`. Os dados representam uma fotografia da consulta, não fixtures imutáveis: se o trabalho real mudar, o script pede revisão das amostras.

O componente visual compacto/completo fica para depois da validação deste contrato. O Kanban atual continua consumindo seus helpers e endpoint existentes.

Comparação prática no Kanban autenticado: Quiosque 01/Filtro de assets (Luana, Em aprovação, 09/10), Sala de Jogos/Finalização (Bruna, HOLD, 12/10), Espaço Gourmet/Finalização (Bruna, Ajuste, 13/10) e Yoga/Modelagem + Composição (João Vitor, 16/10). No Yoga, o card combinado abre a Composição e a timeline antiga marca Modelagem como próxima; o contexto operacional preserva os dois registros “Em andamento”. Essa divergência de apresentação é intencional: não se usa a seleção do card como estado da imagem.

O caso B significa **todas as funções cadastradas concluídas**, não promoção do status da imagem. A amostra continua com seu status oficial R00; o helper não grava nem infere EF. Os registros completos e os grupos estão nos exemplos JSON e na tabela de exemplos Markdown.
