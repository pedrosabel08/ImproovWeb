# Mapa arquitetônico — Fase 2

## Integração experimental com empreendimento 3D

Disponível somente em `predio-3d/visualizador-glb-navegacao.html?obra_id=74`. Nenhum controle foi acrescentado à tela da obra. O teste usa o modelo `Projeto-TES_TES.glb`, cadastrado para TES_TES. O subtipo Lazer existente é `id=3`.

`mapa_modelos_3d` registra a identidade do arquivo por obra. `mapa_modelo_pavimentos` relaciona `(modelo_id, object_name)` ao `subtipo_id`, com autor, datas e versão para evitar sobrescritas concorrentes. A obra vem do modelo; não há nova entidade de pavimento. Objetos `PAVTO_LAZER_01_001` etc. formam a identidade `PAVTO_LAZER_01`; um grupo ou mesh com esse nome também é aceito. A identificação do GLB não faz correspondência textual com nomes de subtipos.

O primeiro clique abre o vínculo e lista somente subtipos pertencentes à obra por imagens, referências ou vínculos existentes. Depois de salvar, Entrar no mapa destaca o pavimento, desvanece o restante do modelo, aproxima a câmera em vista superior e faz crossfade para a própria tela `MapaPavimento/index.php?embed=3d&obra_id=...&subtipo_id=...`. O iframe usa os componentes e a API existentes; o modo incorporado fixa o contexto e remove somente a navegação externa. Mensagens entre as telas validam origem, janela, identificador de abertura e contexto; a transição aguarda o PDF e as demarcações carregarem. O render 3D fica pausado enquanto o mapa está visível.

Voltar ao empreendimento valida se há edição aberta, desfaz o crossfade e restaura câmera (inclusive zoom), visibilidade e controles. Alterar vínculo está disponível no painel e no mapa. Um subtipo sem referência mostra o estado vazio com link para Configurar mapa no módulo existente, com obra/subtipo selecionados.

Para cadastrar o GLB de outra obra, colocar o arquivo no diretório `predio-3d` e executar a migração idempotente abaixo. Ela registra o modelo, sem inventar subtipos ou criar vínculos automaticamente. A interface permite selecionar as obras autorizadas e os respectivos modelos cadastrados. `?src=arquivo.glb` sem `obra_id` mantém o visualizador de diagnóstico separado da integração e não persiste vínculos de arquivos arbitrários.

```powershell
C:/xampp/php/php.exe scripts/migrate_mapa_modelos_3d.php --obra=74 --arquivo=Projeto-TES_TES.glb --nome=TES_TES
C:/xampp/php/php.exe tests/mapa_modelos_3d_test.php
# Testes de banco criam um modelo temporário e removem somente essa fixture:
C:/xampp/php/php.exe tests/mapa_modelos_3d_test.php --integration
```

API autenticada compartilhada: `modelos_3d` (GET, obra), `modelo_3d_contexto` (GET, obra/modelo), `modelo_3d_vincular` (POST, obra/modelo/object_name/subtipo_id/versao). Escrita preserva a permissão de níveis 1/2 e CSRF do módulo. Valida modelo da obra, presença do pavimento na cena GLB e pertencimento do subtipo à obra.

QA em 13/10/2026 pelo navegador interno, autenticado em `https://improov/ImproovWeb/`: vínculo inicial Lazer, persistência após recarga, clique direto no prédio e pela lista, transição para o PDF com layers/área útil/Salão de festas e suas três imagens, retorno completo, bloqueio de saída durante edição e alteração de vínculo a partir do mapa. Estado sem mapa e gravação de alteração de vínculo foram testados com modelo temporário, posteriormente removido; o vínculo real do modelo 1 permanece `PAVTO_LAZER_01 → subtipo_id=3`. Layouts conferidos em 1920×1080, 1366×768, 1024×768, 768×1024 e 390×844, com correções da barra de visualização e painel de ambientes em telas estreitas. A navegação normal por Ferramentas → Mapa de pavimento → TES_TES → Lazer também foi conferida. Testes PHP existentes, testes de identificação/isolamento/persistência/conflito/ausência de mapa e sintaxe PHP/JS passaram.

Acesso por **Ferramentas → Mapa de pavimento**. O módulo complementa as imagens do Flow e permanece independente de `MapaCompatibilizacao`.

## Fluxo

Obra → pavimento/subtipo existente ou criado pelo Select2 → documento da obra → página → layers → área útil poligonal → ambientes → imagens da obra.

Um documento pode atender vários subtipos. PDFs de uma página são vinculados automaticamente; documentos com várias páginas abrem miniaturas com os nomes dos subtipos que já utilizam cada página. Compartilhar uma página exige um aviso, sem bloqueio ou exclusividade. O seletor mostra total de páginas e quantidade de páginas distintas utilizadas.

Reenviar conteúdo idêntico na mesma obra reutiliza o documento por SHA-256 e informa o usuário. Mesmo nome com conteúdo diferente é aceito. O original não é regravado, dividido ou convertido.

## Estrutura e reaproveitamento

| Estrutura | Responsabilidade |
|---|---|
| `subtipo_imagem` | Fonte canônica de pavimento/subtipo, compartilhada com a tela da obra. |
| `imagens_cliente_obra` | Todas as imagens disponíveis por `obra_id`, independentemente do subtipo. |
| `mapa_documentos` | PDF privado da obra, nome, tamanho, páginas, hash, autor e datas. Único por obra/hash. |
| `referencias_arquitetonicas` | Configuração por obra/subtipo: documento, página, layers, área útil e versão. `UNIQUE(obra_id, subtipo_id)`. |
| `ambientes` | Identidade, nome, `referencia_id`, autor, datas e versão de edição. |
| `ambiente_demarcacoes` | Polígono do ambiente e referência correspondente. |
| `ambiente_imagens` | Relação N:N com imagens existentes; uma imagem pode estar em vários ambientes. |

Não existe `UNIQUE(documento_id, pagina)`. A antiga `obra_pavimentos` deixa de existir após a migração verificada. Configurações sem correspondência inequívoca ficam com `subtipo_id=NULL`, preservadas e disponíveis para visualizar/associar pela interface; nenhum subtipo é inventado automaticamente.

O cadastro de subtipos foi extraído para `helpers/subtipo_imagem_helper.php`, usado tanto pelo endpoint existente `Dashboard/getSubtipos.php` quanto pelo mapa. Preserva os grupos Obra/Demais e respostas do Dashboard, valida nomes e evita duplicação concorrente. O Select2 segue o padrão da tela da obra.

O viewer reaproveita a abordagem PDF.js/canvas/SVG do mapa antigo e os componentes do MVP. Zoom, pan, fit/reset, pinch, teclado, limite de resolução, cancelamento e ResizeObserver continuam encapsulados em `planta-viewer.js`. `planta-polygon-editor.js` é compartilhado por área útil e ambientes, incluindo fechamento, arraste, inserção em bordas, adicionar/remover vértices e redesenho. Os controles ficam fora da planta para não encobrir pontos no mobile.

## Coordenadas

Área útil:

```json
{"version":3,"coordinate_space":"page_normalized","points":[[0.1,0.2],[0.8,0.2],[0.9,0.6],[0.7,0.8],[0.1,0.8]]}
```

Pontos relativos à página exibida pelo PDF.js, considerando rotação/CropBox, origem superior esquerda e x/y de 0 a 1. `NULL` significa página inteira. Recortes retangulares anteriores são convertidos para polígonos compatíveis na leitura.

Ambientes:

```json
{"version":2,"coordinate_space":"useful_area_normalized","points":[[0.1,0.2],[0.4,0.2],[0.3,0.5]]}
```

Os pontos são relativos à **caixa delimitadora** da área útil, não ao interior irregular do polígono. Sem área útil, a caixa é a página inteira. Para converter à página: `xPagina = caixa.x + x * caixa.width`, `yPagina = caixa.y + y * caixa.height`. Essa transformação permite consumo futuro em outros viewers; nenhum alinhamento GLB é implementado aqui.

A máscara CSS `clip-path: polygon(...)` recorta canvas e SVG conjuntamente. O backend valida pontos, limites, área, repetições, interseções e contenção de todas as arestas, inclusive em áreas côncavas. Até 200 vértices. O último ponto não repete o primeiro.

Geometrias legadas v1 (`normalized`, página inteira) continuam legíveis sem mutação na consulta. Salvar, ampliar ou limpar a área útil recalcula os ambientes na mesma transação, mantendo sua posição física na página. Uma alteração que corte um ambiente é recusada integralmente. Trocar documento/página em um mapa com ambientes também é recusado, evitando deslocar demarcações para outra planta; primeiro é necessário remover as demarcações desse mapa.

## Layers e inspeção

PDF.js local **3.11.174**, com `getOptionalContentConfig()` e `optionalContentConfigPromise` em todas as renderizações. Os nomes e IDs OCG vêm do PDF. O padrão do mapa é todas as camadas visíveis. Checkboxes são persistidos por configuração; hover/foco isola temporariamente uma camada e restaura o estado anterior sem modificar a seleção.

`planta-layer-inspector.js` compara renders isolados com um render sem OCGs e guarda máscaras binárias limitadas em resolução/memória. O ponteiro consulta essas máscaras nas coordenadas da página e lista todas as camadas detectadas, inclusive sobreposições. É uma **aproximação visual**, não uma extração semântica de objetos PDF; elementos muito finos, efeitos de transparência, expressões de visibilidade e documentos muito densos podem reduzir a precisão. Conteúdo sem OCG não é atribuído a uma camada. PDFs achatados mostram uma mensagem explícita e desabilitam a inspeção.

## Imagens e compatibilidade

Novas seleções vêm de `imagens_cliente_obra WHERE obra_id=?`, incluindo imagens de qualquer subtipo e sem subtipo. O subtipo continua representando o pavimento do mapa; o ambiente possui seu próprio nome. Nenhum nome de imagem é criado pelo mapa e os vínculos existentes são preservados.

O Select2 permite buscar pelo nome e selecionar várias imagens. Separa imagens sem vínculo em outros ambientes das já vinculadas e mostra os nomes dos ambientes/pavimentos relacionados na obra, desconsiderando o próprio ambiente em edição. Essa classificação é informativa: uma imagem pode estar em vários ambientes, inclusive de pavimentos diferentes. Listagem e gravação validam a obra, sem alterar o subtipo original da imagem. A classificação é atualizada após salvar ou excluir um ambiente.

Nome, geometria e vínculos são salvos atomicamente. Alterar/excluir ambientes exige `versao_edicao`; desenhar exige `versao_referencia`; preparar/trocar página exige `versao_config`. Conflitos retornam HTTP 409. A leitura do mapa usa snapshot consistente.

## Instalação e migração

```powershell
C:/xampp/php/php.exe scripts/migrate_mapa_pavimento.php
# Para associar explicitamente IDs antigos a subtipos existentes:
C:/xampp/php/php.exe scripts/migrate_mapa_pavimento.php --associar=ID_LEGADO:ID_SUBTIPO
```

O runner CLI aplica a estrutura e migração retomável da Fase 2. Não há DDL pela interface. Antes de executar em outro ambiente, manter backup do banco e dos arquivos originais. Cada linha antiga de pavimento e metadados da referência fica em `referencias_arquitetonicas.legado_json` para auditoria, antes da remoção da tabela antiga. PDFs passam a `mapa_documentos`; IDs de referências, ambientes, geometrias e links são preservados. Associações automáticas exigem nome correspondente no catálogo existente; colisões ficam pendentes.

No desenvolvimento em 09/10/2026, por orientação do usuário: legado 1 “Homologação Mapa 20261009” → subtipo 1 “Térreo”; legado 2 “Pavimento lazer” → subtipo 3 “Lazer”. Os três ambientes anteriores, seus polígonos, links e hashes dos PDFs foram comparados com o snapshot anterior. Reexecução da migração validada.

Poppler existente: `tools/poppler/poppler-25.12.0/Library/bin/pdfinfo.exe` no Windows ou `pdfinfo` no PATH. `IMPROOV_PDFINFO_BINARY` pode indicar outro executável; FPDI existente é fallback. Select2/jQuery seguem as bibliotecas já usadas no Flow. Limite de upload: 30 MB; configurar `upload_max_filesize` e `post_max_size` compatíveis.

PDFs em `uploads/referencias_arquitetonicas/` são privados. Manter o `.htaccess` que bloqueia acesso direto; em outro servidor, configurar restrição equivalente. Downloads passam pela API autenticada e validam obra. Arquivos originais precisam acompanhar backups do banco.

## API

`MapaPavimento/api.php?action=...`. Sessão Flow, obras permitidas por `obterObras()`, leitura autenticada, escrita para níveis 1/2 com `X-CSRF-Token` emitido na página.

| Ação | Método | Dados principais |
|---|---|---|
| contexto | GET | obra_id |
| subtipo_salvar | POST JSON | obra_id, nome |
| documento_upload | POST multipart | obra_id, pdf |
| documento_pdf | GET | obra_id, documento_id |
| paginas | GET | obra_id, documento_id |
| pagina_vincular | POST JSON | obra_id, subtipo_id, documento_id, pagina, versao_config, confirmar_reuso |
| legado_associar | POST JSON | obra_id, subtipo_id, referencia_id |
| mapa | GET | obra_id, subtipo_id; ou legado_id para configuração pendente |
| imagens | GET | obra_id, subtipo_id, referencia_id opcional; imagens da obra com lista de ambientes vinculados; preservadas vazio por compatibilidade |
| referencia_configurar | POST JSON | obra_id, subtipo_id, referencia_id, versao_config, layers, area_util (inclusive NULL) |
| ambiente_salvar | POST JSON | obra_id, subtipo_id, referencia_id, versao_referencia, nome, geometria, imagem_ids; id/versao_edicao para editar |
| ambiente_excluir | POST JSON | obra_id, subtipo_id, referencia_id, id, versao_edicao |

URL de reabertura: `obra_id` + `subtipo_id`. Bookmarks anteriores com `pavimento_id` são resolvidos pelos metadados migrados.

## Verificação

```powershell
C:/xampp/php/php.exe tests/mapa_pavimento_rules_test.php
C:/xampp/php/php.exe tests/mapa_pavimento_preparacao_test.php
C:/xampp/php/php.exe tests/mapa_pavimento_pdf_fixture.php
```

Testes cobrem polígonos válidos/côncavos, limites, autocruzamento, arestas atravessando vazios, conversão e preservação de posição, layers, nomes, links e PDFs reais de uma/várias páginas. Fixtures sintéticas incluem OCGs sobrepostos, PDF achatado e rotação/CropBox.

QA autenticado na obra TES_TES/teste (74), em 09/10/2026: Select2/criação, documento existente/upload, miniaturas, página compartilhada, hash idêntico e mesmo nome com conteúdo diferente, página única automática, layers/preview/inspeção sobreposta, área útil e vértices, ambientes 0/N imagens, edição, exclusão, zoom/pan, fit/reset e recuperação após recarga. Negativos: contexto incompatível, imagens de outro subtipo, área externa, concorrência e CSRF. Layout conferido em desktop, notebook, iPad landscape/portrait e mobile, claro/escuro; auditoria axe sem violações detectadas na tela e seletor de páginas (checagens manuais de contraste/ARIA ainda aplicáveis).

O navegador interno falhou ao iniciar nas duas URLs oficiais; a validação prática utilizou agent-browser/Chromium no ambiente HTTPS oficial. Evidências ficam ignoradas em `tmp/mapa-pavimento-qa/`. Dados temporários de QA são removidos sem alterar os registros anteriores.

QA das imagens em 13/10/2026, autenticado em `http://localhost:8066/ImproovWeb/`, obra TES_TES/teste (74): todas as 8 imagens disponíveis no Lazer, busca com/sem resultados, seleção múltipla, criação/edição e persistência após recarga. Uma imagem foi compartilhada entre dois ambientes temporários no Lazer e a Brinquedoteca existente no Térreo; remover um dos vínculos preservou os demais. Salvar sem imagens também foi validado. Imagem de outra obra retornou HTTP 422 sem alterar o ambiente. Layout conferido em 1920×1080, 1366×768, 1024×768, 768×1024 e 390×844; temas claro/escuro, Escape e clique em salvar com a lista aberta. Auditoria axe final sem violações detectadas, com verificações de contraste pendentes de análise manual. Testes PHP existentes e verificações de sintaxe PHP/JS passaram. O navegador interno falhou na inicialização em ambas as URLs; a alternativa usada foi agent-browser/Chrome na segunda URL oficial.
