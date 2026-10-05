# Pagamento: visão geral e por colaborador

## Auditoria (05/10/2026)

Página: `Pagamento/index.php`. A interface operacional é reorganizada por `script.js`, com estilo em `style.css`. Novo dashboard em `visao-geral.js`/`visao-geral.css`, separado do workspace individual. `view`, `mes`, `ano` e `colaborador_id` persistem na URL. Sem framework ou migration.

Consultas operacionais: `getColaborador.php` consulta dados cadastrais, imagens, acompanhamento, animação, histórico de status, tarifas e pagamentos. `financeiro_elegiveis` em `financeiro_v2.php` é a fonte compartilhada de elegibilidade da tela e dos escritores financeiros. Foi estendida para carregar todos os colaboradores em lote. `PagamentoService` mantém competência, pagamentos e eventos. `helpers/custos_helper.php` fornece centavos, tipos de lançamentos e queries preparadas; `helpers/custo_tarefa.php` fornece tarifas e exceções de PH.

Tabelas: `colaborador`, `usuario`, `informacoes_usuario`, `endereco`, `endereco_cnpj`, `funcao`, `funcao_colaborador`, `funcao_imagem`, `imagens_cliente_obra`, `obra`, `historico_imagens`, `log_alteracoes`, `acompanhamento`, `animacao`, `funcao_animacao`, `pagamentos`, `pagamento_itens`, `pagamento_eventos`, `adendos`, `log_adendos`.

Competência:

- Imagens: status elegível e prazo no mês, ou mudança para um status elegível no mês em `log_alteracoes`. Estados: Finalizado, Em aprovação, Ajuste, Aprovado com ajustes e Aprovado.
- Finalização parcial: identificação existente por pré-finalização ou snapshot do histórico de imagem no fim da competência. Excluída da lista consolidada, como na tela individual atual.
- Acompanhamento: `data` no mês.
- Animação: `data_anima` no mês e status da função elegível.
- Comissão do gestor 8: apenas finalizações completas de 23/40, com a regra vigente de Fachada/embasamento. É um custo distinto do custo do executor.

Valores: snapshots salvos nas origens, sem substituição pelas tarifas atuais. Pago é soma dos lançamentos do livro para a origem e o beneficiário, separando comissão de execução; parcelas e complemento são liquidações do mesmo item. São considerados todos os meses de liquidação, como no detalhe V2. Pendente é o saldo não negativo da origem. Isso representa a posição atual dos itens da competência, não fluxo de caixa nem posição histórica no fechamento do mês. Uma tarefa elegível em mais de uma competência pode reaparecer conforme a regra vigente; não se devem somar dashboards de meses diferentes como reconhecimento contábil de custo.

Divergências de tarifa preservam o indicador existente: valor salvo diferente da tarifa, exceto valor aprovado ou comissão; aplica-se às funções de imagem. Divergências financeiras seguem as condições de reconciliação já usadas pelo escritor: excesso pago, negativos, repetição incompatível com parcial+complemento, ou flag paga sem livro. O card conta itens com ocorrências, sem transformar divergência em um novo valor financeiro. A quantidade é sobreposta a pagos/pendentes.

`getResumo.php` é legado: usa prazo sem elegibilidade, pode substituir total por `pagamentos.valor_total` e acrescenta fixo. Não é fonte do dashboard e não é mais consultado automaticamente pela página. Mantido para compatibilidade com consumidores antigos.

## Adendos e ações preservadas

`gerar_adendo.php` gera PDF temporário e preview em sessão; `confirmar_adendo.php` confirma e move o arquivo; `ver_adendo.php` entrega o preview. A assinatura/tracking documental é mantida pelo módulo Contratos em `adendos`/`log_adendos`. Gerar um PDF local, por si só, não prova a existência de um registro no tracking. A visão geral conta os registros reais da tabela por competência, separadamente do financeiro.

Estados reais: Não gerado, Gerado, Enviado, Visualizado, Assinado, Recusado, Expirado. O KPI mostra registros e quantos não estão assinados; não inventa estados de aprovação. `get_adendo_status.php` continua oferecendo single/by_id/geral; geral aceita a competência selecionada e mantém o mês anterior como fallback.

Pagamento individual mantém `updatePagamento.php`, `insertHistorico.php`, `updateValor.php`, `aprovarValor.php`, `corrigirValores.php`, handlers, seleção, exportações, dropdowns e modais. `updateStatusPagamento.php` mantém o workflow legado. Escritores V2 usam `financeiro_pagar`/`financeiro_lancar`. Os endpoints de mutação mantêm autenticação de gestor e CSRF.

Correção de apresentação justificada: antes o saldo V2 era usado também como valor pago, exibindo zero em registros quitados. Agora `valor_pago` identifica o que foi liquidado e os KPIs individuais usam o mesmo resumo PHP do dashboard. Filtros individuais continuam afetando abas e seleção; indicadores da competência não são recalculados a partir do DOM.

## Contrato e filtros

`GET Pagamento/getVisaoGeral.php?mes=9&ano=2026` retorna `success`, `competencia`, `unidade_monetaria: centavos`, `resumo`, `adendos`, `funcoes`, `colaboradores`, `obras`. Valores financeiros inteiros em centavos. Consultas em lote, sem HTTP por card nem consultas por colaborador. Leitura em transação read-only; autorização igual à página individual; respostas sem cache. O livro é limitado às origens elegíveis, incluindo liquidações de outros meses.

Mês/ano são globais. Busca, função, obra e somente pendentes filtram exclusivamente os colaboradores da tabela. Função/obra selecionam pessoas que tenham itens correspondentes; os valores de cada linha continuam representando o total integral do colaborador na competência, para manter comparabilidade com o detalhe. Ranking e nome do colaborador abrem o detalhe sem mudar a competência.

Total pode diferir de pago+pendente em registros inconsistentes. Excesso pago é explicitado, valores são preservados e a barra financeira fica indisponível em vez de forçar percentuais. Itens de valor zero permanecem pendentes conforme a classificação existente.

Evolução semanal adiada: o critério de competência combina prazo e eventos de status, sem uma única data de reconhecimento do custo. Não há gráfico baseado em data arbitrária.

## Validação

`php tests/pagamento_resumo_test.php`: 24 verificações de snapshots, parcelas, complemento, comissão separada, pagamento em outro mês, excesso, duplicidade, valor aprovado, tarifa divergente, flag sem livro, negativos, zero e competência vazia. `php tests/custos_v2_test.php`: 338 verificações passaram. Lint PHP e sintaxe Node para arquivos alterados.

Na sessão local autenticada de `localhost:8066`, setembro/2026 apresentou 487 itens, custo R$ 49.335,00, pago R$ 11.040,00 e pendente R$ 38.295,00; 14 registros de adendos, dos quais 7 não assinados. Esses são resultados observados, não constantes de implementação. Bruna, Marcio (gestor), Adriana, Rafael e André Tavares coincidiram entre dashboard e detalhe nos valores total/pago/pendente e quantidade de itens. Confirmadas aba Pagos, busca local, somente pendentes, preservação dos KPIs ao filtrar, acesso/refresh de colaborador inativo e estados vazios em setembro/2022. Não foram executadas mutações financeiras durante a validação.

Inspeção visual em viewport desktop de 1912×866, sem overflow horizontal da página. O controle de viewport do navegador conectado não aplicou overrides de 1920×1080 ou 390×844; os breakpoints menores foram implementados em CSS, mas não puderam ser confirmados visualmente nesta sessão.

## Temas e animações

`visao-geral.css` define a paleta dark e a paleta light completas, respeitando `data-theme` explícito na raiz e, na ausência dele, `prefers-color-scheme`. Destaque do custo, avatar, aviso, logo e texto sobre os gráficos usam tokens dependentes do tema. A visão individual e o modal compartilham os mesmos tokens.

`animacoes.js` concentra a apresentação com GSAP 3.13.0: contagem de valores e percentuais, preenchimento das barras por transform, revelação da barra financeira por clip e transição breve do conjunto de indicadores. Os dados e cálculos do backend não mudam. `gsap.matchMedia` respeita movimento reduzido; sem biblioteca, todos os valores continuam aparecendo imediatamente. Troca de competência/visão interrompe e limpa as animações. Leitores de tela recebem o valor final por rótulo acessível, sem anunciar cada frame.

Validado no navegador: progressão dos indicadores, valores finais iguais aos rótulos do backend, ausência de erros no console e troca repetida entre dashboard e Adriana com os totais corretos. Tema light revisado em uma captura estática do mesmo DOM e dos dados reais do dashboard, com `data-theme="light"`, enquanto o navegador usa dark. Verificações isoladas da apresentação cobriram precisão dos centavos, valores finais, recarga com total igual, movimento reduzido e fallback sem GSAP. Capturas finais: `outputs/pagamento/visao-geral-light.jpg` e `visao-geral-dark.jpg`.
