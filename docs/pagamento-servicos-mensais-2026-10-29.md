# Serviços do fechamento mensal — 29/10/2026

## Comportamento

**Ver detalhes dos serviços** abre um modal amplo, com abas **Pendentes**, **Já pagos** e **Retirados**. Cada tarefa mostra projeto, imagem, função, valor total, valor já pago, saldo a pagar e lançamentos históricos. Tarefas parcialmente pagas aparecem também em Pendentes, somente com o saldo restante. A apresentação se adapta a cartões no mobile.

**Alterar valor** define o valor total daquela tarefa neste fechamento, exige motivo e cria uma decisão auditada `SERVICOS`, com autor, instante, valor original e valor substituto. Preserva a tarifa da origem, os lançamentos e as revisões anteriores. O PDF é regenerado e precisa ser conferido novamente. Não permite editar tarefa quitada, retirada, de outro colaborador/competência ou com divergência não resolvida; rejeita valor negativo ou inferior ao já pago. **Restaurar valor** remove o ajuste vigente por uma nova decisão auditada.

Se a tarifa ou os pagamentos mudarem após o ajuste, a revisão fica em atenção e mantém a ação de restauração disponível. Concorrência, idempotência, autorização e CSRF continuam no mecanismo existente. Nenhuma tabela ou tipo novo de journal foi necessário.

Finalizações identificadas como parciais pelo motor mensal não integram os itens, soma de serviços ou PDF das novas revisões. A interface também as omite ao consultar snapshots anteriores. Revisões e documentos já confirmados não são reescritos.

## Diagnóstico de Pedro Henrique e Heverton

Consulta de outubro/2026, usando o repository real em transação de leitura:

- Pedro Henrique: 9 alertas `PAGAMENTO_SEM_LEDGER`, todos de tarefas de Alteração com base zero e flag legada de pagamento; parte tinha registros zerados para beneficiários anteriores e parte não tinha lançamento.
- Heverton: 2 alertas do mesmo tipo, também Alteração com base zero e registros zerados para beneficiário anterior.

A regra mensal deixa de transformar a flag legada sem ledger em valor indeterminado **quando a base é exatamente zero**. O item continua como **Sem valor**, sem fabricar quitação, lançamento ou transferência de pagamento de outro colaborador. Bases positivas sem lançamento compatível continuam bloqueadas. A regra financeira legada fora do fechamento mensal permanece intacta.

Após a correção, o cálculo atual encontrou **zero pendências para ambos**, sem alterar o banco compartilhado. As revisões persistidas continuam refletindo o snapshot anterior até uma ação explícita de **Atualizar valores**.

## Validação

- `php tests/fechamento_mensal_pendencias_test.php`: 30 verificações.
- `php tests/fechamento_mensal_v1_rules_test.php`: 53 verificações.
- `php tests/fechamento_mensal_v1_integration_test.php`: 48 verificações, incluindo PDF sem Finalização parcial.
- `php tests/fechamento_servicos_valores_test.php`: 22 verificações de edição, auditoria, PDF, pagamento parcial, ajuste desatualizado, valores zerados, idempotência, restauração e rejeições.
- `php tests/fechamento_servicos_http_test.php`: edição/restauração HTTP, idempotência e CSRF.
- `php tests/pagamento_competencia_test.php`: 111 verificações do ciclo completo, incluindo conclusão, pagamento sintético, concorrência e proteção de histórico. Foram atualizados pressupostos da fixture: cabeçalho único por competência, versão consultada em vez de fixa, snapshot completo para a projeção documental e contexto de tarifas/gráfico de funções atual.
- `php tools/test_page_metadata.php`: 830 verificações; catálogo/head central aplicados à página alterada.
- PHP lint, `node --check Pagamento/mensal.js` e `git diff --check` sem erros.

Toda escrita de teste utilizou MySQL 8.0.42 isolado em `127.0.0.1:3320`, com dados sintéticos e storage próprio. A instância foi iniciada a partir do pacote portátil oficial porque os executáveis da homologação anterior já não estavam disponíveis.

Ao encerrar, os bancos sintéticos criados nesta instância foram removidos após validar o datadir exato e o MySQL de homologação foi desligado. Os manifestos versionados de fixtures foram restaurados ao conteúdo anterior.

Login autenticado na URL oficial HTTPS, navegação Financeiro → Pagamento → Fechamento e carregamento da listagem real foram conferidos pelo navegador interno. A edição foi exercitada pela rota de homologação local já existente, sem criar ou confirmar documentos reais. Teste visual: valor total de R$ 180,00, mantendo R$ 20,00 pagos e saldo de R$ 160,00, seguido de regeneração do PDF.

Capturas em `output/ui/servicos-mensais-20261029/`: desktop 1920×1080, notebook 1366×768, iPad landscape 1024×768, iPad portrait 768×1024 e mobile 390×844; sem transbordamento horizontal do modal. Tema claro também conferido.

## Publicação

Publicar em conjunto:

```text
Pagamento/api/FechamentoHttp.php
Pagamento/fechamento.php
Pagamento/mensal.css
Pagamento/mensal.js
Pagamento/services/FechamentoFinanceiroRules.php
Pagamento/services/FechamentoRetiradaRules.php
Pagamento/services/FechamentoRevisaoService.php
config/page_metadata.php
Contratos/services/ContratoPdfService.php
```

O último arquivo é a dependência de PDF identificada no diagnóstico anterior. O pacote desta entrega inclui a versão local que oferece `renderizarHtml`. O schema compartilhado já contém `SERVICOS` nos dois enums de decisões/operações; não foi aplicado DDL nesta tarefa.

Preservar backup dos arquivos de destino e publicar o conjunto na mesma janela, observando OPcache quando necessário. Depois da publicação, atualizar os valores dos colaboradores com revisões antigas e conferir o novo adendo. Nenhum deploy, pagamento ou confirmação real foi realizado nesta tarefa.
