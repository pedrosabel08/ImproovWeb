# Flow Block

## Objetivo

Flow Block representa um impedimento real de execução de uma tarefa.

## Conceitos

- Não substitui Pendências Operacionais.
- Não substitui requisitos.
- Registra impacto operacional.

## Estados

- ABERTA
- AGUARDANDO_ACAO
- PAUSADA
- RESOLVIDA
- CANCELADA

## Fluxo

1. Colaborador identifica impedimento.
2. Registra Flow Block.
3. Sistema mantém a tarefa bloqueada.
4. Responsável resolve a causa.
5. Colaborador confirma.
6. Replaneja.
7. Continua a tarefa.

## Integração

Origens possíveis:

- Pendência Operacional (Projeto)
- Requisito de Produção (etapa anterior)

## Regras

- Tarefas em Não iniciado também podem possuir Flow Block.
- Se ainda não iniciou, permanece em Não iniciado bloqueada.
- Se já iniciou, permanece em HOLD até replanejamento.

## Responsabilidade

O Flow Block nunca resolve a causa do problema; apenas registra que ela está impedindo a execução.
