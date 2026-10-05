# Pagamento / Adendos — FASE 1C-B

## Registro do modelo antes da migration

Modelo registrado em 02/10/2026 antes de criar/aplicar SQL documental. Referências obrigatórias: fase0, regras-alvo D01–D07, fase1a, fase1b e fase1c-a. Convenções: PHP/mysqli, migrations datadas, InnoDB, BIGINT UNSIGNED, JSON e FK composta com a revisão/fechamento existentes. Não alterar migration 1C-A nem storage legado.

Modelo mínimo proposto:

- `pagamento_fechamento_documento`: reserva com ID/UUID e número documental por fechamento, FK forte `(revisao_id, fechamento_id)`, hash financeiro, modelo/HTML congelados, hash do template, caminhos privados únicos de staging/definitivo; estado público PREVIEW/CONFIRMADO; estado NULL somente enquanto reserva interna não é publicada; SHA-256/tamanho dos bytes, autoria e confirmação.
- `pagamento_fechamento_documento_operacao`: journal de geração/visualização/confirmação, chave única por usuário, hash da requisição, documento/ator, estado RESERVADA/CONCLUIDA, antes/depois, UTC microsegundos. Reserva imutável nos inputs e transição única para conclusão; conclusão append-only. Sem repetir logs em outro storage.

Reserva transacional precede filesystem; cada arquivo pertence a uma reserva já commitada. Paths derivados exclusivamente do UUID/IDs no backend; diretório privado fornecido por configuração confiável, fora de acesso HTTP direto. Geração grava bytes em `.part`, fsync, promove sem sobrescrita e publica metadados PREVIEW. Recuperação usa o mesmo modelo/HTML/caminho reservado e reaproveita artefato já completo, sem nova renderização.

Confirmação reserva journal antes de publicar; valida documento/revisão/hash financeiro/bytes/hash esperado e visualização pelo mesmo ator. Copia os bytes do preview para definitivo de nome único, conservando preview. Falha de DB depois da cópia deixa publicação identificada pelo journal; retry/reconciliação valida bytes existentes e finaliza metadados. CONFIRMADO e operações concluídas são protegidos por triggers contra alteração/delete. Não se presume rollback MySQL de rename/copy.

Autorização reutiliza usuário ativo nível 1/5 da 1C-A. Documentos/previews coexistem; revisão mais nova não substitui a vinculada. Sem envio, assinatura, endpoints produtivos ou troca da tela. Migration será homologada, junto com a 1C-A intacta, somente em MySQL 8 isolado com fixtures sintéticas. Rollback operacional preserva histórico; SQL destrutivo restrito a testes descartáveis.

**Resultado em 02/10/2026: FASE 1C-B CONCLUÍDA no escopo paralelo autorizado.** Migrations aplicadas exclusivamente em bancos sintéticos descartáveis; integração produtiva da tela permanece para outra fase.

## 1. Arquitetura documental

Referências: [FASE 0](pagamento-adendos-fase0.md), [regras-alvo](pagamento-adendos-regras-alvo.md), [1A](pagamento-adendos-fase1a.md), [1B](pagamento-adendos-fase1b.md), [1C-A](pagamento-adendos-fase1c-a.md). D01–D07 e os motores financeiros permanecem intactos nesta fase.

```mermaid
flowchart LR
    R[Revisão PRONTO imutável] --> V[Validar snapshot e versões]
    V --> M[Modelo e HTML congelados]
    M --> P[PDF persistido PREVIEW]
    P --> L[Leitura por document_id e hash]
    L --> C[Confirmar ID, revisão e SHA esperados]
    C --> D[CONFIRMADO com os mesmos bytes]
```

`FechamentoDocumentoService` coordena a API interna; `Repository` concentra SQL/autorização; `Projection` transforma snapshot em apresentação; `Files` controla paths, locks, selagem e publicação. `AdendoDocumentalApresentacao` contém apenas formatação/datas/qualificação. A conexão é dedicada, com autocommit e transações curtas REPEATABLE READ; início, commit e rollback não são presumidos bem-sucedidos. Não há chamada a `calcular()`/`compor()` durante o ciclo documental.

## 2. Schema e migrations

[Migration documental](../sql/2026-10-02_pagamento_fechamento_documento.sql) e [rollback](../sql/2026-10-02_pagamento_fechamento_documento_rollback.sql) são separados da 1C-A. Modelo proposto acima precedeu sua criação/aplicação.

| Tabela | Identidade e vínculos | Restrições |
|---|---|---|
| `pagamento_fechamento_documento` | BIGINT UNSIGNED; fechamento; FK composta `(revisao_id,fechamento_id)`; criador/confirmador com FK usuário INT signed | Únicos por fechamento/número, UUID, preview, definitivo; número positivo; coerência de estado/hash/tamanho/instantes; índices revisão/estado e estado/data |
| `pagamento_fechamento_documento_operacao` | BIGINT UNSIGNED; FK documento e autor | Único `(autor_id,chave)`; índices pendências e visualizações; CHECK RESERVADA/CONCLUIDA e antes/depois/instantes |

Ambas são InnoDB/utf8mb4, JSON nativo, hashes ASCII, paths ASCII e FKs RESTRICT. Quatro triggers protegem base/bytes do documento e journal. Documento permite somente reserva NULL→PREVIEW→CONFIRMADO; CONFIRMADO rejeita qualquer UPDATE/DELETE. Operação aceita uma conclusão, conservando inputs, e depois rejeita UPDATE/DELETE. Não se cria estado de envio/assinatura.

O CHECK documental usa `COALESCE(expressão,FALSE)=1`: NULL/UNKNOWN não pode permitir PREVIEW sem tamanho obrigatório. Isso foi verificado no MySQL real. Reserva NULL não é um documento publicado; seu ID permite diagnóstico/recuperação.

Rollback destrutivo foi executado somente nos bancos descartáveis: remove a estrutura documental e conserva revisões financeiras e arquivos. Em operação, desativar o novo fluxo e preservar banco/storage; não executar rollback destrutivo com histórico a conservar.

## 3. Homologação MySQL 8

Oracle **MySQL Community 8.0.42 Windows x64**, `SELECT VERSION() = 8.0.42`, instância própria em `127.0.0.1:3320`, mysqlx desativado, datadir temporário `pagamento_1cb_runtime/data_1cb_mysql8`. Binário portátil, sem instalar serviço Windows nem alterar instâncias existentes. O banco compartilhado não recebeu DDL/DML desta fase.

A migration **original e intacta da 1C-A** e a nova 1C-B foram aplicadas a bancos aleatórios `pagamento_1cb_test_<10 hex>`, criados e removidos pelos testes. Validaram CREATE TABLE, InnoDB, JSON inválido rejeitado, CHECK efetivo, FKs, triggers, grants, writer, rollback e concorrência entre processos PHP distintos. O rollback de ambas também passou.

[Homologação writer 1C-A/MySQL 8](../tests/pagamento_adendos_persistencia_mysql8_test.php): **117 verificações**. Conserva os 113 cenários da suíte original, adiciona CHECK/JSON/grants e adapta apenas conexão/consulta nativa de locks/comparação de chaves de objetos. Os motores e o teste original não foram alterados.

Achados de compatibilidade: JSON nativo MySQL pode reordenar chaves de objetos. O adaptador compara objetos por chave, preservando ordem de listas, tipos e valores estritos; o hash financeiro já usa a canonização versionada da 1C-A. Locks reais foram observados em `performance_schema.data_lock_waits`, ligados a `performance_schema.threads.PROCESSLIST_ID`; não se usa a tabela de locks específica do MariaDB.

Contas locais sintéticas com SELECT/INSERT/UPDATE e sem TRIGGER foram recusadas pela conferência de proteção. Acrescentar TRIGGER no schema e **reabrir a conexão** permitiu os writers financeiro e documental. A conexão antiga conservava cache de privilégios. Migração exige conta de DDL apropriada; writer precisa SELECT nas origens/identidade, INSERT nos históricos, UPDATE no fechamento/documento/journal e visibilidade de triggers. As contas de teste tinham grants restritos ao schema e foram removidas. TRIGGER também concede poder administrativo sobre triggers: proteger essa credencial; essas garantias não pretendem resistir a administrador do banco/storage. Eventual redução desse privilégio exige adaptar a verificação de estrutura em fase própria.

A suíte original **113** foi repetida em MariaDB **10.4.32** isolado, loopback **3319**. A primeira tentativa perdeu o servidor temporário; sua reinicialização em sessão acompanhada recuperou o datadir e a suíte completa passou. MariaDB não foi usado como substituto da homologação MySQL.

## 4. Vínculo revisão → documento

Toda reserva recebe `revision_id` explícito e `fechamento_id`; FK composta impede revisão de outro fechamento. Antes da geração, verifica existência, escopo, estado PRONTO, schema/canonização/versões 1A/1B/R09 conhecidas, SHA financeiro, beneficiário/competência/instante coerentes, subtotal completo, total inteiro determinado e ausência de bloqueio/pendência bloqueante.

Leitura, listagem e confirmação validam novamente o vínculo e o hash da revisão persistida. `financial_snapshot_hash` identifica o snapshot; `pdf_hash` identifica os bytes. Não são intercambiáveis. Revisão antiga PRONTO continua válida e nunca é trocada pela atual. Modelo congelado conserva também o número da revisão financeira; metadados públicos incluem o ID exato.

## 5. Projeção snapshot → template

| Fonte congelada | Modelo/template |
|---|---|
| `financeiro_servicos.servicos_devidos[].descricao` | Imagem/função da tabela; identidade de origem conservada no modelo |
| `servicos_devidos[].saldo_centavos` | Valor da linha, sem nova seleção, tarifa ou cálculo de saldo |
| Identidade de classe COMISSAO | Rótulo documental “Comissão gestor”; valor já determinado pela 1A |
| `fixo.saldo_centavos` | Rubrica “Valor fixo”, inclusive zero determinado |
| `acompanhamento_especial.aplicavel/valor_centavos` | Rubrica “Acompanhamento”, separada do fixo e extras |
| `extras.itens[].categoria/valor_centavos/referencia` | Uma linha por extra, conservando categoria, referência e centavos |
| `total_final_centavos` | Total da cláusula 2 e valor por extenso; não se somam linhas para redefini-lo |
| Beneficiário/competência do snapshot | Identidade do modelo, título e competência contratual |
| Identidade jurídica atual, capturada uma vez na reserva | Qualificação, CPF/CNPJ, endereço e assinaturas visuais congelados |
| Regra documental IMPROOV/STELLAR e data da reserva | Contratante, data de pagamento e data documental congeladas |

Nenhuma consulta a tarefas, ledger, pagamentos ou `colaborador.valor_fixo` ocorre na projeção. Nome/dados jurídicos podem vir do cadastro somente na **primeira reserva**, sem campos financeiros; ambiguidades de cadastro são recusadas. Alteração posterior desses dados não altera documento existente. Um novo documento de revisão antiga captura a identidade jurídica então disponível, mantendo o financeiro da revisão antiga; essa distinção é explícita.

## 6. Reutilização/refatoração do PDF

`ContratoPdfService::renderizarHtml()` extrai a mesma renderização Dompdf existente e retorna bytes. A API antiga `gerarPdf()` continua aplicando template, escolhendo basename real/sufixo/fallback e gravando como antes. Teste de paridade inspeciona o texto PDF de ambas as APIs e confirma que colisão mantém o arquivo anterior e retorna o nome real.

Template `adendo_modelo.html`, Dompdf **3.1.4**, A4 portrait, fonte Roboto local, opções de chroot/cache/remote e conteúdo jurídico permanecem iguais. Não se instancia `AdendoLocalService`, não se usam suas consultas, cálculos, fallback ou persistência em `adendos`. Qualificação e competência reutilizam serviços puros existentes. Datas e número por extenso foram extraídos da apresentação do legado para helper isolado; moeda usa centavos inteiros.

## 7. Staging e paths

Raiz confiável fornecida pelo backend, **fora de acesso HTTP direto**, com `staging/`, `definitivo/`, `locks/`; CLI usa raiz temporária privada por banco de fixture. Configuração produtiva/ACL ainda deve ser definida na integração. O serviço não aceita raiz/path vindos do navegador.

Nome real: `adendo_f<fechamento>_r<revisao>_d<numero>_<uuid32>.pdf`. Ambos os paths são reservados/persistidos exatamente; não há sufixo escolhido posteriormente nem sobrescrita. Regex estrita, realpath dos diretórios e recusa de links/redirecionamentos bloqueiam traversal, caminhos absolutos e escape da raiz. O lock UUID é cooperativo via `flock`; todas as operações do ciclo usam esse protocolo.

## 8. Hash do PDF

SHA-256 e tamanho são calculados dos **bytes efetivamente renderizados**; validação inclui cabeçalho `%PDF-` e término `%%EOF`. Antes de leitura/confirmação, o serviço relê o arquivo real, verifica SHA/tamanho e recusa corrupção ou ausência. Confirmado também passa pela verificação do arquivo definitivo.

Não há promessa de determinismo entre duas renderizações Dompdf. O primeiro PDF selado é autoritativo. Hash/instantes de confirmação não mudam em retry. As cópias em `output/pdf/fase1c-b/` são evidência sintética de QA; não são documentos operacionais vinculados a um banco permanente.

## 9. Geração

`gerarPreview(fechamento_id,revision_id,usuario,chave,data?)`: autoriza, confere estrutura, trava fechamento/ator, valida revisão, captura apresentação, reserva documento e operação em uma transação. Depois renderiza o HTML congelado sob lock de arquivo. Grava `.part` exclusivo, fflush/fsync quando disponível, calcula hash/tamanho e grava recibo de selagem com IDs, hashes financeiro/HTML/PDF e tamanho. Promove recibo e PDF sem sobrescrever. Outra transação publica PREVIEW e conclui a operação atomicamente.

Data ausente é resolvida uma única vez em America/Sao_Paulo; instantes de auditoria são UTC com microssegundos. Chave nova é a única forma de pedir outro preview explicitamente. Falha não altera pagamentos, ledger, cadastro financeiro ou `adendos` legado.

## 10. Preview e visualização

Preview é persistente, identificado por `document_id`; não depende de `$_SESSION['adendo_pendente']`. `visualizar(id,usuario,chave)` resolve os paths pelo banco, confere autorização/vínculo/bytes, audita esse documento/revisão/hash e retorna MIME/bytes. `obter` entrega metadados com verificação de arquivo quando publicado; `listar` valida os vínculos e entrega metadados, sem ler todos os PDFs.

Na CLI, visualização exporta cópia privada dos bytes verificados em `exports/`, com nome único. Confirmação continua lendo o artefato gerenciado, não a cópia exportada. Auditoria registra **leitura/entrega pelo backend**, não prova que o usuário humano leu o documento. A futura interface deve efetivamente exibir o arquivo recebido e enviar os identificadores esperados.

## 11. Confirmação

`confirmar(document_id,revision_id_esperado,pdf_hash_esperado,usuario,chave)` exige visualização concluída do mesmo documento pelo mesmo ator. Valida os três identificadores, estado, autorização atual, vínculo financeiro e bytes. Reserva operação de publicação antes de tocar no definitivo.

Sob lock, copia os bytes validados para definitivo exclusivo, fsync/promove e verifica eventual definitivo já existente. Não chama renderizador nem motor financeiro. Transação final persiste CONFIRMADO, ator/instante e conclusão auditada. Conserva preview para comparação. Teste confirma igualdade byte a byte entre arquivo visualizado e definitivo, mesmo após nova revisão financeira e alterações no cadastro/tarefas.

## 12. Idempotência

Chave ASCII de 1–128 caracteres, única por ator no journal documental. Request hash versionado inclui ator, ação e parâmetros explícitos. Mesma chave/request retorna o mesmo documento; chave com ação/conteúdo diferente é conflito. Retry de confirmação retorna sucesso, inclusive já CONFIRMADO, sem 422 por repetição e sem duplicar confirmação/auditoria.

Metadados retornam o estado atual do mesmo documento: retry de geração após confirmação pode retornar CONFIRMADO. Não recria o PDF nem retoma estado PREVIEW. Chaves não são apagadas nesta fase.

## 13. Concorrência

Fechamento serializa reserva/número documental, documento serializa transições, ator serializa uso da chave e `flock` serializa filesystem por UUID. Dois processos PHP foram bloqueados simultaneamente e observados no MySQL antes de liberar a linha: geração com mesma chave retorna um ID; confirmação concorrente retorna um CONFIRMADO e uma operação concluída.

Dois gestores podem gerar P1/P2 da mesma revisão com chaves distintas. Confirmar P1 conserva P2 em PREVIEW. V4 financeira posterior não substitui a V3 vinculada a P1. Nomes distintos de definitivos e hashes antigos preservados foram verificados. Não há seleção implícita do “último preview”.

## 14. Recuperação banco/filesystem

| Falha | Estado recuperável e ação |
|---|---|
| DB reservou; renderizador/gravação falhou | Reserva/journal persistem, estado público NULL; remover causa operacional e repetir a mesma chave/`recuperar` |
| `.part` incompleto antes de selagem | Sob lock, parcial não selado pode ser removido e renderizado; nunca foi PDF autoritativo |
| Recibo selado e PDF `.part` completos | Validar contexto/hash e promover o mesmo arquivo |
| PDF completo; publicação PREVIEW no DB falhou | Reserva identifica arquivo/recibo; retry reutiliza seus bytes e finaliza DB |
| Definitivo publicado; commit CONFIRMADO falhou | DB mantém PREVIEW e operação RESERVADA; validar definitivo existente e concluir, sem renderizar/copiar outra versão |
| Resposta CLI/HTTP perdida depois do sucesso | Mesma chave reconhece operação concluída e devolve o documento |
| Arquivo selado ausente/corrompido ou arquivo sem recibo | Bloqueio explícito; reconciliação operacional, sem regeneração silenciosa |

`pendentes(usuario)` lista journal reservado do ator; `recuperar(operacao_id,usuario)` reexecuta os parâmetros originais somente para esse ator autorizado. Falhas de renderização/filesystem, DB após preview e DB após definitivo foram exercitadas. Não se apagam reservas para esconder falhas. Receipts/partiais ficam associados a um documento já commitado; `rename` não é tratado como transacional com MySQL.

Cobertura comprova falhas de processo/SQL e retries; não simula perda física de energia/disco. Locks são locais/cooperativos; implantação em storage distribuído requer homologar suas semânticas. Backup/restauração deve manter banco e arquivos juntos. Administrador pode alterar/remover arquivo, mas leitura/confirmação detecta divergência e bloqueia.

## 15. Autorização e auditoria

Reutiliza server-side usuário ativo, nível **1 ou 5**, da 1C-A. Geração, visualização, confirmação, leitura/listagem e recuperação conferem o ator. Ator vem da sessão validada na futura integração ou da CLI confiável isolada, nunca de formulário financeiro como prova de permissão. Revalidação ocorre nos locks/transações de publicação.

Journal guarda autor, tipo, chave, hash da requisição, inputs, antes/depois e UTC. Hash/ID/revisão exatos aparecem no resultado auditado. Confirmados e conclusões são append-only no banco. Não foi criada regra distinta de preparador/aprovador.

## 16. Testes documentais e CLI

[Suíte documental](../tests/pagamento_adendos_documental_test.php): **194 verificações**, MySQL 8 real, sem conexão ao banco compartilhado. Cobertura inclui revisão PENDENTE, hash financeiro inválido, versão desconhecida com hash válido, lista de serviços ausente, linha de outro beneficiário, corrupção PDF, versões antigas, mudanças posteriores, coexistência, idempotência/conflito, concorrência real, recuperação, traversal, ACL, auditoria, imutabilidade, CHECK/FK/grants e rollback. Uma subclasse mysqli recusa consultas a tabelas financeiras atuais durante o ciclo principal; nenhuma consulta proibida foi executada. Inserts malformados são somente testes adversariais no banco descartável, não alterações em revisões existentes.

| Fixture | Conteúdo financeiro representado | Total |
|---|---|---:|
| A | Serviço 100 + fixo 1500 + SEM_BONUS; item quitado omitido | R$ 1.600,00 |
| B | Serviço/fixo de A + extras 100,50 e 200 | R$ 1.900,50 |
| C | Nicolle sintética limpa: serviço 75 + fixo 1500 + especial 4000 + extra 125 | R$ 5.700,00 |
| D | Comissão gestor já calculada 100; comissão quitada omitida | R$ 100,00 |
| E | Zero explicitamente determinado/PRONTO | R$ 0,00 |
| F | Revisão PENDENTE/total desconhecido | PDF recusado |

Também há fixture STELLAR, validando contratante/CNPJ segundo regra documental existente. `pdftotext -layout` inspeciona os PDFs reais: beneficiário, competência, imagem/função, valores, fixo, especial, extras, extenso, total, omissão de quitados, rótulo de comissão, datas e textos jurídicos principais.

[CLI isolada](../scripts/pagamento_fechamento_documento.php) implementa fixture, gerar, listar, obter, validar-hash, visualizar, confirmar, comparar, pendentes e recuperar. Todas as ações exigem `--isolado`; prefixo do banco e MySQL 8 loopback são validados. Não há modo de escrita no banco configurado nem input livre de caminho. Saída normal contém IDs/hashes/contagens, sem snapshot jurídico completo. `comparar` verifica hash físico e compara total/contagens do modelo congelado com a revisão; inspeção textual efetiva está na suíte PDF, não se confunde o DTO com o texto extraído.

```powershell
$fixture = php scripts/pagamento_fechamento_documento.php --acao=fixture --isolado | ConvertFrom-Json
$bancoFixture = $fixture.banco
$revisaoFixture = $fixture.revisoes.A
$preview = php scripts/pagamento_fechamento_documento.php --acao=gerar --isolado --banco=$bancoFixture --usuario=1 --fechamento=$($revisaoFixture.fechamento_id) --revisao=$($revisaoFixture.revision_id) --chave=preview-1 --data=2026-10-02 | ConvertFrom-Json
$leitura = php scripts/pagamento_fechamento_documento.php --acao=visualizar --isolado --banco=$bancoFixture --usuario=1 --documento=$($preview.document_id) --chave=ver-1 | ConvertFrom-Json
# Abra e confira o PDF em $leitura.copia_para_visualizar antes desta confirmação explícita.
php scripts/pagamento_fechamento_documento.php --acao=confirmar --isolado --banco=$bancoFixture --usuario=1 --documento=$($preview.document_id) --revisao=$($revisaoFixture.revision_id) --hash=$($leitura.metadata.pdf_hash) --chave=confirmar-1
```

Smoke CLI executado com fixture própria: gerar/listar/obter/validar-hash/comparar/visualizar/pendentes/confirmar/retry/recuperar passaram. Banco sintético da CLI foi removido após a validação. Os comandos não iniciam servidor MySQL; exigem instância isolada preparada. Testes esperam MySQL 8 em 3320; original 1C-A espera MariaDB em 3319. `PAGAMENTO_PDFTOTEXT` permite configurar Poppler; nesta sessão foi usado Poppler Windows **26.09.0** portátil. Dez arquivos PHP novos/modificados passaram por `php -l`.

## 17. Regressões

| Verificação | Resultado |
|---|---:|
| 1A offline | 230 OK |
| 1A read-only no banco configurado | 14 OK |
| 1B offline | 169 OK |
| 1B read-only no banco configurado | 26 OK |
| 1C-A original/MariaDB isolado | 113 OK |
| 1C-A/MySQL 8 isolado | 117 OK |
| Históricos/golden `--verify` | 1.312 OK |
| Custos | 329 OK |
| 1C-B/MySQL 8 isolado | 194 OK |
| Shadow 1A golden | 10 casos; 16 esperadas; 0 inesperadas |
| Shadow 1B golden | 3 casos; 5 esperadas; 0 inesperadas |
| Shadow 1A live auditado | 14 pares; 526 esperadas; 0 inesperadas |
| Shadow 1B live auditado | 5 pares; 8 esperadas; 0 inesperadas |

Pares live 1A: 27/set2026, 20/nov2025, 6/set2026, 8/set2026 e ago2026, 13/set2026 e ago2026, 1/jan2025 e ago2026, 7/ago2026, 4/ago2026, 33/ago2026 e set2026, 40/abr2026. 1B: 7/ago2026, 4/ago2026, 1/ago2026, 1/jan2025, 8/set2026. Consultas protegidas/auditadas somente leitura; saída consolidada somente em contagens. Golden não foi recapturada.

SHA-256 da golden: `86E743C3D3FF0BAF98AD40B74C2403EC3D884C3366D48501DEA1F00FCE535B20`. Baseline de 31 arquivos protegidos: 30 intactos, único delta autorizado em `ContratoPdfService.php` para extrair renderização pura. Nenhum motor/migration/teste prévio 1A/1B/1C-A foi alterado nesta fase.

## 18. Compatibilidade visual e validação prática

Cinco PDFs A–E de uma página A4, renderizados por Poppler e inspecionados visualmente: cabeçalho, textos, tabela, rubricas, alinhamento de valores, cláusulas e assinaturas sem cortes/sobreposição. Arquivos de evidência em `output/pdf/fase1c-b/fixture-A.pdf` até `fixture-E.pdf`, somente dados sintéticos de beneficiários. [Manifesto de QA](../output/pdf/fase1c-b/manifest.json) registra SHA-256/tamanho/totais. Os rasters do último run ficaram idênticos aos inspecionados. Qualificação do contratante é o conteúdo jurídico preexistente do template, conservado deliberadamente.

Necessidade técnica documentada: o legado incorporava fixo ao total sem rubrica própria; o modelo novo exibe **“Valor fixo”** na tabela de categorias para representar o snapshot integralmente. Comissão recebe rótulo explícito sem recalcular. Nenhum texto jurídico foi reescrito.

Inconsistências preexistentes preservadas: calendário documental considera sábado no cálculo e ajusta se o quinto útil cair nele; feriados móveis calculados na rotina antiga têm inclusões comentadas. Texto estático de endereço/representante do contratante permanece também quando a regra seleciona STELLAR. Revisão jurídica/calendário é assunto próprio e não foi presumida autorizada nesta fase.

Navegador interno: acesso a `https://improov/ImproovWeb/`, login autorizado, caminho Financeiro→Pagamento e seleção de colaborador/competência carregaram a tela existente. Não foi gerado/confirmado/enviado documento pelo legado no ambiente compartilhado. A primeira URL funcionou; não se concluiu indisponibilidade. O novo ciclo foi validado por serviço/CLI e PDFs, pois não há interface nova nesta fase. Responsividade da futura UI não foi homologada. O console registrou duas mensagens de MutationObserver durante a navegação inicial, às 20:15:14/15 UTC; recarregar Pagamento não acrescentou erro. Nenhum arquivo de UI foi modificado por este trabalho. Esse registro não é uma validação de nova interface documental.

## 19. Legado preservado

`Pagamento/gerar_adendo.php`, `confirmar_adendo.php`, `ver_adendo.php`, `script.js`, tela e `AdendoLocalService` conservam o baseline desta fase. SHA de `AdendoLocalService`: `ABA9AD09B2CA9E335A7FD1DCAB390DBBBEFAAF1E6E8933057F85AA855A1A18BB`. Template jurídico preservado. Não há conversão de adendos antigos, tokens/URLs reaproveitados, overwrite de PDFs existentes, envio, assinatura, webhook, scheduler, lote ou alteração automática de pagamentos.

Alterações externas já existentes em Contratos/Pagamento/Flow e rotação automática de Backup não pertencem a esta fase e foram preservadas. Nenhum commit/staging foi feito. Ao encerrar, bancos e raízes de arquivos sintéticos desta sessão foram limpos com validação do datadir/prefixo/caminho absoluto; as instâncias próprias 3319/3320 foram desligadas. Binários portáteis e datadirs ficam no diretório temporário para reprodução, sem serviço instalado; os cinco PDFs/manifesto permanecem no workspace.

## 20. Pendências para integração da tela

Próxima fase deve definir storage privado/ACL e backup conjunto, aplicar migrations somente em destino autorizado, criar endpoints com sessão/CSRF e entrega PDF autorizada por ID, exibir revisão financeira e documental/hash/estado e indicar revisão financeira mais nova. Deve apresentar efetivamente o preview e confirmar os identificadores recebidos; chave nova somente para pedido explícito de novo preview. Também deve oferecer recuperação operacional e mensagens em português, homologar responsividade e usar Thinking Orbs global para novos loadings.

Não iniciar essa integração automaticamente. Envio/assinatura e calendário/conteúdo jurídico continuam fora do escopo atual. O backend paralelo, migrations homologadas, CLI, recuperação e critérios documentais desta fase estão concluídos.
