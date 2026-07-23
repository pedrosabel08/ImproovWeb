# Mapa de invalidação da prontidão

Este documento registra o mapeamento anterior a qualquer otimização incremental. Nesta fase, a verificação completa do servidor continua sendo a fonte de verdade para publicação e avanço para execução.

| Ação                                                             | Critérios potencialmente afetados                                                                                                                  |
| ---------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| `plan_update`                                                    | executor obrigatório; nenhum outro critério de plano é inferido localmente                                                                         |
| `image_update`                                                   | imagem sem decisão, exclusão sem motivo, ao menos uma confirmada, imagem incluída sem vínculo, imagem excluída ainda vinculada                     |
| `pin_create`                                                     | posição ausente, ponto sem descrição, ponto sem captura                                                                                            |
| `pin_update` (descrição)                                         | ponto sem descrição                                                                                                                                |
| `pin_update` (períodos/vínculos)                                 | ponto sem captura; imagem incluída sem vínculo; imagem excluída vinculada                                                                          |
| `pin_update` (somente coordenadas)                               | nenhum bloqueio atual diretamente; nesta fase continua no mesmo caminho de validação completa porque o endpoint também aceita descrição e vínculos |
| `pin_delete`                                                     | posição ausente; imagens que perderam o vínculo; pontos remanescentes                                                                              |
| `save_draft`                                                     | todos os critérios, pois pode substituir imagens, mapa, posições e capturas                                                                        |
| upload de `mapa`                                                 | mapa obrigatório                                                                                                                                   |
| `create_revision`                                                | versão ativa/mapa e todos os itens copiados para o novo rascunho                                                                                   |
| `publish`                                                        | todos os critérios; validação completa e autoritativa obrigatória dentro do fluxo de publicação                                                    |
| `start`, `submit_execution`, `review`, `hold_open`, `hold_close` | não alteram os critérios de prontidão de publicação; possuem validações próprias de estado, SLA e permissão                                        |

As mudanças desta primeira fase apenas deslocam a sincronização completa dos rascunhos para depois do commit da mutação principal, reduzindo o tempo de retenção do lock. Elas não deixam a interface decidir se o plano pode ser publicado.
