# Histórico da obra: análise e redesign

## Implementação anterior

| Responsabilidade | Arquivos / comportamento |
| --- | --- |
| Página e header da obra | `Dashboard/obra.php`, `Dashboard/obra-navigation.js` e `.css`; atalhos para Tabela, Mapa, Histórico, Mais e Flow Review |
| Lista, cache e ações | `Dashboard/scriptObra.js`, `Dashboard/styleObra.css` |
| Leitura principal | `Obras/getAcompanhamentoEmail.php?idobra=…` |
| Imagens entregues | `Obras/getItensEntregaPorAcompanhamento.php?acomp_id=…`, popover em `Dashboard/popoverAcomp.css` |
| Novo / edição | `Dashboard/addAcompanhamento.php` via POST; mesmo formulário e sugestões predefinidas, texto livre e data |
| Exclusão | `deleteAcompanhamento.php`, confirmação; anteriormente apenas menu de contexto |
| PDF próprio | `Obras/historico_pdf.php`, Dompdf, categoria selecionada |
| PDF geral da obra | `gerarFollowUpPDF()` em `Dashboard/scriptObra.js`; anteriormente lia assunto/data do DOM |
| Configurações | `unifyAcompanhamentos.php?action=list/unify`; encontra duplicados e oferece unificação persistente após confirmação explícita |
| Arquivos da obra | `FlowDrive/getArquivos.php?obra_id=…`, `FlowDrive/visualizar_pdf.php?idarquivo=…` |

A lista retornava `{id, assunto, data, ordem, tipo}`, por data e ID decrescentes. Os filtros locais eram Todos, Manuais, Entregas e Arquivos. `entrega`/`entregas` e `arquivo`/`arquivos`/`file` recebiam categorias próprias; os demais tipos caíam em Manuais. A lista era cortada em 200px e podia ser expandida. A descrição permitia edição inline e o botão direito oferecia exclusão.

`+ Novo` continua sujeito à regra existente de visibilidade (usuários 1, 2 e 9). O redesign mantém o formulário, endpoints de gravação e regras da página. As leituras do histórico e PDF agora verificam sessão e o mesmo acesso à obra usado por `obra.php`.

## Dados existentes e limites

- `acompanhamento_email` também pode ter `status`, `metadata`, `colaborador_id`, `entrega_id` e `arquivo_id`. As colunas opcionais são detectadas por leitura de schema, sem migração.
- `data` é `DATE` nos produtores atuais. Datas sem hora aparecem como **Sem horário**, sem inventar horário de criação.
- O formulário manual legado grava `colaborador_id=1` fixo. Por isso a interface não atribui autoria a registros manuais. Tipos operacionais que gravam o colaborador da sessão podem exibir o nome real. A foto vem de `informacoes_usuario.thumb`, vinculada por `usuario.idcolaborador`; usa o ícone se estiver ausente ou falhar.
- `status` do acompanhamento pode ser uma pendência interna de processamento. Ele fica disponível para busca, mas não é apresentado como status operacional da entrega. A etapa vem de `status_imagem`; o status da entrega vem de `entregas`.
- Onboarding usa `PROJECT_START`, `SLA_DEFINED`, `GROUPS_CREATED`, `IMAGES_IMPORTED`, `IMAGES_EXTRAS_ADDED` e `ONBOARDING_COMPLETED`. O handoff usa `P00_HANDOFF`. Há ainda `fotografico` e tipos legados. A classificação visual não altera esses valores.
- `metadata` de onboarding pode conter pacotes, observações, cliente, contatos, importação e parâmetros comerciais. Somente informações pertinentes à timeline (pacotes, observações e ambiente/grupo) são expostas nos cards; não se despeja JSON técnico ou dados comerciais completos.
- Entregas são vinculadas por `entrega_id`. São recuperadas em lote, sempre limitadas à obra, com etapa, situação atual, data de recebimento, conclusão e quantidade de itens. **Recebimento não é chamado de registro**. Os dados complementares descrevem a entrega vinculada no estado atual, não um snapshot histórico inexistente.
- P00 usa `entregas_p00_versoes`, não os itens padrão. Não se mostra uma contagem zero calculada a partir da tabela errada. A consulta de itens do popover continua usando o endpoint existente, inclusive seu fallback legado por feed/data.
- O upload do FlowDrive agrega a descrição por categoria/formato e grava apenas o primeiro arquivo em `arquivo_id`. O banco de arquivos contém `nome_original`, `nome_interno`, `caminho`, `tipo`, `tipo_imagem_id` e dados de recebimento. Os binários são enviados por SFTP; os caminhos podem ser locais, URLs públicas, caminhos com `/public_html/` ou `uploads/`.
- Só o arquivo explicitamente vinculado é mostrado. Não se reconstrói a lista inteira do lote por data, nome ou proximidade. PDFs usam o visualizador existente; imagens com caminho público recuperável usam `thumb.php` em tamanho reduzido. Caminhos privados não resolvíveis mantêm nome e acesso à seção de arquivos da obra.
- Entregas padrão mostram previews dos itens vinculados, procurando no histórico visual a versão mais recente até o dia do envio do item; os itens ainda não enviados usam a prévia disponível atualmente. Não há snapshot exato associado a cada evento. P00 utiliza os IDs explícitos em `historico_id` e `arquivos_json`, limitados à mesma obra. A resposta inclui no máximo cinco previews e o total para a indicação `+N`. O nome fica no tooltip da miniatura.

## Implementação nova

- `assets/js/components/obra-history.js`: apresentação, classificação, busca sem acentos, períodos, grupos diários em português, resumo, contadores, anexos e lotes de DOM.
- `assets/css/components/obra-history.css`: escopo `.history-section`, tokens dos temas existentes, Inter, timeline compacta, sidebar de 280px/260px, adaptação para mobile, foco visível e reduced motion.
- `Dashboard/obra-history-controller.js`: ponte com edição, exclusão, popover de entrega, arquivos e PDF existentes.
- `Obras/historico_data.php`: consultas de leitura em lote para enriquecer o retorno existente; nenhuma alteração de schema ou escrita.

A busca considera descrição, tipo visual e original, ambiente/grupo, etapa, status, pacotes, observações, autor conhecido e nome de arquivo vinculado. Os botões de categoria contam os registros sujeitos a busca/período, antes da seleção da própria categoria. O resumo e os tipos contam os registros efetivamente filtrados. A lista lateral de períodos respeita busca/categoria, antes do filtro do próprio período. Select e sidebar compartilham o mesmo estado.

Os períodos são inclusivos a partir da meia-noite local: hoje e os seis/29 dias anteriores, ou os últimos três meses de calendário com ajuste de fim de mês. Registros sem data só aparecem em Todo o período. Datas futuras não entram nas janelas recentes.

O limite inicial é de 80 eventos; “Mostrar mais” adiciona outros 80. A timeline tem rolagem interna, com altura máxima de 70% do viewport ou 900px, e cabeçalhos diários sticky dentro dessa área. Todos os dados continuam em memória para busca, contagens e PDFs completos. Os cards são reutilizados entre filtros, a busca tem debounce e miniaturas têm `loading=lazy`, dimensões explícitas e limite inicial de cinco. Não foi adicionada paginação de servidor ou infinite scroll estrutural.

Os tipos da sidebar são botões de filtro. Selecionar um tipo sincroniza a categoria superior; clicar novamente no mesmo tipo limpa a seleção, e clicar numa categoria superior limpa o subtipo. O filtro também acompanha os PDFs. Edição e exclusão só são iniciadas pelo menu de três pontos. Abrir outro menu fecha o anterior; clique externo e Escape também fecham o menu. Clicar no título ou usar botão direito não dispara essas ações.

A consolidação visual exige `metadata.upload_batch_id` ou `metadata.lote_id`, mesmo dia, grupo, ambiente e categoria. O produtor atual não grava essas chaves: seus eventos permanecem separados. O componente está preparado para recebê-las. A expansão mantém os registros individuais e suas ações. **Não chama a unificação persistente nem altera registros.** O botão de configurações conserva a ferramenta anterior, separadamente.

O PDF próprio recebe por POST os IDs filtrados, incluindo eventos além dos 80 renderizados. O servidor intersecta a seleção com a consulta autorizada da obra. A URL GET antiga continua válida. O relatório geral também usa a coleção filtrada completa em vez do DOM paginado. A edição preserva `tipo`; criar, editar, excluir ou unificar deixa de reutilizar dados desatualizados do cache.

## Validação

- `node tests/obra_history_test.cjs`: classificação, busca por múltiplos termos/acentos/autor/grupo, preservação de DATE no fuso local, períodos e fim de mês, ordenação, ausência de agrupamento inseguro e manutenção dos registros originais.
- `node tests/obra_history_controller_test.cjs`: preservação do tipo ao editar, retorno ao texto original em falha, rejeição de texto vazio, cancelamento da exclusão e inclusão dos 200 eventos filtrados no POST do PDF.
- `php tests/obra_history_paths_test.php`: caminhos públicos de previews e fotos, URLs de miniaturas, extensão de imagem e rejeição de caminhos inválidos, sem banco ou SFTP.
- `node --check` nos três scripts envolvidos e `php -l` nos PHP alterados.
- `tests/fixtures/obra-history-preview.php`: prévia isolada sem banco ou ações operacionais, reutilizando o HTML e CSS reais do histórico. Conferida no navegador em desktop, light/dark e mobile; sem overflow horizontal em 390px, controles de período sincronizados, estado vazio e progressão 80 → 160 entre 200 eventos.
- Criação/exclusão/unificação reais e geração Dompdf com sessão/banco de produção não foram executadas nesta validação. Os fluxos foram preservados e revisados no código, sem modificar dados reais.

Prévia local: `http://localhost:8066/ImproovWeb/tests/fixtures/obra-history-preview.php` (porta do Apache desta máquina).
