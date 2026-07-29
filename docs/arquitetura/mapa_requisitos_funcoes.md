# Mapa de Requisitos das Funções

> Este mapa combina a direção arquitetural com o levantamento da implementação
> atual. A seção **Matriz inicial** é uma proposta de negócio; ela não afirma
> que todas as regras já estejam aplicadas pelo sistema.

## Objetivo
Este documento define os requisitos necessários para iniciar, continuar ou concluir cada função do fluxo produtivo.

## Classificação

### Requisitos de Projeto
São atendidos por áreas de gestão e arquitetura.

- Briefing
- Kickoff
- Referências
- Fotográfico
- Google Earth
- Links
- Arquivos técnicos

### Requisitos de Produção
São atendidos pela execução da etapa anterior.

- Tarefa concluída
- Arquivo enviado
- Aprovação
- Render disponível

## Matriz inicial

| Função            | Requisito                    | Tipo     | Responsável    | Bloqueia |
| ----------------- | ---------------------------- | -------- | -------------- | -------- |
| Caderno           | Briefing                     | Projeto  | Gestão         | Início   |
| Caderno           | Kickoff                      | Projeto  | Gestão         | Avaliar  |
| Filtro            | Arquivos técnicos            | Projeto  | Gestão         | Início   |
| Modelagem         | Caderno/Arquivos técnicos    | Produção | Etapa anterior | Início   |
| Composição        | Modelagem concluída          | Produção | Modelador      | Início   |
| Composição        | Arquivo da modelagem enviado | Produção | Modelador      | Início   |
| Composição        | Referências                  | Projeto  | Arquitetura    | Início   |
| Finalização       | Composição concluída         | Produção | Compositor     | Início   |
| Finalização       | Arquivo da composição        | Produção | Compositor     | Início   |
| Finalização       | Fotográfico                  | Projeto  | Fotográfico    | Início   |
| Finalização       | Referências                  | Projeto  | Arquitetura    | Início   |
| Pós-produção      | Render aprovado              | Produção | Render         | Início   |
| Alteração         | Comentários consolidados     | Produção | Review         | Início   |
| Planta Humanizada | Arquivos técnicos e subtipo  | Projeto  | Gestão         | Início   |
| Planta Humanizada | Arquivos finais do subtipo   | Produção | Produção       | Início   |
| Animação          | Imagens-base aprovadas       | Produção | Produção       | Início   |

## Princípios

- Requisitos de Projeto são resolvidos pelas Pendências Operacionais.
- Requisitos de Produção são resolvidos pela conclusão efetiva da etapa anterior.
- O Flow Block registra quando um requisito impede a execução de uma tarefa.

## Status das regras

| Marca          | Significado                                                      |
| -------------- | ---------------------------------------------------------------- |
| **Confirmada** | Localizada no código ou banco atual.                             |
| **Inferida**   | Conclusão técnica a partir do comportamento atual.               |
| **Pendente**   | Decisão de negócio ou detalhe de implementação ainda necessário. |

O documento complementar `motor_requisitos.md` descreve o contrato sugerido do
motor, os tipos de bloqueio e a estratégia de migração.

## Matriz expandida: regra proposta versus implementação

| Função            | Requisito proposto          | Estado da regra de negócio                       | Evidência atual                                                                      | Situação no código                              |
| ----------------- | --------------------------- | ------------------------------------------------ | ------------------------------------------------------------------------------------ | ----------------------------------------------- |
| Caderno           | Briefing validado           | **Pendente**: precisa definir evidência.         | É a primeira função da ordem fixa.                                                   | **Confirmada:** pode ser liberado sem briefing. |
| Caderno           | Kickoff                     | **Pendente**: “Avaliar” não define se bloqueia.  | Não consultado no cálculo.                                                           | Não implementado como requisito.                |
| Filtro de assets  | Arquivos técnicos válidos   | **Pendente**: definir categorias/aceite.         | Frontend possui exceção para liberar Filtro.                                         | **Confirmada:** não bloqueia pelo motor atual.  |
| Modelagem         | Caderno concluído           | **Inferida:** depende da predecessora existente. | Ordem fixa e status final da predecessora.                                           | Parcialmente implementado.                      |
| Modelagem         | Arquivos técnicos           | **Pendente**.                                    | Não consultado.                                                                      | Não implementado.                               |
| Composição        | Modelagem concluída         | **Inferida:** regra de sequência atual.          | Pode exigir Modelagem e Filtro se `liberar_modelagem=1`.                             | Parcialmente implementado.                      |
| Composição        | Arquivo da Modelagem        | **Pendente**: definir arquivo válido.            | `liberada` não consulta arquivos.                                                    | Não implementado.                               |
| Composição        | Referências                 | **Pendente**: definir fonte e aceite.            | Não consultado.                                                                      | Não implementado.                               |
| Pré-Finalização   | Requisitos próprios         | **Pendente**.                                    | Existe na ordem fixa, sem matriz inicial.                                            | Só segue predecessora.                          |
| Finalização       | Composição concluída        | **Inferida:** depende da predecessora existente. | Pré-Finalização pode se tornar a predecessora quando existir.                        | Parcialmente implementado.                      |
| Finalização       | Arquivo da Composição       | **Pendente**.                                    | Não consultado.                                                                      | Não implementado.                               |
| Finalização       | Fotográfico                 | **Pendente**: definir estado concluído/aprovado. | Não consultado.                                                                      | Não implementado.                               |
| Finalização       | Referências                 | **Pendente**.                                    | Não consultado.                                                                      | Não implementado.                               |
| Pós-produção      | Render aprovado             | **Pendente**.                                    | Só segue a função anterior.                                                          | Não implementado como requisito.                |
| Alteração         | Comentários consolidados    | **Pendente**.                                    | Função 6 é liberada sempre, salvo HOLD de imagem.                                    | Diverge da proposta.                            |
| Planta Humanizada | Subtipo e arquivos técnicos | **Pendente**.                                    | Há tratamento de subtipo, não de arquivos.                                           | Parcial.                                        |
| Planta Humanizada | Produção final do subtipo   | **Inferida:** regra legada correlata.            | Exige Composição concluída em outras imagens do mesmo subtipo, em casos humanizados. | Parcial e específica.                           |
| Animação          | Imagens-base aprovadas      | **Pendente**.                                    | `funcao_animacao` retorna `liberada=true`.                                           | Não implementado.                               |

## Regras confirmadas no cálculo legado

Estas regras descrevem o sistema atual e não substituem a matriz proposta.

1. A ordem fixa é Caderno, Filtro de assets, Modelagem, Composição,
   Pré-Finalização, Finalização, Pós-produção, Alteração e Planta Humanizada.
2. Imagem com `nome_status = HOLD` ou `substatus_id = 7` bloqueia todas as
   funções da imagem no PHP e no drag-and-drop do Kanban.
3. Alteração é liberada sempre, salvo HOLD de imagem.
4. `obra.liberar_modelagem=1` libera Modelagem e altera a regra de Composição.
5. A primeira função existente da imagem é liberada, mesmo se for uma etapa
   avançada do fluxo.
6. A predecessora existente libera a atual se estiver `Finalizado`, `Aprovado`
   ou `Aprovado com ajustes`.
7. O colaborador sentinela ID 15/nome “Não se aplica” também libera a etapa
   seguinte; o código não testa o status `Não se aplica`.
8. Flow Block é exposto no JSON, mas não participa do cálculo de `liberada`.
9. Funções de animação são retornadas como liberadas.
10. O JavaScript libera visualmente Filtro de assets mesmo quando o PHP retorna
    `liberada=false`.

## Regras inferidas do código atual

- **Inferida:** `liberada` é uma elegibilidade visual de sequência, não uma
  certificação de requisitos completos.
- **Inferida:** ausência de função anterior é tratada como ausência de
  dependência, porque a primeira função existente é liberada.
- **Inferida:** o colaborador “Não se aplica” é usado como substituto de uma
  decisão de planejamento que deveria ser explícita.
- **Inferida:** a regra especial de Planta Humanizada tenta coordenar imagens
  por subtipo, mas não garante os arquivos finais previstos nesta matriz.
- **Inferida:** `requires_file_upload` é uma trava visual adicional e não faz
  parte do cálculo de `liberada`.

## Decisões de negócio necessárias

1. Definir requisitos obrigatórios por função, tipo de imagem e subtipo.
2. Definir a evidência verificável de cada requisito: campo, arquivo, status,
   aprovação ou conclusão de módulo.
3. Definir se Kickoff bloqueia, alerta ou pode ser dispensado por obra.
4. Definir o comportamento quando não existe predecessora planejada.
5. Definir o modelo de dispensa de requisito, substituindo o sentinela “Não se
   aplica”.
6. Definir estados válidos de Fotográfico, Referências e Render para fins de
   liberação.
7. Definir requisitos de Pré-Finalização e regras completas de Animação.
8. Definir como HOLD de imagem, HOLD de tarefa e Flow Block coexistem.
9. Definir se Flow Block pode existir antes do início, mantendo a tarefa em
   `Não iniciado`.
10. Definir quais requisitos bloqueiam início, continuidade ou são apenas
    informativos.
