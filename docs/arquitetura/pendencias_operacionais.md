# Pendências Operacionais

## Objetivo

Pendências Operacionais representam obrigações do processo. Cada pendência possui responsável, SLA e ciclo de vida próprios.

## Princípios

- Cada módulo continua sendo dono de suas pendências.
- O painel central é apenas um agregador.
- Não existe uma tabela universal obrigatória.
- O agregador deve ser somente leitura.

## Contrato canônico

| Campo       |
| ----------- |
| origem      |
| origem_id   |
| tipo        |
| responsável |
| status      |
| SLA         |
| prazo       |
| URL de ação |

## Ciclo

1. Módulo cria a pendência.
2. Agregador a exibe.
3. Responsável resolve no módulo de origem.
4. Agregador deixa de exibi-la.

## Relação com Requisitos

As pendências atendem requisitos do tipo **Projeto** definidos em `mapa_requisitos_funcoes.md`.

## Relação com Flow Block

Uma pendência pode originar um Flow Block quando um colaborador não consegue executar sua tarefa devido a ela.
