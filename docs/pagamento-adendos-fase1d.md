# Pagamento / Adendos — FASE 1D

## Plano de implantação registrado antes de habilitar a interface

Referências: fase0, regras-alvo R01–R19/D01–D07, fase1a, fase1b, fase1c-a e fase1c-b. Os motores financeiros e as migrations anteriores são a autoridade; esta fase acrescenta transporte HTTP e apresentação individual, sem lote e sem alteração de regras.

O `conexao.php` configurado aponta para um banco remoto compartilhado. **Não autorizado para DDL, fixtures ou atos financeiros/documentais desta fase.** Rollout nele permanecerá desativado. Backups SQL locais existem, mas não constituem confirmação de backup íntegro/restaurável do destino nem de backup conjunto com PDFs.

Antes de deployment no destino: responsável deve confirmar backup consistente e teste de restauração; MySQL 8/InnoDB; grants de SELECT nas origens/usuários, INSERT nos históricos, UPDATE nas entidades mutáveis e TRIGGER para visibilidade das proteções; aplicação manual das migrations 1C-A e 1C-B originais; conferência das oito tabelas e quatorze triggers; raiz privada, conta do processo PHP e ACL; monitoramento e janela de manutenção. Nada será aplicado silenciosamente.

Raiz operacional proposta no Windows: `C:/ProgramData/ImproovWeb/private/pagamento-fechamento`, fora de `C:/xampp/htdocs`, com `staging/`, `definitivo/`, `locks/`. Ela deve ser provisionada pelo operador e informada em `PAGAMENTO_FECHAMENTO_STORAGE_ROOT`; não é criada implicitamente pela UI. Proprietário: conta de serviço PHP/Apache definida pelo operador. ACL: conta do serviço com Modify; administradores/backup com acesso necessário; remover leitura pública/Users/IIS genéricos conforme identidade real. Verificar que nenhum alias, virtual directory ou junction HTTP a publica. Em Linux: raiz privada equivalente, dono do serviço, diretórios 0700/arquivos 0600. Não aplicar ACL sem conhecer a conta real.

Backup/retention: snapshots, decisões, evidências, extras, operações e documentos no banco + todos os PDFs/recibos de selagem em staging/definitivo na mesma janela consistente, escritores pausados. Locks são artefatos operacionais; restaurar dirs/ACL antes de retomar. Não expirar staging nem chaves automaticamente. Retenção dos confirmados/histórico depende da política contratual aprovada; sem exclusão implementada. Restaurar primeiro em homologação e verificar hashes/recuperações antes da reabertura.

Rollback operacional: desativar `PAGAMENTO_FECHAMENTO_V2_ENABLED`, conservar banco/storage/journal, manter legado disponível. Rollbacks SQL são destrutivos e não devem ser usados para rollback de rollout com histórico. Implantação primeiro em MySQL 8 isolado com fixtures sintéticas, sem importar dados pessoais.

## Resultado — 05/10/2026

**FASE 1D CONCLUÍDA em homologação isolada.** Transporte HTTP e UI individual implementados e validados. Após autorização explícita do usuário para acessar a página de teste em `http://127.0.0.1:8066/ImproovWeb/`, passaram os cenários A–J em navegador interno autenticado, as cinco resoluções e a abertura efetiva do PDF pela interface. Banco MySQL 8 e storage descartáveis próprios; a autorização dessa URL não habilita o fluxo no destino compartilhado.

Deployment compartilhado não executado. Não há autorização para migrations reais, envio/assinatura ou fechamento mensal em lote. A flag permanece desligada por padrão.

## 1. Endpoints

Todos usam o controlador interno `Pagamento/api/FechamentoHttp.php`, não acessível como uma API monolítica de ações. Respostas JSON incluem `success`, `data` ou `code/error`, e `request_id`; PDF é binário.

| Método | Caminho                                  | Ato                                       |
| ------ | ---------------------------------------- | ----------------------------------------- |
| POST   | `Pagamento/api/fechamento/preparar.php`  | Preparar snapshot/revisão                 |
| GET    | `Pagamento/api/fechamento/obter.php`     | Última revisão ou `revision_id` explícito |
| POST   | `Pagamento/api/fechamento/decidir.php`   | FIXO, BONUS ou LIQUIDACAO                 |
| GET    | `Pagamento/api/fechamento/revisoes.php`  | Histórico financeiro                      |
| POST   | `Pagamento/api/documento/gerar.php`      | Preview de revisão explícita              |
| POST   | `Pagamento/api/documento/visualizar.php` | Entrega autenticada/auditada por ID       |
| POST   | `Pagamento/api/documento/confirmar.php`  | Confirmar documento/revisão/hash exatos   |
| GET    | `Pagamento/api/documento/listar.php`     | Documentos e reservas do ator/contexto    |
| POST   | `Pagamento/api/documento/recuperar.php`  | Retomar journal com chave original        |

Contexto obrigatório: `colaborador_id` e `competencia`. Campos inesperados são recusados; não há entrada de itens de serviços, totais, paths ou ator. POST exige objeto JSON até 64 KiB. IDs/versões positivos (expected permite zero), competência canônica, hash SHA-256, chave ASCII até 128 caracteres e conjuntos administrativos até 100 linhas são validados antes do serviço. Valores administrativos entram como texto monetário; normalização/cálculo pertencem aos serviços anteriores.

## 2. Autenticação

Reuso de `pagamento_auth.php`/`session_bootstrap.php`. Ator exclusivamente de `$_SESSION['idusuario']`. Sessão precisa estar autenticada e ter nível 1 ou 5; a autorização é reconferida em `usuario.ativo/nivel_acesso` pelos repositórios. Sessão é liberada antes do serviço: concorrência não depende do lock PHP. Dados jurídicos completos do snapshot não são projetados para a nova tela.

## 3. CSRF

Mesmo token `pagamento_csrf` existente, publicado em meta tag e enviado no header `X-CSRF-Token`. Todos os POSTs, inclusive entrega auditada do PDF e recuperação, exigem token. GETs de consulta não escrevem. Não há token paralelo nem exceção pública de teste nas APIs.

## 4. Idempotência

Cada ato explícito recebe UUID. UI conserva ação, contexto, payload e chave em memória/sessionStorage antes da requisição. Timeout de 25 segundos ou resultado incerto mantém a requisição; novos writes ficam bloqueados até repetir/conferir a mesma operação. Retry usa o payload/chave original. Sucesso e rejeições definitivas encerram a requisição pendente. Não há retry automático com chave nova.

`expected_version` é capturado da revisão exibida ao abrir a decisão. STALE retorna HTTP 409; UI fecha decisão e busca a revisão atual, informando o conflito. O adapter devolve o ID da revisão original no retry, mesmo quando existem versões posteriores. `sessionStorage` é por aba; não constitui uma fila mensal ou persistência central de trabalhos.

## 5. Feature flag

`config/pagamento_fechamento.php`: habilitado somente se `PAGAMENTO_FECHAMENTO_V2_ENABLED=1` no processo servidor. Ausência, `0` e outros valores desabilitam. Quando desligado, link/script novos não entram no Pagamento, nova página/API recusam o fluxo. Nenhuma flag altera regras por colaborador.

Configuração opcional de conexão dedicada: `PAGAMENTO_FECHAMENTO_DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` (todos com prefixo `PAGAMENTO_FECHAMENTO_`). HOST informado exige NAME/USER. Sem override utiliza `conexao.php`. Verifica MySQL 8 antes de liberar serviço. Storage obrigatório: `PAGAMENTO_FECHAMENTO_STORAGE_ROOT`, diretório privado previamente provisionado.

Não usar `.env` acessível via HTTP, parâmetros de URL ou variável JS para ativação. Em Apache, configurar variáveis server-side no VirtualHost/serviço e reiniciar/recarregar segundo procedimento operacional. Desativar com `0`/remoção da variável e recarregar o processo.

## 6. UI

Entrada condicional em `Pagamento/index.php`; `fechamento-link.js` transmite somente seleção de colaborador/competência. Página separada `Pagamento/fechamento.php`, CSS/JS próprios; preserva tokens, sidebar e botões do módulo. Tela individual tem resumo, pendências, serviços, decisões, versões e documentos. Não remove nem chama gerar/confirmar/ver_adendo legados, AdendoLocalService ou cálculos do script legado.

JS recebe centavos como strings, formata por operações textuais (inclui inteiros maiores que 2^53), nunca soma/subtrai nem infere serviços de DOM/filtros/checkboxes. `FechamentoInterfaceService` somente valida escopo/projeta/chama 1C-A/1C-B; não introduz regras financeiras. Thinking Orbs global Vanilla carregado como script global existente, canvas transparente único 48 px, `composing`, aria português; reutiliza temas/reduced motion. Durante writes em dialog a mesma orb é movida para o dialog, sem instâncias duplicadas.

## 7. Status

PRONTO, PENDENTE_BONUS, PENDENTE_FIXO, PENDENTE_EXTRAS e DIVERGENCIA_FINANCEIRA vêm da composição. Confirmação é estado documental distinto; título indica quando a revisão exibida já tem documento confirmado. Liquidação indeterminada aparece no detalhe do fixo. Estado RESERVADA é mostrado como operação incompleta, não como preview concluído.

## 8. Pendências

Bloco visível informa que fechamento ainda não pode ser concluído, componente, descrição, valores úteis e origem. Serviços com subtotal incompleto são apresentados como Pendente; composição persistida permanece intacta. Null não vira R$ 0,00; total indeterminado mostra travessão. Divergências de ledger remetem à conferência na origem: UI não oferece reconciliação de serviços que a 1C-A não suporta.

## 9. Decisões

FIXO: CADASTRO, SEM_VALOR_FIXO, OVERRIDE com valor/motivo. BONUS: PENDENTE, SEM_BONUS, DEFINIDO com conjunto completo categoria/valor/referência. LIQUIDACAO: conjunto completo de evidências de pagamento ou apuração explícita sem pagamento, origem verificável e motivo; INDETERMINADA revoga com conjunto vazio. Backend valida tipos/conflitos/valores. Conjuntos substituem o ato vigente, não fazem append implícito; referência das linhas existentes é preservada.

## 10. Revisões

Histórico ordenado por número, estado/data. Clique consulta o snapshot exato. Refresh e foco mantêm a revisão selecionada e mostram existência de versão mais recente. Decisões de versão histórica ficam desabilitadas; decisão já aberta conserva expected_version capturado e pode receber stale. Preparar nova revisão é ato explícito separado.

## 11. Documentos

Lista por fechamento; metadata pública omite arquivos físicos. Mostra número documental, revisão financeira, total daquela revisão, estado, autor/data de confirmação. Novo preview requer revisão PRONTO e IDs explícitos; não usa seleção da tabela legada. Não há envio, assinatura, scheduler ou lote.

## 12. Preview

POST autenticado `visualizar` por document_id registra visualização pelo ator antes da entrega. Browser recebe Blob application/pdf, com IDs/hash conferidos nos headers. O módulo `fechamento-pdf.js` renderiza esse mesmo PDF no modal usando PDF.js/worker já disponíveis em `assets/pdfjs`, sem nova dependência. Paginação, zoom de 100% a 300%, texto acessível e download do Blob original. Nenhum URL físico ou temp_rel é usado. Preview permanece vinculado à revisão original; nova revisão gera aviso, sem troca silenciosa. Confirmação só habilita após entrega auditada e renderização efetiva da primeira página. O iframe nativo inicialmente ficou em branco no navegador interno e foi substituído antes do aceite.

## 13. Confirmação

CTA “Confirmar este documento” usa document_id/revision_id/pdf_hash retornados. Modal exibe número, versão e total antes de confirmar. 1C-B exige visualização pelo mesmo ator, valida hash/bytes e conserva preview. Teste HTTP confirmou documento da V5 quando V6 já existia; definitivo retornou exatamente os bytes visualizados. Idempotência da confirmação também passou. Pelo navegador foi confirmado o documento #1 da V3 (R$1.600,00), mesmo com V4 vigente (R$1.750,25). Auditoria independente confirmou hash do preview e igualdade byte a byte com o definitivo; reabertura permaneceu na V3.

## 14. Recuperação

Lista não esconde RESERVADA. Ato de recuperação valida ator/contexto/operação/chave original e chama serviço 1C-B. UI não gera outro preview automaticamente e bloqueia geração enquanto houver reserva própria no contexto. Teste instalou trigger de falha exclusivamente na fixture, causou erro após reserva/materialização, removeu trigger de teste e recuperou mesma operação pelo endpoint. Bancos reais não receberam essa trigger.

## 15. Storage

Raiz/ACL/backup/retenção planejados acima. Config recusa raiz inexistente, não gravável, symlink ou dentro do repo/webroot/htdocs. Nomes, locks, recibos, hashes e acesso físico continuam responsabilidade de Files 1C-B. Config não comprova inexistência de aliases HTTP externos: operador precisa verificar. Fixture: raiz temporária própria fora de htdocs, nomes `pagamento_1cb_files_pagamento_1cb_test_<10 hex>`, dados sintéticos. PDFs de QA em output são apenas exemplos de teste; nunca usar output como storage operacional.

## 16. Deployment

Pré-flight real somente leitura nesta sessão: MySQL **8.0.46-0ubuntu0.24.04.4**, flag off, proteções financeiras/documentais NOT_READY, storage NOT_READY. Conta configurada tem USAGE/ALL PRIVILEGES visíveis; isto não confirma grants mínimos por tabela nem backup restaurável. Não foi aplicado SQL no destino.

Checklist obrigatório antes de ativar:

1. Autorizar explicitamente destino/janela; manter flag desligada.
2. Fazer backup consistente e registrar teste de restauração de banco + PDFs/recibos, com escritores pausados; confirmar proprietário/ACL/retensão.
3. Conferir MySQL 8/InnoDB e migrations já aplicadas, evitando reaplicar CREATEs parcialmente existentes sem auditoria.
4. Usar conta de migration separada com DDL/CREATE/TRIGGER/FK adequados. Runtime precisa SELECT nas origens/usuários/qualificação, INSERT nos históricos, UPDATE nas entidades/journal mutáveis e TRIGGER para visibilidade da proteção conforme 1C. TRIGGER é privilégio administrativo, não somente observação; proteger a conta. Homologar grants reais antes de reduzir privilégios.
5. Aplicar originais 1C-A depois 1C-B, somente após autorização; nenhuma migration nova é necessária na 1D.
6. Provisionar raiz privada, verificar aliases/ACL e configuração do processo.
7. Executar pré-flight e smoke autenticado em homologação, incluindo recovery/hash/retry; só então autorizar flag no destino.

Comandos de conferência (somente leitura):

```powershell
php scripts/check_pagamento_fechamento_deployment.php
```

Comandos de aplicação **para o operador, depois da aprovação/backup**, substituindo placeholders; senha solicitada interativamente. Não executados nesta tarefa:

```powershell
mysql --host=<HOST_APROVADO> --user=<CONTA_MIGRATION> --password --database=<BANCO_APROVADO> --execute="SOURCE C:/xampp/htdocs/ImproovWeb/sql/2026-10-02_pagamento_fechamento_revisao.sql"
if ($LASTEXITCODE -ne 0) { throw 'Parar: migration financeira falhou' }
mysql --host=<HOST_APROVADO> --user=<CONTA_MIGRATION> --password --database=<BANCO_APROVADO> --execute="SOURCE C:/xampp/htdocs/ImproovWeb/sql/2026-10-02_pagamento_fechamento_documento.sql"
if ($LASTEXITCODE -ne 0) { throw 'Parar: migration documental falhou' }
php scripts/check_pagamento_fechamento_deployment.php
if ($LASTEXITCODE -ne 0) { throw 'Parar: implantação incompleta' }
```

Configurar storage/DB no processo correspondente antes do pré-flight. Conferir oito tabelas novas/quatorze triggers, CHECKs/FKs/JSON e grants por conta real. Não rodar rollbacks SQL em histórico produtivo para desligar flag. Rollback operacional: flag=0, manter banco/storage/journals, confirmar que legado segue disponível e diagnosticar antes de retomar.

## 17. Testes

| Verificação                             | Resultado desta sessão                                 |
| --------------------------------------- | ------------------------------------------------------ |
| 1A offline                              | 230 OK                                                 |
| 1B offline                              | 169 OK                                                 |
| 1C-A MariaDB 10.4.32 isolado 3319       | 113 OK                                                 |
| 1C-A MySQL 8.0.42 isolado 3320          | 117 OK                                                 |
| 1C-B MySQL 8.0.42 isolado 3320          | 194 OK                                                 |
| Históricos/golden                       | 1.312 OK, golden intacto                               |
| Custos atual                            | 338 OK (supera baseline de 329)                        |
| Leitor 1A no destino, READ ONLY         | 14 OK                                                  |
| Leitor 1B no destino, READ ONLY         | 26 OK                                                  |
| Shadow 1B real                          | 2 EXPECTED_TARGET_CHANGE, 19 SQL auditados             |
| Shadow 1A real                          | Guarda recusou SHA legado alterado; não forçada        |
| Shadows golden 1A/1B                    | Rodaram; golden intacto                                |
| HTTP novo                               | 78 verificações OK                                     |
| Render PHP/assets HTTP                  | 14 OK, incluindo PDF.js/worker/módulo/zoom/marca local |
| UI autenticada A–J                      | OK, banco/storage sintéticos                           |
| Cinco resoluções e PDF multipágina/zoom | OK                                                     |
| PHP/JS syntax e diff check              | OK                                                     |

`tests/pagamento_adendos_interface_test.php` usa endpoints reais em servidor PHP CLI explicitamente isolado, fixtures originais estendidas, sessão/CSRF reais do Flow. Login fixture somente no router cli-server guardado por porta/host/banco de teste; não existe bypass de autenticação nos endpoints. Curl usa URL oficial localhost com resolução somente da requisição para loopback, evitando Apache existente; não muda DNS/hosts do sistema. Router não serve outros PHPs nem PDFs físicos. `config/version.php`/sidebar mantidos podem consultar versão cosmética do destino em SELECT; nenhum ato financeiro da fixture usa conexao.php.

Cobertura HTTP: sessão ausente, nível 2, ator inativo, níveis 1/5, CSRF, payload extra/nulo, competência, colaborador ausente, retry/conflito/expected/stale, bônus, override, liquidação, revisão exata, geração/retry, path proibido, escopo estrangeiro, pré-visualização exigida, PDF real/hash/corrupção, hash incorreto, confirmação antiga/mesmos bytes/retry, reservas SQL de geração e confirmação/recovery/chave errada, fixo pendente/divergência/especial Nicolle. Não simula queda de energia/FS distribuído.

Reprodução da homologação exige instâncias próprias 3319/3320 descritas na 1C, PHP/Poppler e porta loopback 8066 livre. Cada execução HTTP precisa fixture nova e servidor configurado para ela. Não usar conexao.php nem seed no destino real:

```powershell
$fixtureText = php tests/pagamento_adendos_interface_fixture.php --seed
$fixture = $fixtureText | ConvertFrom-Json
$manifest = Join-Path $env:TEMP 'pagamento_1d_fixture.json'
[IO.File]::WriteAllText($manifest, $fixtureText, [Text.UTF8Encoding]::new($false))
$env:PAGAMENTO_FECHAMENTO_V2_ENABLED = '1'
$env:PAGAMENTO_FECHAMENTO_DB_HOST = '127.0.0.1'
$env:PAGAMENTO_FECHAMENTO_DB_PORT = '3320'
$env:PAGAMENTO_FECHAMENTO_DB_NAME = $fixture.db
$env:PAGAMENTO_FECHAMENTO_DB_USER = 'root'
$env:PAGAMENTO_FECHAMENTO_STORAGE_ROOT = $fixture.root
$env:PAGAMENTO_FIXTURE_PASSWORD = '<SENHA_LOCAL_SINTETICA>'
php -S 127.0.0.1:8066 -t C:/xampp/htdocs C:/xampp/htdocs/ImproovWeb/tests/pagamento_adendos_interface_router.php
# Outra sessão, mesma senha de teste configurada:
php tests/pagamento_adendos_interface_test.php $manifest
php tests/pagamento_adendos_interface_test.php $manifest --page
# Depois de parar o servidor da fixture:
php tests/pagamento_adendos_interface_fixture.php --cleanup $manifest
```

Router fornece shell/login/menu sintéticos e serve a página nova real; não reproduz integralmente login/dashboard/seleção do legado. Conta sintética pedro_imp identifica ator fixture 1; não importar usuários/dados reais. Não registrar senha em comandos de relatório/logs.

## 18. Navegador

Skill agent-browser aplicada, CLI indisponível; utilizado navegador interno CUA. URLs oficiais `https://improov/ImproovWeb/` e `http://localhost:8066/ImproovWeb/` abriram login; autenticação de desenvolvimento funcionou em ambas. Em HTTPS navegação real Financeiro → Pagamento: legado carregou, link novo ausente com flag off. Não houve atos financeiros reais. A nova tela foi aberta pela navegação do login/menu sintéticos na URL loopback expressamente autorizada. Página, módulos JS, autenticação/CSRF e endpoints são os arquivos reais; o shell/login fixture não homologa novamente o dashboard completo do legado.

| Cenário                                     | Evidência no navegador                                                                                                                                                                  |
| ------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| A — PRONTO → gerar → visualizar → confirmar | Fixture 2 V3: documento ID1/revisão ID16, preview #1, R$1.600,00. PDF renderizado antes do CTA; confirmado, data/ator mostrados; hash e bytes definitivos conferidos.                   |
| B — bônus PENDENTE → SEM_BONUS              | Fixture 2 V2→V3, extras R$0,00 explícitos, PRONTO e total canônico R$1.600,00.                                                                                                          |
| C — bônus DEFINIDO                          | Extra Qualidade UI R$150,25, V3→V4, total retornado R$1.750,25.                                                                                                                         |
| D — fixo pendente                           | Fixture 4 mostrou Pendente/total —; override R$200 manteve liquidação pendente; apuração explícita sem pagamento de R$0,00 gerou V4 PRONTO/R$200,00.                                    |
| E — divergência                             | Fixture 5: serviço sem ledger, base R$250,00, serviços Pendente/total —, preview bloqueado; nenhuma reconciliação não suportada oferecida.                                              |
| F — Nicolle/especial                        | Fixture sintética ID1: especial R$4.000,00; V3 total R$5.575,00; V1 manteve especial e componentes indeterminados/total —, com aviso de versão mais recente.                            |
| G — duas abas/stale                         | Ambas na Fixture 6 V2; uma publicou V3; decisão capturada na outra foi recusada, dialog fechado, V3 recarregada sem V4.                                                                 |
| H — preview V3 com V4                       | Preview/confirmado ID1 conservou V3/R$1.600,00 e aviso explícito sobre V4; nenhum arquivo substituído.                                                                                  |
| I — timeout/retry                           | Trigger somente na fixture atrasou preparo em 28s; UI abortou aos 25s com orb/retry e novos writes bloqueados. Repetir mesma operação recuperou V4; auditoria: quatro revisões, sem V5. |
| J — operação recuperável                    | Falha de publicação somente na fixture deixou reserva visível. Após remoção da falha, Recuperar retomou a mesma operação, documento ID2/preview #1/V2/R$100,00, sem preview duplicado.  |

Auditoria final da fixture UI: três documentos com hashes íntegros, documento ID1 confirmado com bytes idênticos ao preview; nenhuma operação RESERVADA remanescente. Fixture 3 teve PDF com 45 extras/duas páginas/R$45,00: navegação 1→2→1 e total da segunda página conferidos. Zoom 100→150→200→150% passou no mobile, com overflow limitado à área do PDF. Os 78 testes HTTP foram repetidos em fixture nova após as correções de apresentação, junto aos 14 testes de assets.

Evidências em `output/ui/fase1d/`: `desktop-1440x900.png`, `notebook-pdf-confirmado.png`, `timeout.png`, `operacao-incompleta.png`, `pdf-pagina2.png`, screenshots dos iPads e do mobile/formulário/PDF/zoom. `auditoria.json` registra a conferência independente de hashes, bytes, revisões e ausência de reservas antes da limpeza.

PDF HTTP confirmado `output/pdf/fase1d/endpoint-confirmado.pdf`, fixture com V5, fixo R$100,00 + bônus R$125,50 = R$225,50 canônico, V6 posterior. Bytes visualizado/definitivo idênticos. Extração Poppler e render PNG inspecionados: título competência, rubricas, total/extenso e layout corretos. Template jurídico fixo da 1C preservado (contém identificação da contratante constante). Esta verificação offline complementa a abertura e confirmação efetivas pela UI acima.

## 19. Responsividade

CSS implementa grids, overflow local da tabela, botões flex, contexto empilhado, dialogs limitados por viewport e breakpoints 1180/840/540 px. Foi corrigido o box sizing dos elementos da nova página: histórico e inputs excediam a largura disponível. A marca da sidebar foi direcionada ao GIF local existente somente nesta página, corrigindo a imagem externa indisponível.

| Resolução               | Resultado visual/funcional                                                                                                                 |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Desktop 1440×900        | Resumo quatro colunas, histórico lateral; body 1440, main client/scroll 1375.                                                              |
| Notebook 1366×768       | Resumo/histórico legíveis, PDF e aviso V3/V4, footer acessível; body 1366, main 1301.                                                      |
| iPad landscape 1024×768 | Histórico abaixo, PDF com rolagem interna e ações acessíveis; body 1024, main 964.                                                         |
| iPad portrait 768×1024  | Resumo duas colunas, ações acomodadas; PDF legível no modal; body 768, main 703.                                                           |
| Mobile 390×844          | Contexto empilhado, histórico uma coluna; formulário completo de bônus e ações acessíveis. PDF com zoom/rolagem local; body 390, main 325. |

Sem overflow horizontal da página/main nas cinco resoluções; tabela larga tem rolagem local intencional. Screenshots inspecionados e salvos. Override de viewport restaurado ao final. O navegador estava em tema escuro; tema claro e reduced motion não foram alternados porque a ferramenta não expõe emulação dessas preferências. Tokens de tema existentes e suporte a `prefers-reduced-motion` do Thinking Orbs foram preservados sem alterar o módulo global; essa conferência de código não é apresentada como teste visual desses modos.

## 20. Riscos e limites

UI homologada em fixture isolada; manter rollout off no destino, que segue sem schema/storage/backup confirmado. Gravações só ocorreram em bancos descartáveis. FS privado/aliases/ACL precisam de homologação por responsável; locks são locais/cooperativos. Shadow 1A protegido aguarda auditoria do novo SHA do legado, sem alterar guarda automaticamente. Alterações de Backup são rotação externa e foram preservadas; não há commit/staging desta tarefa. PDFs 1C-B reemitidos pela regressão foram repostos ao baseline, golden e motores anteriores permanecem intactos. Os testes em resoluções de iPad/mobile usaram viewport do navegador interno, não dispositivos físicos nem Safari; não foi simulada queda de energia.

## 21. Próximos passos

A implementação/homologação individual da 1D está concluída. Próximo passo operacional: aprovar deployment/backup/storage/grants do destino conforme seção 16; aplicar migrations manualmente na janela aprovada, executar pré-flight/smoke autenticado e só então habilitar flag. Decidir aposentadoria do legado e lote mensal em fases posteriores.

Ao encerrar esta etapa: servidor PHP de teste e instâncias próprias 3319/3320 desligados, banco/raiz sintéticos próprios removidos por helper com validação de prefixo/caminho absoluto. Binários/datadirs portáteis previamente existentes foram conservados para reprodução. Manifesto temporário não é configuração produtiva. Exemplos PDF/PNG de QA permanecem em output.
