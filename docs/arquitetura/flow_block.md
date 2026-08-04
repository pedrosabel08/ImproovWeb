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

- Tarefas em qualquer status podem possuir Flow Block.
- Ao registrar uma Issue bloqueante, a tarefa passa para HOLD, exceto quando já estiver nesse status.
- A tarefa permanece em HOLD até que o bloqueio seja resolvido, confirmado e replanejado.

## Responsabilidade

O Flow Block nunca resolve a causa do problema; apenas registra que ela está impedindo a execução.
