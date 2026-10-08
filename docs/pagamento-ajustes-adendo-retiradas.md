# Ajustes de adendo, sábado e retiradas

Esta entrega atende aos três ajustes autorizados: PDF sem tarefas quitadas, sábado na contagem do 5º dia útil e retirada/restauração de tarefas ou funções no fechamento.

## Regras

- A seleção continua baseada na atribuição de cada tarefa. Caderno, Alteração e outras funções podem aparecer para qualquer colaborador atribuído, sem divergência automática por função habitual.
- O PDF mensal novo ou pendente exclui `QUITADO` e `RETIRADO`. Tarefas sem remuneração de colaboradores FIXO continuam com `−` quando não estiverem quitadas ou retiradas.
- Pagamentos históricos permanecem na composição financeira oficial e no ledger. As linhas do PDF usam o saldo não pago; seu valor documental desconta os pagamentos da competência já incluídos na revisão. `total_centavos` do modelo preserva o valor financeiro reconhecido; `total_documental_centavos` identifica o valor efetivamente apresentado no adendo.
- Documentos confirmados permanecem imutáveis. Previews antigos são preservados, mas a interface prepara um novo preview v2; o backend recusa confirmar um preview mensal v1.
- Sábado conta como dia útil para Pagamentos. Domingo e os feriados do calendário existente não contam. A regra de Entregas mantém o comportamento anterior, pois o parâmetro opcional `incluirSabado` é falso por padrão.
- Setembro/2026: previsão **06/10/2026**. O ajuste da competência aberta foi explícito, auditado como `PREVISAO_ALTERADA` e refletido nos prazos dos checklists. Competências concluídas não recebem alteração de data.
- Retiradas exigem motivo, gestor autenticado, CSRF, versão vigente e chave idempotente. São decisões da pessoa/competência, sem alterar produção ou cadastro.
- Retirar função alcança seus itens sem pagamento, inclusive novos candidatos dessa função enquanto a competência estiver aberta. Não apaga pagamentos históricos. Restaurar uma tarefa permite uma exceção individual; uma nova decisão por função substitui as exceções daquela função.
- Retirar tarefa com pagamento registrado é recusado. Isso evita apagar ou ocultar um direito já liquidado. Uma retirada em lote preserva esses itens no histórico.
- Cada decisão gera revisão e journal com antes/depois, motivo, autor e instante. A revisão anterior deixa de contar como revisada; é necessário visualizar e confirmar o novo PDF.
- Retirar Finalização também retira o evento correspondente da contagem financeira de produtividade; o bônus é recalculado. Restaurar recompõe a contagem.
- Após a conclusão geral, retirar/restaurar continua bloqueado. As funções retiradas não são lançadas nem marcadas como pagas ao quitar o colaborador.

## Banco e implantação

Migration: `sql/2026-10-08_pagamento_retiradas.sql`.

Reutiliza `pagamento_fechamento_decisao` e `pagamento_fechamento_operacao`, acrescentando `SERVICOS` aos enums; não cria outra tabela de decisões. Substitui `pc_snapshot` para permitir somente correção de previsão de competência aberta respaldada por evento de auditoria, mantendo todas as demais proteções de fechamento/quitação.

A migration foi aplicada com backup privado das tabelas e do gatilho. Setembro foi atualizado para 06/10, sem registrar pagamento, confirmar PDF real ou reenviar Slack. O pacote privado do job foi atualizado para `v20261007_80ad024e3d86`; as páginas públicas do servidor continuam sem publicação, conforme o escopo da implantação anterior.

## Arquivos

Novos: `Pagamento/services/FechamentoRetiradaRules.php`, a migration, `tests/pagamento_retiradas_http_test.php` e este documento.

Alterados: `Entregas/prazo_entrega_helper.php`, `helpers/pagamento_competencia_helper.php`, `Pagamento/services/FechamentoRevisaoService.php`, `FechamentoMensalRules.php`, `FechamentoInterfaceService.php`, `FechamentoDocumentoProjection.php`, `FechamentoDocumentoRepository.php`, `FechamentoDocumentoService.php`, `FechamentoCompetenciaService.php`, `Pagamento/api/FechamentoHttp.php`, `Pagamento/fechamento.php`, `mensal.js`, `mensal.css`, `pagamento_auth.php`, `scripts/deploy_pagamento_competencia.php`, `tests/pagamento_competencia_test.php` e a documentação anterior.

O endpoint `Pagamento/api/fechamento/decidir.php` reutiliza `tipo: SERVICOS`, com `estado: RETIRAR|RESTAURAR`, `alvo: ITEM|FUNCAO`, identidade ou ID da função e motivo. O endpoint de documento mantém seu contrato financeiro e adiciona metadados da versão documental.

## Validação

- **99 verificações** do ciclo financeiro passaram, incluindo retirada/restauração, função em lote, exceção individual, alteração da faixa de bônus, item de outro colaborador, tarefa paga, fechamento concluído, PDF sem quitados, valor documental, previsão auditada e idempotência.
- Regressões: **53** regras mensais, **48** integração mensal e **194** documentais.
- Teste HTTP na rota isolada: retirada, restauração e rejeição de CSRF inválido passaram. O retorno de CSRF foi padronizado para HTTP 403; o Apache local convertia o antigo 419 em 500, mantendo indevidamente a requisição na fila de retry da interface.
- HTTP oficial: `http://localhost:8066/ImproovWeb/` responde 200. A tentativa HTTPS pelo cliente PHP recusou o certificado local autoassinado, sem desativar a verificação de certificado.
- Validação visual das novas ações ainda pendente: o controle do Edge encerra o processo antes de acessar as páginas, mesmo após tentativa de reconexão. Não confundir as evidências visuais da entrega anterior com validação desta alteração.

```powershell
php tests/pagamento_competencia_test.php
php tests/pagamento_competencia_test.php --browser-ready
php tests/pagamento_retiradas_http_test.php
php scripts/validar_pagamento_competencia.php 2026-09
```

Os testes usam MySQL loopback na porta 3320 e um banco `pagamento_1cb_test_*`. O teste HTTP exige a fixture aberta, preparada por `--browser-ready`, e altera somente o banco sintético.

## Roteiro da interface

1. Entrar pela URL oficial e navegar a Financeiro → Pagamentos → Fechamento. Para mutações de teste, usar a rota isolada `tests/competencia-browser.php`.
2. Abrir um colaborador e expandir **Ver detalhes dos serviços**.
3. Clicar **Retirar** na tarefa, informar motivo e confirmar. Conferir linha “Retirado do fechamento”, total atualizado, ausência no PDF e revisão pendente.
4. Clicar **Restaurar**, informar motivo e conferir recomposição da lista/valor.
5. Selecionar uma função e **Retirar função**. Conferir retirada dos itens sem pagamento dessa função. Restaurar um único item e depois **Restaurar função** para validar os dois alcances.
6. Conferir previsão 06/10/2026 e exclusão de tarefas quitadas no PDF novo. Confirmar o novo PDF somente depois de revisar os valores.
7. Concluir a competência sintética e verificar que as ações de retirada ficam indisponíveis. Quitar e conferir que tarefas retiradas não foram marcadas como pagas.
8. Validar desktop, notebook, iPad landscape/portrait e mobile quando o controle do navegador estiver disponível.
