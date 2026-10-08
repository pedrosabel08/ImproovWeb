# Ajustes de adendo, sábado e retiradas

Esta entrega atende aos três ajustes autorizados: PDF sem tarefas quitadas, sábado na contagem do 5º dia útil e retirada/restauração de tarefas ou funções no fechamento.

## Regras

- A seleção continua baseada na atribuição de cada tarefa. Caderno, Alteração e outras funções podem aparecer para qualquer colaborador atribuído, sem divergência automática por função habitual.
- O PDF mensal novo ou pendente exclui `RETIRADO`. Serviços `QUITADO` aparecem para consulta quando têm pagamento positivo com `mes_ref` igual à competência selecionada, identificados como pagos e com o valor pago nessa competência; quitações de outras competências continuam fora. Tarefas sem remuneração de colaboradores FIXO continuam com `−` quando não estiverem quitadas ou retiradas.
- Pagamentos históricos permanecem na composição financeira oficial e no ledger. O adendo mostra o valor pago para tarefas quitadas na competência selecionada, e desconta esses pagamentos do total documental. `total_centavos` do modelo preserva o valor financeiro reconhecido; `total_documental_centavos` identifica o valor efetivamente apresentado no adendo.
- Animações são identificadas por `nome da imagem - tipo`, com capitalização de título e `IA` em maiúsculas; serviços e adendo seguem a ordem de projeto, imagem, animação e tarefas da animação.
- O bônus de produtividade de Finalização R0 aparece na conferência somente para colaboradores com tarefas de Finalização.
- Documentos confirmados permanecem imutáveis. Previews antigos são preservados, mas a interface prepara um novo preview v4; o backend recusa confirmar previews mensais antigos.
- A faixa após os KPIs mostra apenas o estado e os valores resumidos da competência selecionada, com a previsão e a ação de conclusão, sem repetir o mês/ano.
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

Na revisão visual de 08/10/2026, a URL HTTPS oficial abriu a tela autenticada antes da atualização; após recarregar, o fechamento ficou no indicador “Carregando fechamento”. A URL HTTP oficial exigiu login; depois do login, navegar por Financeiro → Pagamento → Fechamento também ficou carregando. O MySQL isolado da porta 3320 recusou a conexão até com acesso local permitido, então a integração e a validação visual final ficaram bloqueadas pelo ambiente. PHP lint e `node --check` passaram.

Na conferência posterior da aba **Por colaborador**, a URL HTTP oficial voltou a carregar a competência autenticada. A aba agora usa toda a largura disponível, com identificação e status no topo, cartões de total/pago/pendente, composição e pagamento em painéis e lista expansível de funções. Foram conferidos Ana Carolina (pendente), Adriana (paga), seleção vazia, edição e limpeza da observação e expansão das funções. Desktop, notebook 1366 × 768, iPad 1024 × 768 e 768 × 1024 e mobile 390 × 844 foram conferidos; a quebra das ações no mobile foi corrigida. Nenhuma quitação foi enviada. A sintaxe do JavaScript e `git diff --check` passaram. Esta conferência não substitui a integração isolada que depende da porta 3320.

Os valores da aba individual agora usam `pagamentoMotion`, com contagem de zero até o valor oficial e respeito a `prefers-reduced-motion`. Ao enviar a quitação, o botão mostra “Registrando…” com o Thinking Orbs global, indica processamento para acessibilidade e bloqueia envios repetidos. O indicador é encerrado tanto no sucesso quanto no erro; o erro restaura o botão. A contagem inicial, os totais finais e a ausência de erros foram conferidos no navegador autenticado. Os três testes de `node --test tests/pagamento_individual_motion_test.cjs` passaram usando transporte simulado, sem banco ou quitações reais, cobrindo integração dos valores com a animação, duplicidade/erro e sucesso com atualização dos valores.

## Roteiro da interface

1. Entrar pela URL oficial e navegar a Financeiro → Pagamentos → Fechamento. Para mutações de teste, usar a rota isolada `tests/competencia-browser.php`.
2. Abrir um colaborador e expandir **Ver detalhes dos serviços**.
3. Clicar **Retirar** na tarefa, informar motivo e confirmar. Conferir linha “Retirado do fechamento”, total atualizado, ausência no PDF e revisão pendente.
4. Clicar **Restaurar**, informar motivo e conferir recomposição da lista/valor.
5. Selecionar uma função e **Retirar função**. Conferir retirada dos itens sem pagamento dessa função. Restaurar um único item e depois **Restaurar função** para validar os dois alcances.
6. Conferir previsão 06/10/2026 e exclusão de tarefas quitadas no PDF novo. Confirmar o novo PDF somente depois de revisar os valores.
7. Concluir a competência sintética e verificar que as ações de retirada ficam indisponíveis. Quitar e conferir que tarefas retiradas não foram marcadas como pagas.
8. Validar desktop, notebook, iPad landscape/portrait e mobile quando o controle do navegador estiver disponível.
