# Pagamento / Adendos — FASE 0

Caracterização do estado capturado em **02/10/2026, 10:54:06, America/Sao_Paulo**. Este relatório não aprova regras de negócio nem inicia a FASE 1.

## 1. Resumo executivo

Foi construída uma base de referência com **18 casos nomeados**, cobrindo os 17 cenários solicitados, **14 combinações colaborador/competência**, 380 itens do controller e 381 itens elegíveis V2 antes da exclusão de parcial. A captura auditou **254 operações SELECT/SHOW**, incluindo preparações cujo bind falhou; o SQL está registrado no snapshot. Há exemplos reais de tarefa normal, parcial, pago integral, comissão, animação, acompanhamento, fixo positivo/zero, divergência e pagamentos entre meses. Os cenários de filtros, bônus manual e regeneração foram caracterizados por código, vinculados aos registros encontrados.

Os caminhos A e B não são equivalentes. Para Marcio (8), setembro/2026, ambos consultam os mesmos 33 IDs, porém os serviços documentais somam **R$ 9.120,00 em A** e **R$ 11.100,00 em B**, sob a projeção da aba A pagar. O caminho B usa o valor bruto da tarefa para comissões que A calcula em R$ 80/100. Em outros colaboradores, B falha no bind de parâmetros; esse erro foi preservado.

O saldo V2 desconta o ledger de todas as competências do beneficiário, separando comissão de remuneração da tarefa. A seleção documental usa nomes, datas e contadores recebidos do cliente, sem recalcular esse saldo. Filtros e aba ativa alteram o conjunto enviado. O coletor ainda usa posições incompatíveis com a tabela Pagos e perde a data de pagamento em A pagar.

O valor fixo cadastrado participa do resumo, mas a geração pede um novo valor manual. O colaborador 1 recebe R$ 4.000,00 em extras por código, substituindo extras manuais, além do fixo manual e das linhas incluídas. O payload persistido não conserva os itens ou bônus, impedindo a reconstrução integral das gerações antigas.

**Limites da evidência:** A tentativa no navegador interno falhou em ambas as URLs oficiais (`https://improov/ImproovWeb/` e `http://localhost:8066/ImproovWeb/`) com `Browser is not available: iab`, antes do login. Não houve navegação autenticada, teste de responsividade ou observação do DOM vivo. “Tela”, “JS” e “PDF” abaixo significam, respectivamente, resposta financeira executada isoladamente, projeção estática do coletor e linhas calculadas por métodos puros; nenhum PDF foi gerado. Totais históricos persistidos são separados do replay atual.

Não foram alterados arquivos de produção, regras, endpoints, JS, tabelas, templates ou dados. Não houve geração, confirmação, envio, regeneração ou lançamento financeiro. As alterações preexistentes do workspace foram preservadas.

Artefatos auxiliares:

- [Snapshot congelado](../tests/characterization/pagamento_adendos_golden.php): JSON depois de `__halt_compiler()`, protegido contra execução HTTP; contém registros, ledger, logs, payloads projetados, linhas, totais condicionais, comparações, hashes e SQL auditado.
- [Diagnóstico CLI](../tests/characterization/pagamento_adendos.php): captura somente leitura e replay offline, sem chamar os endpoints.
- Este relatório: interpretação humana e limites para uso futuro.

Categorias utilizadas: **COMPORTAMENTO ATUAL CONFIRMADO**, **REGRA DE NEGÓCIO APARENTE**, **POSSÍVEL BUG**, **DÍVIDA TÉCNICA**, **REGRA NÃO DETERMINADA**. Uma classificação descreve a evidência; não concede autorização para corrigir.

## 2. Casos de referência

Valores em reais. “Documental” é o valor da linha calculada, condicionado ao payload projetado, sem renderização PDF.

| Cenário / CASE_ID                    | Registro real / competência                                                | Original → pago → saldo                                     | Resultado atual                                                                                           | Classificação principal        |
| ------------------------------------ | -------------------------------------------------------------------------- | ----------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- | ------------------------------ |
| 1 — CASE_NORMAL_001                  | João Vitor (27), FI 120437, 2026-09                                        | 50 → 0 → 50                                                 | A pagar; JS/backend/documental 50                                                                         | COMPORTAMENTO ATUAL CONFIRMADO |
| 2 — CASE_PARCIAL_001                 | André Moreira (20), FI 102804, 2025-11                                     | 250 → 125 → 125 matemático                                  | Elegível V2 com parcial=1; excluído do controller; sem payload                                            | COMPORTAMENTO ATUAL CONFIRMADO |
| 3 — CASE_PAGO_001                    | Bruna (6), FI 110526, 2026-09                                              | 380 → 380 → 0                                               | A pagar pelo caso especial; linha documental zero incluída                                                | POSSÍVEL BUG                   |
| 4 — CASE_COMISSAO_001                | Marcio (8), FI 120172 de Heverton (40), 2026-09                            | Tarefa 300; comissão 80 → 0 → 80                            | A inclui 80; B inclui 300                                                                                 | REGRA DE NEGÓCIO APARENTE      |
| 4 adicional — CASE_COMISSAO_PAGA_001 | Marcio (8), FI 118830, 2026-08                                             | Comissão 80 → 80 → 0                                        | Ledger separado do pagamento de 300 a Heverton; Pagos projeta linha zero                                  | COMPORTAMENTO ATUAL CONFIRMADO |
| 5 — CASE_ANIMACAO_001                | André Tavares (13), FA 534 / animação 633, 2026-09                         | 100 → 100 → 0                                               | Data da animação em setembro; prazo em agosto; nomes degradados e payload Pagos zero                      | POSSÍVEL BUG                   |
| 6 — CASE_ACOMPANHAMENTO_001          | Nicolle (1), AC 617, 2025-01                                               | 10 → ledger 0 → 10                                          | Origem marcada paga; A inclui 10, B exclui por data                                                       | POSSÍVEL BUG                   |
| 7 — CASE_FIXO_001                    | Anderson (7), adendo 133, 2026-08                                          | Cadastro 4.600; payload fixo 4.600                          | Resumo pendente 4.600 mesmo com status pago; modal manual                                                 | REGRA NÃO DETERMINADA          |
| 8 — CASE_FIXO_ZERO_001               | Mariana (4), adendo 141, 2026-08                                           | Cadastro 0; payload fixo 0                                  | Resumo pendente 0; total histórico 650; NULL sem exemplar                                                 | COMPORTAMENTO ATUAL CONFIRMADO |
| 9 — CASE_BONUS_001                   | Nicolle (1), adendo 145, 2026-08                                           | Cadastro 3.000; fixo histórico 4.000; total histórico 8.000 | Extra Acompanhamento 4.000 aplicado por código; bônus manual não recuperável                              | REGRA DE NEGÓCIO APARENTE      |
| 10 — CASE_DIVERGENCIA_001            | José Robson (33), FI 110107, 2026-08                                       | 150 → 275 → 0; excesso 125                                  | Flag V2 verdadeira e legada falsa; Pagos projeta zero                                                     | POSSÍVEL BUG                   |
| 11 — CASE_LOG_001                    | José Robson (33), FI 120385, 2026-09                                       | 300 → 0 → 300                                               | Prazo outubro; entra por logs elegíveis de setembro                                                       | COMPORTAMENTO ATUAL CONFIRMADO |
| 12 — CASE_FILTROS_001                | João Vitor (27), 2026-09, 42 itens consultados                             | Depende das linhas visíveis                                 | Busca/função/divergências/aba participam; obra indisponível; marcação do checkbox não seleciona documento | DÍVIDA TÉCNICA                 |
| 13 — CASE_ABA_A_PAGAR_001            | Nicolle (1), 2025-01, AC 617                                               | 10 documental                                               | Coletor usa coluna Ações como data; perde data 2025-02-07                                                 | POSSÍVEL BUG                   |
| 14 — CASE_ABA_PAGOS_001              | André Tavares (13), 2026-09, FA 534                                        | 0 documental                                                | Imagem=função; função=moeda; valor=0; data=Detalhes                                                       | POSSÍVEL BUG                   |
| 15 — CASE_LISTA_VAZIA_001            | João Vitor (27), 2026-09                                                   | Sem total válido por B                                      | `itens=[]` aciona B; ArgumentCountError confirmado em leitura                                             | POSSÍVEL BUG                   |
| 16 — CASE_ENTRE_MESES_001            | Heverton (40), FI 111524, 2026-04                                          | 300 → 125 março + 150 abril → 25                            | A mostra saldo 25; buildRows exclui por completa_count=1                                                  | POSSÍVEL BUG                   |
| 17 — CASE_REGENERACAO_001            | Marcio (8), adendo 140 gerado, 2026-08; adendo 145 assinado como contraste | Histórico 7.160 separado do saldo atual                     | Gerado/visualizado/enviado permitem; assinado/recusado/expirado bloqueiam por código                      | REGRA NÃO DETERMINADA          |

FI = `funcao_imagem.idfuncao_imagem`; FA = `funcao_animacao.id`; AC = `acompanhamento.idacompanhamento`.

Ausências comprovadas na captura: nenhum `colaborador.valor_fixo IS NULL`; nenhum `funcao_animacao.valor=175`; nenhum adendo com status enviado, recusado ou expirado. Existem 32 gerados, 4 visualizados e 114 assinados. Não foi encontrado payload estruturado de bônus manual que permita reconstrução segura; isso não prova que bônus nunca tenha sido usado. O filtro de obra não está implementado na UI analisada.

## 3. Golden master detalhada

A fonte exata é o snapshot: `reference_cases[CASE_ID]` e `pairs['colaborador/AAAA-MM']`. Cada par conserva **todos os itens**, não apenas a amostra, em `screen.funcoes`, `eligible_v2`, `items`, `documents`, `fallback`, `path_comparison` e `existing_adendo`. `query_audit.sql` conserva o SQL auditado, incluindo preparações cujo bind falhou.

O valor no documento é observado pela execução real de `normalizeItensInput()` e `buildRows()` sem instanciar dependências de PDF ou persistência. “Não inclui” não significa que o gerador completo tenha sido executado. Para pares com payload vazio, a geração real cairia em B; o resultado puro vazio não é o resultado final desse endpoint. Para status terminal, a geração real seria bloqueada antes de selecionar itens.

**Convenção de total:** `serviços + F + E`, em que F é o fixo digitado no modal e E são extras manuais não observados. Para ID 1, E é substituído por 4.000. F extraído de adendo antigo é apenas uma hipótese explícita do replay, não uma observação do modal atual. `null` representa input desconhecido, nunca zero financeiro presumido. O total histórico armazenado não é recalculado usando o ledger atual.

### CASE_NORMAL_001

Tarefa positiva sem ledger ou divergência. **Classificação: COMPORTAMENTO ATUAL CONFIRMADO.**

- Colaborador/beneficiário: **João Vitor (27)**; competência **2026-09**.
- Entidade: **funcao_imagem:120437**; colaborador na origem: 27; imagem: **13.COR_MAX Living do apartamento tipo 1 do pavimento com 1 apartamento por andar - angulo 1**.
- Função na origem: Modelagem (funcao_id=2); status: Finalizado; prazo/data: 2026-09-30.
- Elegibilidade: `{"current_status_and_deadline":true,"eligible_log_ids":[38256]}`; retorno V2: SIM, parcial=0.
- Valor bruto da origem: **R$ 50,00**; base para este beneficiário: **R$ 50,00**; ledger correspondente: **R$ 0,00**; saldo matemático: **R$ 50,00**.
- Resposta atual da tela: SIM; função **Modelagem**, imagem **13.COR_MAX Living do apartamento tipo 1 do pavimento com 1 apartamento por andar - angulo 1**, valor_exibido **R$ 50,00**, pagamento=0; aba projetada **a_pagar**.
- Flags de divergência: legada=false; V2=false.
- Payload JS projetado: `{"imagem_nome":"13.COR_MAX Living do apartamento tipo 1 do pavimento com 1 apartamento por andar - angulo 1","nome_funcao":"Modelagem","valor":50.0,"data_pagamento":null,"pago_parcial_count":0,"pago_completa_count":0}`.
- Item aceito pela normalização: `{"imagem_nome":"13.COR_MAX Living do apartamento tipo 1 do pavimento com 1 apartamento por andar - angulo 1","nome_funcao":"Modelagem","valor":50.0,"data_pagamento":null,"pago_parcial_count":0,"pago_completa_count":0,"colaborador_ref":null,"from_payload":true}`.
- Incluído pelo buildRows: **SIM**; linha que alimentaria o PDF: `{"no":1,"imagem":"13.COR_MAX Living do apartamento tipo 1 do pavimento com 1 apartamento por andar - angulo 1","funcao":"Modelagem","valor":"50,00","valor_num":50.0}`. PDF não gerado.
- Competência inteira, aba A pagar: **41 linhas / serviços R$ 2.050,00**; fixo input condicional **Não observado**; extras do replay **R$ 0,00**. Extras manuais não observados.
- Total condicional do par: **Não observado**; fórmula R$ 2.050,00 + F, usando somente o extra previsto no replay. Gate por status: `allowed_by_existing_status`. Lista vazia acionaria fallback: NÃO.

Não há linhas no ledger para esta origem/ID na captura.

Evidências: `reference_cases["CASE_NORMAL_001"]`, `pairs["27/2026-09"]`, consulta parametrizada por origem/ID em `query_audit.sql`; `Pagamento/financeiro_v2.php:7–29`, `Pagamento/getColaborador.php:737–771`, `Pagamento/script.js:1788–1848,2786–2869`, `Contratos/services/AdendoLocalService.php:216–270`.

### CASE_PARCIAL_001

Parcial real; saldo matemático 125, mas elegibilidade marca parcial e tela remove. **Classificação: COMPORTAMENTO ATUAL CONFIRMADO.**

- Colaborador/beneficiário: **André Moreira (20)**; competência **2025-11**.
- Entidade: **funcao_imagem:102804**; colaborador na origem: 20; imagem: **11.MEN_991 Sala de jogos - Lazer**.
- Função na origem: Finalização (funcao_id=4); status: Finalizado; prazo/data: 2025-11-07.
- Elegibilidade: `{"current_status_and_deadline":true,"eligible_log_ids":[16834,17591,17667]}`; retorno V2: SIM, parcial=1.
- Valor bruto da origem: **R$ 250,00**; base para este beneficiário: **R$ 250,00**; ledger correspondente: **R$ 125,00**; saldo matemático: **R$ 125,00**.
- Resposta atual da tela: NÃO; não há valor exibido ou payload atual para esta entidade.
- Flags de divergência: legada=não exibida; V2=não exibida.
- Payload JS projetado: `null`.
- Item aceito pela normalização: `null`.
- Incluído pelo buildRows: **não recebe item pelo caminho A**; linha que alimentaria o PDF: `null`. PDF não gerado.
- Competência inteira, aba A pagar: **8 linhas / serviços R$ 800,00**; fixo input condicional **Não observado**; extras do replay **R$ 0,00**. Extras manuais não observados.
- Total condicional do par: **Não observado**; fórmula R$ 800,00 + F, usando somente o extra previsto no replay. Gate por status: `allowed_by_existing_status`. Lista vazia acionaria fallback: NÃO.

Ledger de **todos os beneficiários desta entidade** (saldo acima filtra beneficiário/classe):

| pagamento_item / pagamento | Beneficiário | Competência |     Valor | Observação          | Criado em           |
| -------------------------- | -----------: | ----------- | --------: | ------------------- | ------------------- |
| 724 / 39                   |           20 | 2025-11     | R$ 125,00 | Finalização Parcial | 2025-12-08 08:50:11 |

Evidências: `reference_cases["CASE_PARCIAL_001"]`, `pairs["20/2025-11"]`, consulta parametrizada por origem/ID em `query_audit.sql`; `Pagamento/financeiro_v2.php:7–29`, `Pagamento/getColaborador.php:737–771`, `Pagamento/script.js:1788–1848,2786–2869`, `Contratos/services/AdendoLocalService.php:216–270`.

O retorno elegível contém parcial=1; o controller o remove antes de enviar ao JS. Os R$ 125,00 são saldo matemático de referência, não valor efetivamente mostrado. buildRows também rejeitaria uma função contendo “parcial”, mas não recebeu esta tarefa em A.

### CASE_PAGO_001

Ledger cobre valor; routing especial conserva item na aba A pagar com saldo zero. **Classificação: POSSÍVEL BUG.**

- Colaborador/beneficiário: **Bruna (6)**; competência **2026-09**.
- Entidade: **funcao_imagem:110526**; colaborador na origem: 6; imagem: **53.GES_CHA Suite master angulo com foco no closet**.
- Função na origem: Finalização (funcao_id=4); status: Finalizado; prazo/data: 2026-09-30.
- Elegibilidade: `{"current_status_and_deadline":true,"eligible_log_ids":[37084,37258,37945,37949,37963,37992,38185]}`; retorno V2: SIM, parcial=0.
- Valor bruto da origem: **R$ 380,00**; base para este beneficiário: **R$ 380,00**; ledger correspondente: **R$ 380,00**; saldo matemático: **R$ 0,00**.
- Resposta atual da tela: SIM; função **Finalização Completa**, imagem **53.GES_CHA Suite master angulo com foco no closet**, valor_exibido **R$ 0,00**, pagamento=1; aba projetada **a_pagar**.
- Flags de divergência: legada=false; V2=false.
- Payload JS projetado: `{"imagem_nome":"53.GES_CHA Suite master angulo com foco no closet","nome_funcao":"Finalização Completa","valor":0.0,"data_pagamento":null,"pago_parcial_count":1,"pago_completa_count":0}`.
- Item aceito pela normalização: `{"imagem_nome":"53.GES_CHA Suite master angulo com foco no closet","nome_funcao":"Finalização Completa","valor":0.0,"data_pagamento":null,"pago_parcial_count":1,"pago_completa_count":0,"colaborador_ref":null,"from_payload":true}`.
- Incluído pelo buildRows: **SIM**; linha que alimentaria o PDF: `{"no":1,"imagem":"53.GES_CHA Suite master angulo com foco no closet","funcao":"Finalização completa com pagamento final","valor":"-","valor_num":0.0}`. PDF não gerado.
- Competência inteira, aba A pagar: **30 linhas / serviços R$ 4.430,00**; fixo input condicional **Não observado**; extras do replay **R$ 0,00**. Extras manuais não observados.
- Total condicional do par: **Não observado**; fórmula R$ 4.430,00 + F, usando somente o extra previsto no replay. Gate por status: `allowed_by_existing_status`. Lista vazia acionaria fallback: NÃO.

Ledger de **todos os beneficiários desta entidade** (saldo acima filtra beneficiário/classe):

| pagamento_item / pagamento | Beneficiário | Competência |     Valor | Observação          | Criado em           |
| -------------------------- | -----------: | ----------- | --------: | ------------------- | ------------------- |
| 2148 / 108                 |            6 | 2026-03     | R$ 380,00 | Finalização Parcial | 2026-04-08 10:45:09 |

Evidências: `reference_cases["CASE_PAGO_001"]`, `pairs["6/2026-09"]`, consulta parametrizada por origem/ID em `query_audit.sql`; `Pagamento/financeiro_v2.php:7–29`, `Pagamento/getColaborador.php:737–771`, `Pagamento/script.js:1788–1848,2786–2869`, `Contratos/services/AdendoLocalService.php:216–270`.

### CASE_COMISSAO_001

Beneficiário 8, tarefa 40, valor registrado 300 e comissão 80. **Classificação: REGRA DE NEGÓCIO APARENTE.**

- Colaborador/beneficiário: **Marcio (8)**; competência **2026-09**.
- Entidade: **funcao_imagem:120172**; colaborador na origem: 40; imagem: **21.RAY_DOM Piscinas angulo 2**.
- Função na origem: Finalização (funcao_id=4); status: Aprovado com ajustes; prazo/data: 2026-09-24.
- Elegibilidade: `{"current_status_and_deadline":true,"eligible_log_ids":[37612,37637]}`; retorno V2: SIM, parcial=0.
- Valor bruto da origem: **R$ 300,00**; base para este beneficiário: **R$ 80,00**; ledger correspondente: **R$ 0,00**; saldo matemático: **R$ 80,00**.
- Resposta atual da tela: SIM; função **Finalização Completa - Heverton**, imagem **21.RAY_DOM Piscinas angulo 2**, valor_exibido **R$ 80,00**, pagamento=0; aba projetada **a_pagar**.
- Flags de divergência: legada=false; V2=false.
- Payload JS projetado: `{"imagem_nome":"21.RAY_DOM Piscinas angulo 2","nome_funcao":"Finalização Completa - Heverton","valor":80.0,"data_pagamento":null,"pago_parcial_count":0,"pago_completa_count":0}`.
- Item aceito pela normalização: `{"imagem_nome":"21.RAY_DOM Piscinas angulo 2","nome_funcao":"Finalização Completa - Heverton","valor":80.0,"data_pagamento":null,"pago_parcial_count":0,"pago_completa_count":0,"colaborador_ref":null,"from_payload":true}`.
- Incluído pelo buildRows: **SIM**; linha que alimentaria o PDF: `{"no":1,"imagem":"21.RAY_DOM Piscinas angulo 2","funcao":"Finalização Completa - Heverton","valor":"80,00","valor_num":80.0}`. PDF não gerado.
- Competência inteira, aba A pagar: **31 linhas / serviços R$ 9.120,00**; fixo input condicional **Não observado**; extras do replay **R$ 0,00**. Extras manuais não observados.
- Total condicional do par: **Não observado**; fórmula R$ 9.120,00 + F, usando somente o extra previsto no replay. Gate por status: `allowed_by_existing_status`. Lista vazia acionaria fallback: NÃO.

Não há linhas no ledger para esta origem/ID na captura.

Evidências: `reference_cases["CASE_COMISSAO_001"]`, `pairs["8/2026-09"]`, consulta parametrizada por origem/ID em `query_audit.sql`; `Pagamento/financeiro_v2.php:7–29`, `Pagamento/getColaborador.php:737–771`, `Pagamento/script.js:1788–1848,2786–2869`, `Contratos/services/AdendoLocalService.php:216–270`.

### CASE_COMISSAO_PAGA_001

Comissão e remuneração da tarefa coexistem em beneficiários diferentes. **Classificação: COMPORTAMENTO ATUAL CONFIRMADO.**

- Colaborador/beneficiário: **Marcio (8)**; competência **2026-08**.
- Entidade: **funcao_imagem:118830**; colaborador na origem: 40; imagem: **10.MUS_AKE Area externa proximo da piscina (Frente da academia)**.
- Função na origem: Finalização (funcao_id=4); status: Finalizado; prazo/data: 2026-08-28.
- Elegibilidade: `{"current_status_and_deadline":true,"eligible_log_ids":[35591,35619]}`; retorno V2: SIM, parcial=0.
- Valor bruto da origem: **R$ 300,00**; base para este beneficiário: **R$ 80,00**; ledger correspondente: **R$ 80,00**; saldo matemático: **R$ 0,00**.
- Resposta atual da tela: SIM; função **Finalização Completa - Heverton**, imagem **10.MUS_AKE Area externa proximo da piscina (Frente da academia)**, valor_exibido **R$ 0,00**, pagamento=1; aba projetada **pagos**.
- Flags de divergência: legada=false; V2=false.
- Payload JS projetado: `{"imagem_nome":"Finalização Completa - Heverton Pago Completa","nome_funcao":"R$ 0,00","valor":0,"data_pagamento":"—","pago_parcial_count":0,"pago_completa_count":0}`.
- Item aceito pela normalização: `{"imagem_nome":"Finalização Completa - Heverton Pago Completa","nome_funcao":"R$ 0,00","valor":0.0,"data_pagamento":"—","pago_parcial_count":0,"pago_completa_count":0,"colaborador_ref":null,"from_payload":true}`.
- Incluído pelo buildRows: **SIM**; linha que alimentaria o PDF: `{"no":1,"imagem":"Finalização Completa - Heverton Pago Completa","funcao":"R$ 0,00","valor":"-","valor_num":0.0}`. PDF não gerado.
- Competência inteira, aba A pagar: **1 linhas / serviços R$ 0,00**; fixo input condicional **R$ 0,00**; extras do replay **R$ 0,00**. Extras manuais não observados.
- Total condicional do par: **R$ 0,00**; fórmula R$ 0,00 + F, usando somente o extra previsto no replay. Gate por status: `allowed_by_existing_status`. Lista vazia acionaria fallback: NÃO.

Ledger de **todos os beneficiários desta entidade** (saldo acima filtra beneficiário/classe):

| pagamento_item / pagamento | Beneficiário | Competência |     Valor | Observação      | Criado em           |
| -------------------------- | -----------: | ----------- | --------: | --------------- | ------------------- |
| 3859 / 199                 |           40 | 2026-08     | R$ 300,00 | NULL            | 2026-09-10 22:30:41 |
| 3962 / 204                 |            8 | 2026-08     |  R$ 80,00 | Comissão Gestor | 2026-09-10 22:31:14 |

Evidências: `reference_cases["CASE_COMISSAO_PAGA_001"]`, `pairs["8/2026-08"]`, consulta parametrizada por origem/ID em `query_audit.sql`; `Pagamento/financeiro_v2.php:7–29`, `Pagamento/getColaborador.php:737–771`, `Pagamento/script.js:1788–1848,2786–2869`, `Contratos/services/AdendoLocalService.php:216–270`.

### CASE_ANIMACAO_001

Data animação Setembro, prazo Agosto, ledger Agosto; saldo zero e nomes degradados no V2. **Classificação: POSSÍVEL BUG.**

- Colaborador/beneficiário: **André Tavares (13)**; competência **2026-09**.
- Entidade: **funcao_animacao:534**; colaborador na origem: 13; imagem: **10.GT_LAC Hall de entrada**.
- Função na origem: Pós-produção (funcao_id=5); status: Finalizado; prazo/data: 2026-08-31.
- Elegibilidade: `{"date_source":"animacao.data_anima","date":"2026-09-04","status":"Finalizado"}`; retorno V2: SIM, parcial=0.
- Valor bruto da origem: **R$ 100,00**; base para este beneficiário: **R$ 100,00**; ledger correspondente: **R$ 100,00**; saldo matemático: **R$ 0,00**.
- Resposta atual da tela: SIM; função **Animação**, imagem **Custo geral da obra**, valor_exibido **R$ 0,00**, pagamento=1; aba projetada **pagos**.
- Flags de divergência: legada=null; V2=false.
- Payload JS projetado: `{"imagem_nome":"Animação","nome_funcao":"R$ 0,00","valor":0,"data_pagamento":"—","pago_parcial_count":0,"pago_completa_count":0}`.
- Item aceito pela normalização: `{"imagem_nome":"Animação","nome_funcao":"R$ 0,00","valor":0.0,"data_pagamento":"—","pago_parcial_count":0,"pago_completa_count":0,"colaborador_ref":null,"from_payload":true}`.
- Incluído pelo buildRows: **SIM**; linha que alimentaria o PDF: `{"no":1,"imagem":"Animação","funcao":"R$ 0,00","valor":"-","valor_num":0.0}`. PDF não gerado.
- Competência inteira, aba A pagar: **0 linhas / serviços R$ 0,00**; fixo input condicional **Não observado**; extras do replay **R$ 0,00**. Extras manuais não observados.
- Total condicional do par: **Não observado**; fórmula R$ 0,00 + F, usando somente o extra previsto no replay. Gate por status: `allowed_by_existing_status`. Lista vazia acionaria fallback: SIM.

Ledger de **todos os beneficiários desta entidade** (saldo acima filtra beneficiário/classe):

| pagamento_item / pagamento | Beneficiário | Competência |     Valor | Observação | Criado em           |
| -------------------------- | -----------: | ----------- | --------: | ---------- | ------------------- |
| 3802 / 195                 |           13 | 2026-08     | R$ 100,00 | NULL       | 2026-09-10 22:30:19 |

Evidências: `reference_cases["CASE_ANIMACAO_001"]`, `pairs["13/2026-09"]`, consulta parametrizada por origem/ID em `query_audit.sql`; `Pagamento/financeiro_v2.php:7–29`, `Pagamento/getColaborador.php:737–771`, `Pagamento/script.js:1788–1848,2786–2869`, `Contratos/services/AdendoLocalService.php:216–270`.

A data_anima real é 2026-09-04 e o prazo da FA é 2026-08-31. A função real Pós-produção/valor 100 não sobrevive ao payload Pagos. A renomeação documental de rótulos Animação com 100/175 para Pós-Produção existe no código, mas este payload chega com valor 0 e função em moeda, portanto não a aciona. A ausência de 175 foi consultada somente em funcao_animacao.

### CASE_ACOMPANHAMENTO_001

Origem paga sem ledger; projeção A inclui valor e perde data; B exclui. **Classificação: POSSÍVEL BUG.**

- Colaborador/beneficiário: **Nicolle (1)**; competência **2025-01**.
- Entidade: **acompanhamento:617**; colaborador na origem: 1; imagem: **18.HAA_HOR Planta Humanizada do apartamento tipo 3 com 3 suites**.
- Função na origem: Acompanhamento (funcao_id=não possui); status: não possui coluna status; prazo/data: 2025-01-26 21:41:59.
- Elegibilidade: `{"date_source":"acompanhamento.data","date":"2025-01-26 21:41:59"}`; retorno V2: SIM, parcial=0.
- Valor bruto da origem: **R$ 10,00**; base para este beneficiário: **R$ 10,00**; ledger correspondente: **R$ 0,00**; saldo matemático: **R$ 10,00**.
- Resposta atual da tela: SIM; função **Acompanhamento**, imagem **18.HAA_HOR Planta Humanizada do apartamento tipo 3 com 3 suites**, valor_exibido **R$ 10,00**, pagamento=0; aba projetada **a_pagar**.
- Flags de divergência: legada=true; V2=false.
- Payload JS projetado: `{"imagem_nome":"18.HAA_HOR Planta Humanizada do apartamento tipo 3 com 3 suites","nome_funcao":"Acompanhamento","valor":10.0,"data_pagamento":null,"pago_parcial_count":0,"pago_completa_count":0}`.
- Item aceito pela normalização: `{"imagem_nome":"18.HAA_HOR Planta Humanizada do apartamento tipo 3 com 3 suites","nome_funcao":"Acompanhamento","valor":10.0,"data_pagamento":null,"pago_parcial_count":0,"pago_completa_count":0,"colaborador_ref":null,"from_payload":true}`.
- Incluído pelo buildRows: **SIM**; linha que alimentaria o PDF: `{"no":1,"imagem":"18.HAA_HOR Planta Humanizada do apartamento tipo 3 com 3 suites","funcao":"Acompanhamento","valor":"10,00","valor_num":10.0}`. PDF não gerado.
- Competência inteira, aba A pagar: **84 linhas / serviços R$ 2.415,00**; fixo input condicional **Não observado**; extras do replay **R$ 4.000,00**. Extras manuais não observados.
- Total condicional do par: **Não observado**; fórmula R$ 6.415,00 + F, usando somente o extra previsto no replay. Gate por status: `allowed_by_existing_status`. Lista vazia acionaria fallback: NÃO.

Não há linhas no ledger para esta origem/ID na captura.

Evidências: `reference_cases["CASE_ACOMPANHAMENTO_001"]`, `pairs["1/2025-01"]`, consulta parametrizada por origem/ID em `query_audit.sql`; `Pagamento/financeiro_v2.php:7–29`, `Pagamento/getColaborador.php:737–771`, `Pagamento/script.js:1788–1848,2786–2869`, `Contratos/services/AdendoLocalService.php:216–270`.

### CASE_DIVERGENCIA_001

Snapshot 150, pago 275, flag V2 true e legada false. **Classificação: POSSÍVEL BUG.**

- Colaborador/beneficiário: **José Robson (33)**; competência **2026-08**.
- Entidade: **funcao_imagem:110107**; colaborador na origem: 33; imagem: **12.WER_RIO Academia**.
- Função na origem: Finalização (funcao_id=4); status: Finalizado; prazo/data: 2026-08-03.
- Elegibilidade: `{"current_status_and_deadline":true,"eligible_log_ids":[33550,33572]}`; retorno V2: SIM, parcial=0.
- Valor bruto da origem: **R$ 150,00**; base para este beneficiário: **R$ 150,00**; ledger correspondente: **R$ 275,00**; saldo matemático: **R$ 0,00**.
- Resposta atual da tela: SIM; função **Finalização Completa**, imagem **12.WER_RIO Academia**, valor_exibido **R$ 0,00**, pagamento=1; aba projetada **pagos**.
- Flags de divergência: legada=false; V2=true.
- Payload JS projetado: `{"imagem_nome":"Finalização Completa Pago Completa","nome_funcao":"R$ 0,00","valor":0,"data_pagamento":"—","pago_parcial_count":0,"pago_completa_count":0}`.
- Item aceito pela normalização: `{"imagem_nome":"Finalização Completa Pago Completa","nome_funcao":"R$ 0,00","valor":0.0,"data_pagamento":"—","pago_parcial_count":0,"pago_completa_count":0,"colaborador_ref":null,"from_payload":true}`.
- Incluído pelo buildRows: **SIM**; linha que alimentaria o PDF: `{"no":1,"imagem":"Finalização Completa Pago Completa","funcao":"R$ 0,00","valor":"-","valor_num":0.0}`. PDF não gerado.
- Competência inteira, aba A pagar: **0 linhas / serviços R$ 0,00**; fixo input condicional **R$ 0,00**; extras do replay **R$ 0,00**. Extras manuais não observados.
- Total condicional do par: **R$ 0,00**; fórmula R$ 0,00 + F, usando somente o extra previsto no replay. Gate por status: `blocked_by_existing_status`. Lista vazia acionaria fallback: SIM.

Ledger de **todos os beneficiários desta entidade** (saldo acima filtra beneficiário/classe):

| pagamento_item / pagamento | Beneficiário | Competência |     Valor | Observação          | Criado em           |
| -------------------------- | -----------: | ----------- | --------: | ------------------- | ------------------- |
| 2342 / 118                 |           33 | 2026-03     | R$ 125,00 | Finalização Parcial | 2026-04-08 21:17:18 |
| 3315 / 169                 |           33 | 2026-06     | R$ 150,00 | Pago Completa       | 2026-07-06 11:05:27 |

Evidências: `reference_cases["CASE_DIVERGENCIA_001"]`, `pairs["33/2026-08"]`, consulta parametrizada por origem/ID em `query_audit.sql`; `Pagamento/financeiro_v2.php:7–29`, `Pagamento/getColaborador.php:737–771`, `Pagamento/script.js:1788–1848,2786–2869`, `Contratos/services/AdendoLocalService.php:216–270`.

### CASE_LOG_001

Prazo Outubro; Setembro entra pelo log elegível. **Classificação: COMPORTAMENTO ATUAL CONFIRMADO.**

- Colaborador/beneficiário: **José Robson (33)**; competência **2026-09**.
- Entidade: **funcao_imagem:120385**; colaborador na origem: 33; imagem: **13.RAY_DOM Sala de jogos**.
- Função na origem: Finalização (funcao_id=4); status: Ajuste; prazo/data: 2026-10-01.
- Elegibilidade: `{"current_status_and_deadline":false,"eligible_log_ids":[37775,38238]}`; retorno V2: SIM, parcial=0.
- Valor bruto da origem: **R$ 300,00**; base para este beneficiário: **R$ 300,00**; ledger correspondente: **R$ 0,00**; saldo matemático: **R$ 300,00**.
- Resposta atual da tela: SIM; função **Finalização Completa**, imagem **13.RAY_DOM Sala de jogos**, valor_exibido **R$ 300,00**, pagamento=0; aba projetada **a_pagar**.
- Flags de divergência: legada=false; V2=false.
- Payload JS projetado: `{"imagem_nome":"13.RAY_DOM Sala de jogos","nome_funcao":"Finalização Completa","valor":300.0,"data_pagamento":null,"pago_parcial_count":0,"pago_completa_count":0}`.
- Item aceito pela normalização: `{"imagem_nome":"13.RAY_DOM Sala de jogos","nome_funcao":"Finalização Completa","valor":300.0,"data_pagamento":null,"pago_parcial_count":0,"pago_completa_count":0,"colaborador_ref":null,"from_payload":true}`.
- Incluído pelo buildRows: **SIM**; linha que alimentaria o PDF: `{"no":1,"imagem":"13.RAY_DOM Sala de jogos","funcao":"Finalização Completa","valor":"300,00","valor_num":300.0}`. PDF não gerado.
- Competência inteira, aba A pagar: **17 linhas / serviços R$ 3.800,00**; fixo input condicional **Não observado**; extras do replay **R$ 0,00**. Extras manuais não observados.
- Total condicional do par: **Não observado**; fórmula R$ 3.800,00 + F, usando somente o extra previsto no replay. Gate por status: `allowed_by_existing_status`. Lista vazia acionaria fallback: NÃO.

Não há linhas no ledger para esta origem/ID na captura.

Evidências: `reference_cases["CASE_LOG_001"]`, `pairs["33/2026-09"]`, consulta parametrizada por origem/ID em `query_audit.sql`; `Pagamento/financeiro_v2.php:7–29`, `Pagamento/getColaborador.php:737–771`, `Pagamento/script.js:1788–1848,2786–2869`, `Contratos/services/AdendoLocalService.php:216–270`.

Os logs elegíveis 37775 (2026-09-28, Em aprovação) e 38238 (2026-09-30, Ajuste) permitem a entrada. Há log posterior 38274 de Em andamento em 2026-09-30 23:16; o predicado não exige que o último estado seja elegível.

### CASE_ENTRE_MESES_001

Parcial Março+complemento Abril deixam saldo 25, excluído por contador completa. **Classificação: POSSÍVEL BUG.**

- Colaborador/beneficiário: **Heverton (40)**; competência **2026-04**.
- Entidade: **funcao_imagem:111524**; colaborador na origem: 40; imagem: **2.CIB_OCE Salao de festas com vista fotografica real**.
- Função na origem: Finalização (funcao_id=4); status: Finalizado; prazo/data: 2026-04-02.
- Elegibilidade: `{"current_status_and_deadline":true,"eligible_log_ids":[24862,24979,25021,25040]}`; retorno V2: SIM, parcial=0.
- Valor bruto da origem: **R$ 300,00**; base para este beneficiário: **R$ 300,00**; ledger correspondente: **R$ 275,00**; saldo matemático: **R$ 25,00**.
- Resposta atual da tela: SIM; função **Finalização Completa**, imagem **2.CIB_OCE Salao de festas com vista fotografica real**, valor_exibido **R$ 25,00**, pagamento=0; aba projetada **a_pagar**.
- Flags de divergência: legada=false; V2=false.
- Payload JS projetado: `{"imagem_nome":"2.CIB_OCE Salao de festas com vista fotografica real","nome_funcao":"Finalização Completa","valor":25.0,"data_pagamento":null,"pago_parcial_count":1,"pago_completa_count":1}`.
- Item aceito pela normalização: `{"imagem_nome":"2.CIB_OCE Salao de festas com vista fotografica real","nome_funcao":"Finalização Completa","valor":25.0,"data_pagamento":null,"pago_parcial_count":1,"pago_completa_count":1,"colaborador_ref":null,"from_payload":true}`.
- Incluído pelo buildRows: **NÃO**; linha que alimentaria o PDF: `null`. PDF não gerado.
- Competência inteira, aba A pagar: **2 linhas / serviços R$ 100,00**; fixo input condicional **R$ 0,00**; extras do replay **R$ 0,00**. Extras manuais não observados.
- Total condicional do par: **R$ 100,00**; fórmula R$ 100,00 + F, usando somente o extra previsto no replay. Gate por status: `blocked_by_existing_status`. Lista vazia acionaria fallback: NÃO.

Ledger de **todos os beneficiários desta entidade** (saldo acima filtra beneficiário/classe):

| pagamento_item / pagamento | Beneficiário | Competência |     Valor | Observação          | Criado em           |
| -------------------------- | -----------: | ----------- | --------: | ------------------- | ------------------- |
| 2476 / 123                 |           40 | 2026-03     | R$ 125,00 | Finalização Parcial | 2026-04-08 22:29:34 |
| 2635 / 132                 |           40 | 2026-04     | R$ 150,00 | Pago Completa       | 2026-05-07 11:10:11 |
| 2830 / 141                 |            8 | 2026-04     |  R$ 80,00 | Comissão Gestor     | 2026-05-07 11:17:29 |

Evidências: `reference_cases["CASE_ENTRE_MESES_001"]`, `pairs["40/2026-04"]`, consulta parametrizada por origem/ID em `query_audit.sql`; `Pagamento/financeiro_v2.php:7–29`, `Pagamento/getColaborador.php:737–771`, `Pagamento/script.js:1788–1848,2786–2869`, `Contratos/services/AdendoLocalService.php:216–270`.

O lançamento de comissão 80 ao beneficiário 8 não reduz o saldo do beneficiário 40. O coletor envia 25 com parcial_count=1/completa_count=1; buildRows exige completa_count=0 e exclui a linha mesmo com saldo positivo.

### Casos de fixo e extra

**CASE_FIXO_001 / Anderson (7), 2026-08:** cadastro 4.600; resumo retorna `valor=4600`, `valor_fixo=4600`, `valor_mes=4600`, `status=pago`, pagamento 194. O resumo adiciona o fixo ao pendente mesmo quando existe pagamento. O modal pede valor obrigatório sem buscar/preencher o cadastro. Adendo 133 assinado conserva `VALOR_FIXO=4600` e `VALOR_TOTAL=4600`. Na projeção atual A pagar há 17 linhas zero, serviços 0; usando o fixo antigo como input, total condicional 4.600, mas o status assinado bloqueia geração. A igualdade histórica do fixo não prova sincronização automática.

**CASE_FIXO_ZERO_001 / Mariana (4), 2026-08:** cadastro 0; resumo `valor=0`, `valor_fixo=0`, `valor_mes=650`, `status=pago`, pagamento 205. Adendo 141 assinado tem fixo 0 e total 650. Zero é um número informado; campo vazio/cancelamento interrompe o modal. O retorno string `"0"` do input não é falsy e passa a validação; esse fluxo não foi executado no navegador. Backend converte ausente em 0, assim como NULL, sem conservar distinção. A coluna permite NULL, mas a captura encontrou zero registros NULL.

**CASE_BONUS_001 / Nicolle (1), 2026-08:** cadastro atual 3.000; resumo atual 3.000; adendo 145 assinado registra fixo 4.000 e total 8.000. Na competência não há itens consultados. O serviço substitui extras pelo array `[{categoria: 'Acompanhamento', valor: 4000}]` e soma o fixo manual. Usando o fixo persistido antigo, o replay resulta 8.000; geração real bloqueada pelo status assinado. Adendo 123 de julho/2026 registra fixo 0 e total 4.000. Essa evidência confirma totais armazenados e a regra presente no código; não prova qual decisão originou cada input histórico.

Bônus manual é criado pelo JS em um loop de categoria e valor, enviado como `extras:[{categoria,valor}]`. Categoria é trimada e exigida; valor é convertido por `parseFloat` após vírgula→ponto. O campo possui min=0, mas a validação customizada não impõe explicitamente limite positivo. `normalizeExtras()` aceita categoria não vazia e `is_numeric(valorRaw)`; strings com vírgula são descartadas apesar da conversão anterior, valores negativos numéricos não são rejeitados no serviço. O JS usual envia números JSON, portanto a falha com string de vírgula é uma condição de entrada alternativa, não comprovada por bônus real. Extras viram tabela HTML e entram na soma. O registro `adendos.payload_enviado` conserva apenas nome, competência, fixo e total; categoria, valor individual e itens não permanecem ali. Nenhum bônus real foi criado.

### Casos de interface e lista vazia

**CASE_FILTROS_001:** `applyVisualFilters()` em `Pagamento/script.js:339` modifica `row.style.display` usando busca no texto completo, comparação substring da função em minúsculas e `row.dataset.divergence === '1'`. Não normaliza acentos da busca/função. Busca não se limita ao nome da imagem. A aba esconde seu painel; `offsetParent !== null` exclui suas linhas do coletor. A tabela de divergências não está no seletor do adendo. O filtro de obra está indisponível. Selecionar checkboxes afeta pagamento, mas o coletor documental não exige `.checked`. `funcoes` é enviado separadamente e só é aplicado em B. As combinações de filtros não foram executadas em browser; a conclusão segue os seletores e handlers atuais.

**CASE_ABA_A_PAGAR_001 e CASE_ABA_PAGOS_001:** estrutura depois de `transformUnpaid()`/`transformPaid()`:

| Índice DOM | A pagar visual | Interpretação do coletor                    | Pagos visual  | Interpretação do coletor                          |
| ---------: | -------------- | ------------------------------------------- | ------------- | ------------------------------------------------- |
|          0 | Checkbox       | Usado para contadores, não para seleção     | Tarefa        | Ignorado como nome                                |
|          1 | Tarefa         | imagem_nome correto                         | Função        | Usado como imagem_nome                            |
|          2 | Função         | nome_funcao, limpa badges                   | Valor         | Usado como nome_funcao                            |
|          3 | Valor          | valor numérico correto                      | Tipo, “—”     | Parse vira valor 0                                |
|          4 | Situação       | Ignorado para data                          | Data          | Data ignorada                                     |
|          5 | Ações          | Usado como data_pagamento; texto vazio→null | Detalhes, “—” | Usado como data_pagamento; backend normaliza→null |

Pagos remove o checkbox na transformação; contadores projetados tornam-se 0. Para FA 534, o payload projetado é `imagem_nome='Animação', nome_funcao='R$ 0,00', valor=0, data_pagamento='—'`. `buildRows()` inclui uma linha zero. Para AC 617, A pagar envia 10 e data null, enquanto a origem real possui data de pagamento 2025-02-07; B conserva a data e exclui a linha. Antes da transformação visual, animação/acompanhamento renderizam valor bruto; a transformação usa `checkbox.dataset.valor` com saldo V2 e altera a célula final. A projeção registra as duas etapas, sem tratá-las como regra financeira legítima.

**CASE_LISTA_VAZIA_001:** `gerarAdendo()` escolhe A somente com `!empty($itensInput)`. Lista vazia, ausente ou não-array convertida pelo endpoint aciona B; filtros que escondam tudo e aba de divergências podem produzir essa condição. Para João Vitor/2026-09, execução isolada da consulta privada reproduziu `ArgumentCountError: The number of elements in the type definition string must match the number of bind variables`. A consulta é somente leitura e foi auditada; o gerador não foi chamado. Um array não vazio contendo elementos inválidos entra em A, pode normalizar para vazio e não retorna automaticamente a B. Linha placeholder visível de tabela também pode ser coletada como item vazio/zero; isso é análise estática, não observação de um clique real.

### Regeneração, sem executar

**CASE_REGENERACAO_001:** adendo 140, Marcio/2026-08, status gerado, fixo 0, total histórico 7.160. O serviço permite regeneração por esse status; replay atual de serviços zero não reconstrói os 7.160 da geração antiga. Adendo 145 assinado fornece o contraste real bloqueado.

| Status existente | Permite por código? | Registro disponível na captura?               |
| ---------------- | ------------------- | --------------------------------------------- |
| gerado           | Sim                 | Sim, 32; exemplo 140                          |
| enviado          | Sim                 | Não; caracterização estática                  |
| visualizado      | Sim                 | Sim, 4; comportamento de regeneração estático |
| assinado         | Não                 | Sim, 114; exemplo 145                         |
| recusado         | Não                 | Não; caracterização estática                  |
| expirado         | Não                 | Não; caracterização estática                  |

Se permitida, `salvarAdendo()` atualizaria **status para gerado, data_envio para agora, payload_enviado, arquivo_nome e arquivo_path**, mantendo id/beneficiário/competência. Outros campos que não aparecem no UPDATE, incluindo token, URL de assinatura e metadados de assinatura/visualização existentes, permanecem. Há risco de token/URL antigos continuarem ligados a documento substituído; intenção não determinada. O PDF é criado antes de salvar o registro; não há transação envolvendo arquivo e DB.

O endpoint chama o gerador e já persiste `adendos` **antes** da confirmação humana; depois salva uma pendência única em sessão. `confirmar_adendo.php` apenas move o arquivo e limpa a pendência, sem atualizar arquivo_path no DB ou registrar a confirmação. `ContratoPdfService` evita colisões com sufixo, mas o serviço registra o nome solicitado, não `file_name` retornado. Esses comportamentos foram lidos, nunca executados.

## 4. Identidade dos itens

Estrutura real conferida em `information_schema.COLUMNS`, queries V2 e joins do helper de Custos. Origem é literal persistido, não um nome conceitual inventado.

| Tipo                  | Origem real                                    | Tabela / PK                                           | Campo de valor                 | Colaborador                                                              | Competência/data                                          | Status                                                     |
| --------------------- | ---------------------------------------------- | ----------------------------------------------------- | ------------------------------ | ------------------------------------------------------------------------ | --------------------------------------------------------- | ---------------------------------------------------------- |
| Tarefa de imagem      | funcao_imagem                                  | funcao_imagem / idfuncao_imagem                       | valor                          | colaborador_id                                                           | prazo ou log_alteracoes.data elegível                     | status / status_novo do log                                |
| Comissão do gestor    | funcao_imagem                                  | Mesma PK da tarefa de origem                          | Derivado: 80/100; ledger valor | Origem em fi.colaborador_id; beneficiário em pagamentos.colaborador_id=8 | Mesma elegibilidade da tarefa                             | Mesmo status da tarefa; tipo de lançamento separa comissão |
| Tarefa de animação    | funcao_animacao                                | funcao_animacao / id                                  | valor                          | colaborador_id                                                           | animacao.data_anima por animacao_id                       | funcao_animacao.status                                     |
| Animação legada       | animacao                                       | animacao / idanimacao                                 | valor                          | colaborador_id                                                           | data_anima                                                | substatus_id; não presumir equivalente a fa.status         |
| Acompanhamento        | acompanhamento                                 | acompanhamento / idacompanhamento                     | valor                          | colaborador_id                                                           | data                                                      | Sem coluna status; possui pagamento/data_pagamento         |
| Lançamento financeiro | origem + origem_id                             | pagamento_itens / idpagamento_item                    | valor decimal(12,2)            | pagamentos.colaborador_id via pagamento_id                               | pagamentos.mes_ref; criado_em é data de criação diferente | pagamentos.status                                          |
| Fixo cadastrado       | Não é origem de pagamento_itens neste fluxo    | colaborador / idcolaborador                           | valor_fixo decimal(10,2)       | Própria PK                                                               | Sem vigência/histórico nesse campo                        | Sem status financeiro próprio                              |
| Extra manual          | Sem identidade estruturada persistida no fluxo | Array de entrada; payload final não conserva rubricas | valor                          | Beneficiário do adendo                                                   | Competência do adendo                                     | Sem status individual                                      |
| Adendo                | Documento                                      | adendos / id                                          | VALOR_TOTAL no payload         | colaborador_id                                                           | competencia                                               | status                                                     |

`origem + origem_id` identifica a entidade, mas **não distingue comissão e remuneração nem beneficiários**. A chave efetiva de saldo é `beneficiário + origem + origem_id + classe comissão/não-comissão`. O próprio ledger tem PK individual para distinguir parcelas. Para reconstrução mensal também é necessário `pagamentos.mes_ref`; não usar a criação como substituto da competência.

No banco capturado, `pagamento_itens` não possui `tipo_lancamento` ou `chave_lancamento`; `custos_tipo()` usa observação e origem como classificação legada. Não foi criada coluna. Existem 185 lançamentos `origem='animacao'` e 160 `origem='funcao_animacao'`; `helpers/custos_helper.php:149–150` liga os legados a `animacao.idanimacao`. IDs dessas duas tabelas não são intercambiáveis. O helper identifica sobreposição de animação legada com tarefas, mas a tela V2 consulta apenas tarefas FA para animação.

O valor especial de comissão R$ 100 também tem exemplo real no conjunto: `pairs['8/2026-08']`, FI 117304, imagem `1.MUS_AKE Fachada diurna`, base de comissão 100, ledger do gestor 100, saldo 0. Isso complementa o caso de comissão 80; não foi inventado um item para testar a exceção de Fachada.

O payload A contém nomes, valor, data e contadores, **sem origem/PK/beneficiário/classe canônicos**. O snapshot conserva a identidade para análise, mas isso não significa que ela seja transmitida atualmente pelo JS. Definir um contrato futuro baseado nesses campos exige decisão na FASE 1, não alteração agora.

## 5. Matriz de regras atuais

| Regra              | Onde / input → output                                                                                                     | Outros locais / duplicação                                                                     | Pode divergir? / classificação                                                                  |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| Elegibilidade      | financeiro_v2.php:7–29; beneficiário,mês,ano → entidades elegíveis; FI prazo+status OU qualquer log elegível no intervalo | getColaborador legado, getAdendoItens legado, getResumo por datas/flags                        | Sim; SQL e datas diferentes. COMPORTAMENTO ATUAL CONFIRMADO / DÍVIDA TÉCNICA                    |
| Valor da tarefa    | getColaborador.php:737–755 usa valor persistido da origem                                                                 | Tarifas/custo_tarefa e cálculo legado de divergência; B bruto                                  | Sim; tarifa atual não equivale a snapshot. COMPORTAMENTO ATUAL CONFIRMADO                       |
| Saldo              | getColaborador.php:758–771; original e ledger de todas as competências → max(0, original−pago), em centavos               | financeiro_v2 writers usam saldo; B e serviço documental não recalculam                        | Sim. COMPORTAMENTO ATUAL CONFIRMADO                                                             |
| Comissão           | V2 elegibilidade inclui fi de 23/40 função 4 para beneficiário 8; controller:100 Fachada sem embasamento, senão80         | financeiro_v2 writers; getAdendoItens do gestor conserva fi.valor                              | Sim, exemplo120172:80 vs300. REGRA DE NEGÓCIO APARENTE / POSSÍVEL BUG                           |
| Animação           | V2 usa fa.status + a.data_anima, valor fa.valor                                                                           | B tem ramo por fa.prazo para13/20/23/37; buildRows renomeia Animação 100/175→Pós-Produção      | Sim por data, descrição e valor que chegou. REGRA DE NEGÓCIO APARENTE / DÍVIDA TÉCNICA          |
| Acompanhamento     | V2 usa ac.data, qualquer estado de pagamento, valor ac.valor                                                              | B usa dados/data de pagamento; ID 1 extra fixo4000                                             | Sim, AC617; intenção da soma por item+fixo indeterminada. REGRA NÃO DETERMINADA                 |
| Fixo               | getResumo.php:128–142 adiciona cadastro ao pendente e total; modal solicita novo número; serviço soma input               | Payload antigo conserva fixo sem vigência                                                      | Sim, ID 1 cadastro 3000 vsadendo4000; ID 7 pendente 4600 com status pago. REGRA NÃO DETERMINADA |
| Bônus              | script.js:1720–1783 cria array; normalizeExtras:371 valida categoria/is_numeric; soma/tabela                              | ID 1 substitui por Acompanhamento4000; payload final omite rubricas                            | Sim; perda de histórico e formatos alternativos. REGRA DE NEGÓCIO APARENTE / DÍVIDA TÉCNICA     |
| Divergência        | Legado compara tarifa/valor e valor_aprovado; V2 marca pago>original                                                      | UI usa tem_divergencia e sync por nome; não usa flag V2 para bloquear adendo                   | Sim, FI110107. POSSÍVEL BUG                                                                     |
| Parcial            | V2: função 4 e Pré-Finalização existente ou último status de imagem1 → parcial=1; controller remove                       | Contadores de ledger por imagem+função+observacao; JS routing; buildRows por nome e contadores | Sim; “parcial” tem vários significados. REGRA NÃO DETERMINADA / DÍVIDA TÉCNICA                  |
| Exclusão de pago   | V2 marca pagamento=1 se p>=v e v>0; JS mantém completa com parcial e sem completa na aba A pagar                          | buildRows: nome sem parcial, data vazia OU parcial; sempre completa_count=0                    | Sim, FI110526/111524. POSSÍVEL BUG                                                              |
| Seleção documental | script.js:1788–1848: tabelas A pagar/Pagos + offsetParent; células1/2/3/5 → payload                                       | gerarAdendo:44–55; A não reaplica funções; B consulta e filtra funções                         | Sim por aba/filtros/lista vazia. DÍVIDA TÉCNICA / POSSÍVEL BUG                                  |
| Total documental   | AdendoLocalService:57–79: sumRows + sumExtras + fixo manual                                                               | getResumo tem cálculo próprio; pagamentos.valor_total é outra agregação                        | Sim; totais têm sentidos diferentes. REGRA NÃO DETERMINADA                                      |

Elegibilidade usa início do mês inclusivo e primeiro dia do mês seguinte exclusivo. Status aceitos: finalizado, em aprovação, ajuste, aprovado com ajustes, aprovado, após trim/lowercase. O comentário fala em estado no fim da competência, mas o predicado verifica **existência de qualquer log elegível**, sem exigir que seja o último. FI120385 entra mesmo com log posterior de Em andamento em setembro. Isso confirma implementação, não intenção histórica.

Saldo não filtra `pagamentos.status`, competência do lançamento ou data de corte: toda linha daquele beneficiário é somada. Uma captura atual de mês antigo pode mudar quando outro pagamento é criado depois. Não chamar esse replay de reconstrução histórica.

Os contadores parcial/completa vêm de pagamentos de funções4 da **mesma imagem**, não necessariamente da mesma PK/beneficiário, por observações exatas `Finalização Parcial` / `Pago Completa`. A identidade de saldo é mais específica que esses contadores. Nenhuma equivalência entre essa regra e os tipos futuros foi presumida.

## 6. Caminho A versus caminho B

**A:** getColaborador → financeiro_elegiveis → saldo do ledger → renderização/transformação da interface → linhas visíveis do DOM → Pagamento/gerar_adendo → normalização → buildRows. **B:** gerarAdendo, com itens vazios → getAdendoItens → filtro de funções opcional → buildRows. Ambos foram comparados sem gerar documento ou persistir adendo; A inclui projeção estática do JS.

Na tabela, “A serviços” usa somente a projeção da aba A pagar, sem filtros. Quantidade da consulta inclui os itens classificados para Pagos. Fixo e extras ficam fora desses subtotais.

| Colaborador / competência  | A consulta / elegíveis V2 | A linhas / serviços | B consulta / linhas / serviços                         |
| -------------------------- | ------------------------: | ------------------: | ------------------------------------------------------ |
| 27 João Vitor / 2026-09    |                   42 / 42 |          41 / 2.050 | Erro original bind 7 tipos/6 variáveis                 |
| 20 André Moreira / 2025-11 |                   26 / 27 |             8 / 800 | Erro original bind 10 tipos/9 variáveis                |
| 6 Bruna / 2026-09          |                   32 / 32 |          30 / 4.430 | Erro original bind 7/6                                 |
| 8 Marcio / 2026-09         |                   33 / 33 |          31 / 9.120 | 33 / 31 / 11.100                                       |
| 8 Marcio / 2026-08         |                   33 / 33 |               1 / 0 | 33 / 0 / 0                                             |
| 13 André Tavares / 2026-09 |                   13 / 13 |               0 / 0 | Erro original bind 10/9; A pagar vazia acionaria B     |
| 13 André Tavares / 2026-08 |                   41 / 41 |               0 / 0 | Erro original bind 10/9; A pagar vazia acionaria B     |
| 1 Nicolle / 2025-01        |                   84 / 84 |          84 / 2.415 | 84 / 2 / 0                                             |
| 1 Nicolle / 2026-08        |                     0 / 0 |               0 / 0 | 0 / 0 / 0; geração bloqueada por assinado              |
| 7 Anderson / 2026-08       |                   17 / 17 |              17 / 0 | Erro original bind 7/6; geração bloqueada por assinado |
| 4 Mariana / 2026-08        |                   16 / 16 |               0 / 0 | Erro original bind 7/6; geração bloqueada por assinado |
| 33 José Robson / 2026-08   |                   16 / 16 |               0 / 0 | Erro original bind 7/6; geração bloqueada por assinado |
| 33 José Robson / 2026-09   |                   19 / 19 |          17 / 3.800 | Erro original bind 7/6                                 |
| 40 Heverton / 2026-04      |                     8 / 8 |             2 / 100 | Erro original bind 7/6; geração bloqueada por assinado |

Nos quatro pares em que B executou, os conjuntos de IDs A/B são iguais. No snapshot, `A_ids`, `B_ids`, `only_A` e `only_B` mostram a lista exata. Não há equivalência comprovada para os ramos que falharam; não foram corrigidos para obter comparação artificial.

Marcio/2026-09 tem **12 diferenças semânticas** entre campos do endpoint A e de B, incluindo saldo, comissão e null versus zero; nomes e funções coincidem para os IDs comparados. `field_differences` preserva também diferenças de tipo PHP; `semantic_differences` converte números para float e preserva NULL, evitando chamar int400/float400 de diferença financeira. Exemplo FI120172: nome `21.RAY_DOM Piscinas angulo 2`, função `Finalização Completa - Heverton`, A 80/B 300. Nove comissões incluídas divergem em 220 cada, explicando a diferença de serviços **1.980**. As demais diferenças não alteram essa diferença de subtotal.

Nicolle/2025-01 tem nomes, funções e valores consultados iguais entre A e B; a diferença **2.415 vs0** nasce da seleção documental: A perde as datas na transformação, B conserva datas pagas e exclui linhas. Ambos ainda aplicariam extra 4000 e fixo manual; totais condicionais A=`6415+F`, B=`4000+F`, antes de qualquer bloqueio/erro. Marcio/2026-08 tem 33 diferenças semânticas de valores, mas ambos somam 0 nos serviços atuais: A mantém uma linha zero, B nenhuma. O adendo140 conserva histórico 7160, sem lista suficiente para reconstrução exata.

Evidências principais: `Pagamento/getColaborador.php:737–785`, `Pagamento/script.js:1788–1856,2786–2869`, `AdendoLocalService.php:44–79,511–984`. Erros de bind: linhas 970 e 973 do serviço. Para animação, A usa `animacao.data_anima`, enquanto o ramo B de colaboradores 13/20/23/37 usa prazo de FA; no exemplo FA 534 são setembro versus agosto. Esse ramo B não executa até a seleção por causa do bind inválido, portanto sua diferença de elegibilidade é evidência estática.

## 7. Possíveis bugs, preservados

Severidade abaixo é avaliação técnica de impacto, não decisão de regra de negócio.

| Achado / evidência                                                                                 | Consequência                                                                                 | Severidade técnica | Afeta valor financeiro?                                                         |
| -------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- | ------------------ | ------------------------------------------------------------------------------- |
| Bind inválido em B; AdendoLocalService:970/973; reproduzido nos pares acima                        | Lista vazia/uso de fallback impede geração com erro500 no endpoint                           | Alta               | Indiretamente: impede documento; não produziu valor errado na execução isolada  |
| Coletor posicional incompatível com Pagos; JS1800–1823 versus2845–2869; FA534                      | Nomes trocados, valores0, data/contadores perdidos; linhas pagas incluídas                   | Alta               | Sim: altera subtotal e seleção se aba Pagos for usada                           |
| Data de pagamento perdida em A pagar; JS1823 e2786–2843; AC617                                     | Linha já marcada paga pode entrar novamente; A 2415/B 0 em Nicolle janeiro                   | Alta               | Sim                                                                             |
| Comissão em B usa bruto; FI120172 A 80/B 300; nove itens em8/setembro                              | Subtotal B supera A em 1980                                                                  | Alta               | Sim                                                                             |
| Excesso pago V2 não alimenta tratamento visual legado; FI110107, flags true/false                  | Divergência real pode ficar fora do filtro e não bloqueia adendo por essa flag               | Alta               | Sim, risco de decisão financeira sem indicação adequada                         |
| Contador de completa exclui saldo positivo; FI111524 saldo 25, completa_count=1                    | Saldo exibido não entra no documento; contadores usam escopo por imagem                      | Alta               | Sim, diferença de 25 no exemplo                                                 |
| Pago integral com parcial_count=1 permanece em A pagar; FI110526                                   | Documento conserva linha0 com nome de pagamento final                                        | Média              | Seleção/documento alterados; subtotal 0 nesse item                              |
| Origem paga sem ledger; AC617 valor 10 e flag 1                                                    | V2 redefine como não pago; perda de data no DOM agrava inclusão                              | Alta               | Sim; intenção de compatibilidade legada não determinada                         |
| Nomes degradados de animação no merge V2; FA534 vira Animação/Custo geral da obra                  | Documento perde função/imagem real; identificação por texto se torna frágil                  | Média              | Potencialmente: também muda renomeação e seleção textual                        |
| Lista vazia por filtro aciona consulta interna; JS offsetParent + serviço44                        | Não representa “nenhum selecionado”; pode consultar itens que não estavam visíveis ou falhar | Alta               | Sim quando B executa; regra pretendida não determinada                          |
| Fixo sempre somado ao pendente do resumo; getResumo128–142; Anderson agosto status pago/valor4600  | Resumo de pendência não acompanha estado pago do registro                                    | Média              | Sim na apresentação; não prova lançamento duplicado                             |
| Geração grava adendo antes da confirmação; serviço111–132, endpoint81                              | Prévia já muda registro; cancelamento não reverte esse registro por esse fluxo               | Alta               | Histórico/total documental podem ser sobrescritos; não gera pagamento por si só |
| Confirmação move arquivo sem atualizar DB; confirmar_adendo60–72                                   | arquivo_path persistido pode apontar temp que não existe após mover                          | Alta               | Não muda cálculo; compromete acesso/rastreabilidade                             |
| Nome registrado ignora sufixo retornado pelo PDF; serviço111–138 versusContratoPdfService:92       | arquivo_nome/payload podem divergir do arquivo físico em colisões                            | Média              | Não muda cálculo                                                                |
| Regeneração não limpa token/URL/metadados antigos; UPDATE serviço188                               | Documento novo pode conservar referência de assinatura antiga                                | Alta               | Risco documental e auditável; sem demonstração de uso real nesta fase           |
| Extras com string decimal de vírgula descartados e negativos numéricos aceitos; normalizeExtras371 | Entradas alternativas têm resultado distinto da expectativa do formulário                    | Média              | Sim sob essas entradas; não há bônus real comprovando ocorrência                |

Fragilidades adicionais classificadas como dívida técnica: valores sem identidade no payload; SQL legado duplicado; observação livre como tipo de lançamento; divergência sincronizada por substring do nome da imagem; ausência de detalhe de itens/extras no histórico. Nenhuma delas foi corrigida.

## 8. Decisões de negócio necessárias

Estas decisões permanecem abertas; o código não é suficiente para determinar intenção:

1. O fixo cadastrado substitui, sugere ou soma ao valor manual? Existe vigência por competência? Uma consulta de mês antigo deve usar o cadastro atual ou o valor vigente então? ID 1 tem cadastro 3000 e fixo histórico 4000.
2. Como o fixo participa de pendência depois de um pagamento? Anderson possui status pago e pendente 4600 no resumo.
3. O extra de acompanhamento4000 do ID 1 deve substituir bônus, coexistir com acompanhamento por item e somar a um segundo fixo manual? A intenção de evitar dupla contagem não está explícita.
4. Ausência de bônus, bônuszero, lista vazia e bônus descartado são estados equivalentes? Como preservar categoria, autoria, valor e competência? Não há histórico estruturado suficiente.
5. Comissão do gestor deve manter80/100 em todas as rotas? Quais colaboradores/imagens são elegíveis e qual regra histórica vale? A regra específica de Fachada/embasamento está no código; B contradiz seu valor.
6. Saldo é global atual ou saldo na data de corte da competência? Hoje desconta inclusive pagamentos posteriores e não filtra status do pagamento. Qual precedência entre ledger e flags antigas pagas sem ledger?
7. “Parcial” significa etapa da tarefa, parcela financeira ou complemento? Como reconciliar parcial V2, observação e contador por imagem? O saldo 25 excluído precisa de decisão explícita.
8. Deve bastar qualquer log elegível no mês, ou deve valer o último estado no fechamento? O exemplo120385 mostra a diferença.
9. Data de competência da animação é data_anima ou prazo da função? Como tratar `animacao` legada e `funcao_animacao` sem duplicar valores?
10. Adendo descreve itens elegíveis, saldos pendentes, serviços executados ou itens visíveis/selecionados? Como devem funcionar Pagos, busca, função, divergência e lista vazia?
11. Divergência financeira deve bloquear geração, exigir decisão ou apenas alertar? Aprovação do valor legada não resolve automaticamente excesso de ledger.
12. O que significa regenerar gerado/enviado/visualizado? Deve invalidar token/URL, preservar versões ou exigir etapa explícita? O que é persistência de prévia versus confirmação?
13. Como conservar a rastreabilidade dos itens, extras e fixo do documento para futuras comparações? O payload atual de quatro campos é insuficiente.

## 9. Testes de caracterização

Foi seguido o padrão simples de scripts PHP CLI e asserts/exceções já existente, sem adicionar framework ou mudar produção para torná-la testável.

Comando offline, a partir da raiz do repositório:

```powershell
& C:\xampp\php\php.exe tests/characterization/pagamento_adendos.php --verify
```

Resultado: **OK, 1.312 comparações offline**, divididas em:

- 1.140: valor_exibido, pagamento e divergencia_financeira para 380 itens congelados, executando o trecho puro de saldo original do controller com ledger congelado.
- 168: seis resultados de normalização/linhas/serviços/extras/total condicional em 28 projeções documentais de 14 pares.
- 4: linhas dos fallbacks que executaram com sucesso na captura, reprocessadas pelo buildRows original.

Lint dos dois arquivos PHP auxiliares passou. `tests/custos_v2_test.php` passou **329 verificações com fixtures em memória**; é cobertura do motor de Custos, não validação da cadeia completa de Pagamento/Adendos. Não foram executados testes que escrevem no DB. Os seis hashes de produção registrados na captura foram comparados ao estado final e permaneceram iguais; essa comparação foi uma verificação manual, não parte de --verify.

Inspeção/captura adicional possível e realizada: queries reais, IDs e quantidades congelados, contadores, elegibilidade de referência, ledger de diferentes competências/beneficiários, resumo financeiro, erro original do fallback, seleção documental pura. A guarda SQL autoriza apenas SELECT/SHOW auditados antes de query/prepare/real_query e rejeita multi_query. Ela não substitui uma conta DB com privilégios somente leitura nem é um sandbox SQL universal. A captura fez leituras sucessivas, sem snapshot transacional; mudanças concorrentes poderiam afetar consistência entre consultas.

Limitações do replay automatizado:

- **Não reexecuta elegibilidade, queries de IDs/quantidades, o controller inteiro nem getAdendoItens.** A captura documenta esses resultados, mas uma alteração SQL pode não falhar em --verify.
- Não executa JavaScript, DOM, filtros, autenticação, responsividade, PDFs/templates ou regeneração. A projeção do DOM é descrição do comportamento, não assert de que esse comportamento é regra correta.
- Não reconstrói gerações históricas com exatidão: faltam itens, extras e input manual originais.
- Não cobre exemplo real de valor175 em FA, fixoNULL, bônus manual recuperável e status enviado/recusado/expirado.

Antes de centralizar, será necessário comparar seleção real de IDs/quantidades/elegibilidade na mesma base estável, por funções somente leitura ou uma cópia isolada. Os asserts de saldo/documento já podem ser reutilizados. A correção futura de um possível bug deve ter expectativa própria aprovada; não substituir silenciosamente o golden pelo resultado corrigido.

`--summary` e `--snapshot-json` apenas leem o snapshot. `--capture` conecta ao banco e emite JSON, não grava automaticamente a golden; serve para comparação separada. Não recapturar em cima da referência congelada durante testes. O arquivo golden não deve ser convertido em JSON público dentro do webroot: a extensão PHP e o bloqueio HTTP foram usados para conservar os dados financeiros locais.

## 10. Checklist para FASE 1

Condições para começar a centralização:

- [x] Código de produção e dados preservados; nenhuma execução de operações documentais/financeiras com efeitos colaterais.
- [x] Base congelada com identidades, ledger, valores, payloads projetados e linhas documentais para os pares analisados.
- [x] Diferenças A/B e erros originais registrados sem “conserto” preparatório.
- [x] Regras distribuídas, exclusões, fixo, acompanhamento, comissão e histórico insuficiente documentados.
- [x] Replay offline seguro, passando, com alcance explícito.
- [ ] Definir, com o negócio, as decisões da seção 8; registrar quais comportamentos serão preservados e quais bugs têm correção autorizada.
- [ ] Estabelecer contrato de identidade no servidor: origem/PK/beneficiário/classe, sem confundir comissão e tarefa ou animação legada e FA.
- [ ] Definir data de corte, precedência ledger/flags e tratamento de parcial/complemento/pago/excesso.
- [ ] Definir significado/vigência do fixo e persistência de bônus/extras; resolver a regra especial do ID 1.
- [ ] Definir seleção documental e comportamento de lista vazia independentemente de nomes/posições de células.
- [ ] Reproduzir captura e comparação dos IDs/quantidades/elegibilidade em base estável ou cópia controlada, assegurando consistência temporal. Registrar eventuais lacunas de cenários sem inventar registros.
- [ ] Validar na interface autenticada as duas abas e filtros usando uma das URLs oficiais quando o navegador interno estiver disponível, sem clicar em operações proibidas nesta fase. Desktop/notebook/iPad landscape/portrait/mobile continuam pendentes.
- [ ] Validar apresentação do documento usando artefatos existentes ou um ambiente isolado autorizado; não gerar adendo real para obter essa evidência.
- [ ] Definir preservação/versionamento do histórico documental e ciclo prévia/confirmar/regenerar/token/URL.
- [ ] Fazer a nova implementação executar em comparação somente leitura com a golden antes de substituir o fluxo, incluindo divergências deliberadas aprovadas.

**Situação:** caracterização técnica e base congelada entregues; a FASE 1 não foi implementada. Validação prática da UI/PDF e decisões de negócio permanecem condições abertas, não resultados presumidos.
