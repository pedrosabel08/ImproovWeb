# Medição de mutações fotográficas

Cada chamada da API e do upload emite uma linha `[FOTO-PERF]` com `request_id`, ação, plano, quantidade de queries, tamanho de request/response e os tempos por etapa. Em desenvolvimento, os mesmos tempos também são expostos em `Server-Timing`.

Exemplo:

```text
[FOTO-PERF] request_id=... action=pin_update plan=1 queries=...
request_bytes=... response_bytes=... begin=...ms lock=...ms mutation=...ms
commit=...ms readiness=...ms redis=...ms response=...ms total=...ms
```

## Comparativo a registrar

Para a mesma operação e o mesmo plano, compare uma linha anterior e uma posterior por:

| Métrica          | Antes | Depois |
| ---------------- | ----: | -----: |
| `queries`        |       |        |
| `response_bytes` |       |        |
| `total`          |       |        |
| `lock`           |       |        |
| `redis`          |       |        |

O baseline estrutural anterior reconstruía `foto_get_detail()` depois de cada mutação. Nesta fase, `pin_create`, `pin_update` e `pin_delete` retornam um delta. Para um `pin_move` identificado pelo servidor como alteração exclusiva de coordenadas, a API não regrava capturas, vínculos nem roda a sincronização completa de prontidão.

Não compare chamadas `get`: elas continuam carregando o detalhe completo e servem apenas como referência do custo de abertura/atualização manual do plano.
