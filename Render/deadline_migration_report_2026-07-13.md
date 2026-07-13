# Diagnóstico pré-migration — 2026-07-13

Execução somente de leitura:

```text
deadline_migration_report.py --check-deadline
```

Nenhuma migration foi aplicada e nenhum `DeleteJob` foi executado.

## Banco

- MySQL: `8.0.46`;
- renders em `render_alta`: 2.151;
- renders sem `imagem_id` ou `status_id`: 15, que serão ignorados pelo backfill
  e permanecerão no relatório para decisão manual;
- renders com `deadline_job_id`: 40;
- IDs inválidos: 0;
- IDs duplicados: 0;
- tabelas novas: ainda não instaladas.

## Deadline

- jobs retornados por `-GetJobs True`: 21;
- vínculos do Flow ainda existentes no Deadline e coerentes: 4;
- vínculos do Flow cujo ID não aparece mais no Deadline: 36;
- jobs terminais do Flow ainda existentes no Deadline: 0.

Vínculos ativos e coerentes:

| render_id | status | deadline_job_id |
|---:|---|---|
| 356031 | Erro | `6a54d4de9e0cdf6f9c92ecf9` |
| 356239 | Em aprovação | `6a51724c4f0dfc3607a0de9c` |
| 356246 | Em andamento | `6a54e0d37d34a7796d689e3d` |
| 356255 | Em andamento | `6a54e5283964469f2cd7e65e` |

Entre os 36 IDs ausentes:

- 35 pertencem a renders `Aprovado`;
- 1 pertence ao render 354799, em `Em aprovação`.

## Decisão conservadora incorporada

- O backfill copia os 40 IDs para o histórico.
- Nenhum dos 35 aprovados gera exclusão automática.
- O vínculo ausente do render 354799 será preservado e marcado como
  inconsistência pela reconciliação após confirmação explícita de `not found`.
- Os 15 renders sem chaves não recebem tentativa automaticamente.
- O relatório detalhado continua disponível executando o script; ele inclui
  render, imagem, status, Job ID, submissão, existência, categoria e ação
  sugerida para cada vínculo.
