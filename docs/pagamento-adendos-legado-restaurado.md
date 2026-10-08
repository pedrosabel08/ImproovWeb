# Resolução do conflito legado — 05/10/2026

**LEGADO RESTAURADO · FLAG OFF · PRONTO PARA CANARY.** Verificação final em 05/10/2026, 21:10:56, America/Sao_Paulo. Canary não iniciado.

O merge `40a3c770` entre `d04145bf` (automação de pagamentos) e `093e0b0c` (layout de Pagamento) gravou marcadores de conflito em `Pagamento/getColaborador.php`. O commit seguinte `7c027add` resolveu os conflitos equivalentes em `financeiro_v2.php` e `resumo_geral.php`, mas deixou dois blocos no controller. O arquivo já era inválido antes do deployment estrutural.

| Lado | Comportamento |
|---|---|
| A — `d04145bf` | Mantém valor salvo e comissão 80/100; usa `financeiro_valor_previsto_centavos` para recuperar tarifa de origem zerada somente na condição legada auditada de finalização com parcela anterior. Passa conexão ao resumo para conservar a mesma resolução de tarifa. |
| B — `093e0b0c` | Centraliza valor salvo/comissão em `financeiro_snapshot`; chama resumo sem conexão, desativando a recuperação de tarifa nessa projeção. |

**Decisão:** preservar A nos dois blocos. Não se trata de escolher HEAD pelo nome: a função e sua condição já foram auditadas na [FASE 1B, seção 3](pagamento-adendos-fase1b.md); a resolução posterior dos módulos relacionados manteve A; o resumo atual recebe conexão opcional e conserva parcelas comprovadas. B preserva o cálculo comum de comissão/valor salvo, mas perderia a correção específica já integrada. Não houve adaptação ao motor novo 1A nem criação de regra financeira.

Diff: **zero linhas adicionadas, oito removidas** — seis marcadores e duas alternativas incompatíveis. `git diff d04145bf -- Pagamento/getColaborador.php` ficou vazio: o resultado é exatamente o controller pré-conflito do commit de referência. Não existem outros marcadores no módulo `Pagamento`.

| SHA-256 de `getColaborador.php` | Hash |
|---|---|
| Antes | `d3ac47ac5738896d088d083cc61f869085445b500303972096556da76fc88c8e` |
| Depois, auditado após o diff | `fa944c739037905c26ac9d69e571d8c016e68263e5bc5e33307ecf69ebc9294a` |

SHA novo registrado neste relatório e [manifesto](evidence/pagamento-legacy-fix-2026-10-05.json). Whitelists/guards permanecem intactos; não se ampliou autorização de execução dinâmica nem se executou shadow vivo dependente desses guards. Os testes vivos abaixo exercitam os readers seguros 1A/1B.

| Validação | Resultado |
|---|---|
| `php -l Pagamento/getColaborador.php` | No syntax errors detected |
| Caracterização histórica `--verify` | 1.312 comparações offline OK; golden preservada |
| `tests/custos_v2_test.php` | 338 verificações OK |
| 1A financeiro offline / 1B composição offline | 230 / 169 verificações OK |
| 1A / 1B `--db-readonly` | 14 / 26 verificações OK; SELECT/transação read-only, sem DML/DDL |
| Goldens de comparação 1A / 1B | 10 / 3 casos; 16 / 5 diferenças esperadas; **zero inesperadas**, exit 0. [1A](evidence/pagamento-legacy-fix-golden-1a-2026-10-05.json), [1B](evidence/pagamento-legacy-fix-golden-1b-2026-10-05.json) |
| Resumo financeiro legado pré-merge | 25 verificações OK; corpo em memória igual a `d04145bf` |

O arquivo externo `tests/pagamento_resumo_test.php` também contém um conflito do mesmo merge e foi preservado, conforme o escopo. O [harness de auditoria](../tests/pagamento_resumo_conflict_audit.php) verifica que a resolução em memória é idêntica ao teste do commit pré-merge antes de executar seus casos offline. Não foi declarado que o teste original inválido passa diretamente.

Navegador: login real e Financeiro → Pagamento → Por colaborador nas **duas URLs oficiais**. Cobertos carregamento, troca de mês/ano, A pagar, Pagos, Divergências, comissão (Marcio/setembro, nove marcações no DOM), pagamento parcial (Heverton/abril, parcela comprovada e saldo visível) e vazio (Flow/janeiro 2023). Tarefas carregaram, sem erro PHP ou erro de console; nova tela sem link. Apenas aviso de depreciação `backgroundColor` do Toastify, sem falha funcional. Contagens e casos no manifesto, sem valores financeiros ou dados cadastrais.

[Pre-flight após a correção](evidence/pagamento-legacy-fix-readiness-2026-10-05.json): exit 0; MySQL 8, 1C-A/1C-B e storage OK; **8 tabelas / 14 triggers / FLAG OFF**. Configuração privada Apache permanece com flag `0`. Nenhuma migration, fechamento, adendo, confirmação, pagamento ou PDF foi executado. Os módulos do novo fluxo e os conflitos externos foram preservados.
