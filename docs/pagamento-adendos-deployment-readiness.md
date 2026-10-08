# Pagamento / Adendos — FASE 1D.1: readiness de implantação

Diagnóstico de 05/10/2026, America/Sao_Paulo. **DEPLOYMENT NÃO APTO.**

Referências: [fase0](pagamento-adendos-fase0.md), [regras-alvo](pagamento-adendos-regras-alvo.md), [1A](pagamento-adendos-fase1a.md), [1B](pagamento-adendos-fase1b.md), [1C-A](pagamento-adendos-fase1c-a.md), [1C-B](pagamento-adendos-fase1c-b.md), [1D](pagamento-adendos-fase1d.md). A homologação isolada da 1D permanece válida. Esta etapa realizou somente diagnóstico: nenhuma migration/grant/ACL/configuração de servidor foi aplicada, nenhum fechamento/PDF/documento real foi criado e a flag não foi habilitada. Não inicia 1E.

## 1. Ambiente

Instância web inspecionada: Windows, `C:/xampp/htdocs/ImproovWeb`, Apache **2.4.58 Win64**, PHP integrado por `php8apache2_4.dll`. Apache usa `C:/xampp/apache/conf/httpd.conf`, webroot `C:/xampp/htdocs`, porta HTTP 8066; HTTPS `improov:443` em `conf/extra/httpd-ssl.conf`, mesmo webroot. São as URLs oficiais `https://improov/ImproovWeb/` e `http://localhost:8066/ImproovWeb/`.

O servidor MySQL é remoto Ubuntu. Não confundir o SO do banco com o SO do processo que grava PDFs. O filesystem/ACL inspecionado é o da instância Windows acima; eventual outro servidor web precisa de diagnóstico próprio antes de usar estes comandos.

Consulta inicial CLI sem acesso de rede do sandbox retornou apenas `mysqli_sql_exception`, sem mensagem de conexão/segredo. Reexecução com acesso autorizado de rede funcionou. Não se trata de indisponibilidade do destino. Só SELECT/SHOW e transação própria READ ONLY para metadados.

## 2. Banco

| Dado | Confirmado |
| --- | --- |
| Configuração usada | `conexao.php`, sem override de fechamento no CLI |
| Host / porta | `72.60.137.192:3306`, TCP/IP |
| Database | `flowdb` |
| Conta efetiva | `improov@%` |
| Hostname do MySQL | `srv1150340.hstgr.cloud` |
| Versão | `8.0.46-0ubuntu0.24.04.4` |
| Engine padrão | InnoDB |
| Isolation da sessão | REPEATABLE-READ, apenas consultado |
| Engines do database | 313 tabelas base InnoDB |
| Binlog / trust creators | `log_bin=1`, `log_bin_trust_function_creators=0`, apenas consultados |
| Ambiente | Compartilhado: contém origens financeiras, usuários, dados jurídicos e legado do Flow |

As 15 tabelas requeridas existentes estão presentes/InnoDB: `colaborador`, `usuario`, `funcao_imagem`, `imagens_cliente_obra`, `funcao`, `log_alteracoes`, `funcao_animacao`, `animacao`, `acompanhamento`, `pagamento_itens`, `pagamentos`, `adendos`, `informacoes_usuario`, `endereco`, `endereco_cnpj`. Pais `colaborador.idcolaborador` e `usuario.idusuario`: INT signed, NOT NULL, PRIMARY KEY, compatíveis com as FKs externas das migrations.

## 3. Migrations

SQL revisado, sem alteração. Arquivos continuam iguais ao HEAD versionado após normalização exclusiva CRLF/LF, sem diff Git. Nenhuma alteração ocorreu desde a homologação 1D nesta sessão; os hashes abaixo passam a ser o manifesto operacional dos bytes atuais. Não existe manifesto anterior de SHA dessas duas migrations nos relatórios 1C; essa limitação não foi substituída por uma afirmação de comparação com hash inexistente.

| Migration | SHA-256 dos bytes atuais |
| --- | --- |
| `sql/2026-10-02_pagamento_fechamento_revisao.sql` | `ac513ca0e03e355624bbc875c59844f499734748f85d82a7efa36075d0493243` |
| `sql/2026-10-02_pagamento_fechamento_documento.sql` | `3d02fd459fbfc60bb6165e184af59a2f884eb2b07d1c142558374809d9d31282` |

Inventário real: **0/8 tabelas, 0/14 triggers, zero colisões com nomes de constraints explícitos dessas migrations**. TRIGGER está visível à conta com ALL PRIVILEGES em `flowdb`, portanto ausência não foi inferida a partir de conta sem visibilidade. Estado: **ABSENT, sem instalação parcial detectada**. Ausência é esperada antes da implantação, não um defeito da 1D. Se qualquer objeto surgir antes da janela, parar e auditar; não completar automaticamente.

1C-A cria seis tabelas (`pagamento_fechamento`, `_decisao`, `_extra`, `_evidencia`, `_revisao`, `_operacao`) e dez triggers: `pfr`, `pfd`, `pfe`, `pfv`, `pfo`, cada um com `_no_update` e `_no_delete`. 1C-B cria `_documento`, `_documento_operacao` e quatro triggers: `pdoc_no_update`, `pdoc_no_delete`, `pdop_no_update`, `pdop_no_delete`. SQL documental usa DELIMITER; executar com cliente MySQL, não splitter genérico por ponto e vírgula. Migrations possuem DDL com commit implícito: backup e parada após primeiro erro são obrigatórios.

## 4. Grants: migration e runtime

Grants diretos confirmados, sem senha/hash de autenticação: USAGE em `*.*`, ALL PRIVILEGES em `flowdb.*` e em `improov.*`. `CURRENT_ROLE()` retornou NONE; não há papel ativo acrescentando privilégios. Não aparece SUPER/SET_USER_ID global nem GRANT OPTION. O segundo database é excesso de escopo para este fluxo. Não foram concedidos/revogados privilégios.

| Privilégio | Atual em flowdb | Necessário | Ação proposta, não executada |
| --- | --- | --- | --- |
| SELECT | ALL cobre | Runtime: 15 origens e 8 tabelas novas | Mapear conta dedicada; preservar SELECT do definer documental |
| INSERT | ALL cobre | Runtime: todas as 8 tabelas novas | Conceder somente nessas tabelas na conta futura |
| UPDATE | ALL cobre | Runtime: `pagamento_fechamento`, `_documento`, `_documento_operacao` | Limitar às três entidades mutáveis; triggers seguem obrigatórios |
| DELETE | ALL cobre | Nenhum ato do fluxo novo | Não incluir na conta futura; não revogar da conta compartilhada sem auditoria do legado |
| LOCK TABLES | ALL cobre no database | Implementação atual exige alternativa de privilégio para FOR UPDATE em `usuario` e operação financeira append-only | Perfil sem UPDATE/DELETE dessas tabelas precisa LOCK TABLES em `flowdb`; aprovar/homologar esse alcance |
| TRIGGER | ALL cobre | Runtime: visibilidade de `information_schema.TRIGGERS` nas 7 tabelas protegidas; definer: execução das proteções | Privilégio administrativo, não somente leitura; restringir escopo e proteger credencial |
| CREATE | ALL cobre | Conta migration: oito CREATE TABLE | Conta DDL separada, ainda não definida |
| REFERENCES | ALL cobre | Conta migration: tabelas referenciadas, inclusive `colaborador`/`usuario` | Mapear na conta DDL |
| INDEX | ALL cobre | Índices estão dentro de CREATE TABLE; não há CREATE INDEX avulso | Não exigir concessão adicional por comandos inexistentes |
| ALTER / DROP | ALL cobre | Não utilizados pelas migrations originais | Não exigir para aplicação normal; nenhum rollback SQL destrutivo |
| SUPER / autorização global equivalente aplicável | Não vista | Com binlog=1/trust=0, a criação de triggers exige resolução pelo DBA/provedor | **BLOQUEANTE**: definir conta capaz e estratégia de DEFINER; não alterar variável global ou ampliar runtime |

SELECT do runtime deriva dos repositories 1A/1B/1C e da projeção jurídica. INSERT inclui reserva inicial de fechamento e decisões/extras/evidências/revisões/operações/documentos; UPDATE limita-se à publicação/estado/versionamento/journal. Nenhum write nas origens financeiras ou dados jurídicos.

**Detalhe que impede reduzir grants apenas por DML:** `FechamentoDocumentoRepository::autorizar()` usa `SELECT ... FOR UPDATE` em `usuario`; `FechamentoRevisaoRepository::buscarOperacao()` faz o mesmo em operação append-only. MySQL exige SELECT mais UPDATE, DELETE ou LOCK TABLES para FOR UPDATE. A proposta usa LOCK TABLES no database para manter UPDATE restrito às entidades mutáveis, embora o código não emita LOCK TABLES. Não modificar o lock ou ampliar UPDATE de `usuario` nesta fase. [MySQL: locking reads](https://dev.mysql.com/doc/refman/8.0/en/innodb-locking-reads.html).

**DEFINER:** migrations omitem DEFINER, logo será a conta aplicadora. Ela deve continuar existindo com TRIGGER nas tabelas protegidas e SELECT nas duas tabelas documentais cujos OLD/NEW são lidos. Não excluir essa identidade ou retirar esses privilégios após DDL. Credenciais DDL podem ficar indisponíveis ao runtime, sem apagar o definer. A restrição de binlog deve ser resolvida pelo DBA antes de executar sequer a 1C-A, evitando seis tabelas criadas e falha no primeiro trigger. [MySQL: CREATE TRIGGER](https://dev.mysql.com/doc/refman/8.0/en/create-trigger.html), [binary logging](https://dev.mysql.com/doc/refman/8.0/en/stored-programs-logging.html).

Perfil dedicado de runtime deve ser homologado em MySQL 8 isolado com o conjunto exato de grants antes de trocar credencial. A conta atual cobre o acesso do novo fluxo, mas não é um perfil mínimo e também atende o legado; redução nela não está autorizada.

## 5. Storage

Raiz proposta e correta para esta instância Windows: `C:/ProgramData/ImproovWeb/private/pagamento-fechamento`. Não existe; nem foi provisionada nesta fase. Subdiretórios obrigatórios: `staging/`, `definitivo/`, `locks/`. Raiz fora de repo/htdocs/webroot. `C:/ProgramData` é diretório real, sem link/reparse point na inspeção; os descendentes ainda inexistentes não possuem ACL/link verificáveis.

Nenhum alias/document root conhecido em `C:/xampp/apache/conf` aponta para essa raiz; os document roots ativos são `C:/xampp/htdocs`. Isso não certifica um proxy/servidor externo não inspecionado. Depois de provisionar, conferir todos os componentes com `Get-Item -Force`, negar reparse points, conferir aliases novamente e testar acesso sob a conta real. Não usar `Contratos/gerados`, output, Backup ou diretório público como novo storage.

## 6. Conta PHP/Apache

Processos httpd PID 2960 (pai) e 13296 (filho): proprietário **`IMP-PC011\usuario`**, GetOwner retornou 0. PHP integrado roda nessa identidade; não há processo PHP-FPM nem serviço Windows Apache/PHP registrado encontrado. Execução atual via aplicação/console, não presumir `Apache2.4`, SYSTEM, IUSR ou www-data. Se o deployment mudar para serviço dedicado, identificar novamente a identidade e revisar ACL/comandos antes da ativação.

## 7. ACL

Proposta: storage privado protegido da herança; `IMP-PC011\usuario` com Modify, SYSTEM/Administrators com FullControl; demais usuários comuns sem entrada de acesso. Backup nesta instância usa a mesma identidade; eventual operador/serviço de backup distinto precisa ser definido e receber só o acesso necessário. A conta é também interativa: seus usos e o login da máquina ficam dentro da fronteira de confiança, risco a revisar pelo responsável.

Não houve criação/alteração de ACL. ACL efetiva do storage está **BLOQUEANTE** até provisionamento e validação de leitura/escrita/rename/locks/fsync sob a conta PHP. Em primeiro momento, comandos da seção 16 deixam backup/configuração legíveis apenas a administradores/SYSTEM e adicionam a conta PHP no storage e leitura da configuração. Não conceder Users/Everyone. Retenção de confirmados e journals é a política contratual a aprovar; não implementar expiração automática de staging/chaves.

## 8. Backup

| Evidência | Resultado |
| --- | --- |
| Rotina completa | `Backup/backup_completo.php`, tarefa Windows **Backup BD**, diária 02:00, conta `usuario` |
| Execução reportada | 05/10/2026 02:00:01, resultado da tarefa 0 |
| Último completo efetivamente encontrado | `Backup/backup_completo_2026-10-02_02-00-01.sql`, 95.066.572 bytes, footer “Dump completed” de 02/10 02:01:07 |
| Rotina parcial | `Backup/backup_tabelas.php`, tarefa **Backup Tabelas Parciais**, repetição PT10M |
| Parcial observado | 05/10/2026 19:01:36, 1.553.579 bytes; inclui somente funcao_imagem, obra, imagens_cliente_obra, acompanhamento_email |
| Destino dos backups | Mesmo host/porta/database/usuário do Flow conforme chaves não secretas do .env existente |
| Retenção implementada | Cinco completos e um parcial, por nome/data; exclusão apenas pela rotina existente |
| Cliente configurado por fallback | MariaDB mysqldump 10.4.32 do XAMPP, não cliente MySQL 8 |
| Estado banco + PDFs | Nenhuma cópia do novo storage; rotina não implementa janela conjunta |

O resultado 0 do agendador não prova backup válido: scripts imprimem erro mas não terminam com exit não zero ao falhar; php-win não fornece evidência persistente no código inspecionado. Há discrepância concreta entre última execução e último dump completo. Não executar a rotina para “ver se funciona”, pois ela também exclui backups antigos. Não atribuir causa sem log do mysqldump. Compatibilidade do cliente/grants precisa ser verificada pelo responsável.

Backups atuais ficam no webroot, sem regra conhecida em `.htaccess`/configuração que negue `.sql`/Backup. Não foram baixados por HTTP nem expostos conteúdos. Adotar destino privado e restringir a publicação dos dumps existentes é requisito de proteção do backup; nenhuma regra foi alterada nesta fase.

**BLOQUEANTE:** obter novo backup completo verificável do destino na janela pré-DDL, diagnosticar a discrepância da rotina e confirmar local privado/retensão/cópia externa aprovada. Um footer e tamanho positivo não demonstram restauração.

## 9. Restauração e backup conjunto

Não foi encontrada evidência de teste recente de restauração do destino. **BLOQUEIO DE DEPLOYMENT.** Não restaurar no compartilhado nesta fase. Responsável deve fornecer resultado ou executar teste futuro autorizado em MySQL 8 separado, com aplicações, eventos e integrações desligados, sem conexão de retorno ao destino.

Aceite do restore: versão/engines/schema e triggers, contagens e amostras aprovadas das origens, integridade de FK/CHECK; depois de implantação, hashes de todos os PDFs, correspondência documento/revisão/journal/recibos, recuperação sem duplicação e aplicação autenticada usando exclusivamente a cópia. Restaurar ACL/dirs/identidade do definer antes de retomar. Registrar host isolado, instante do backup, SHA, operador e resultados; não apenas sucesso do comando de importação.

Implantação futura: flag OFF, nenhum writer novo, janela coordenada também com DDL concorrente; backup completo banco antes de qualquer DDL; provisionar storage; aplicar 1C-A/verificar; aplicar 1C-B/verificar; pré-flight; canary restrito e smoke supervisionado; liberar rollout somente após aceite. Ausência de writer novo não paralisa writers do legado; coordenar janela e dump consistente.

Backups futuros: fechar a entrada de novos atos, deixar requests novos em andamento terminarem, pausar recovery/CLI/jobs e impedir DDL; manter banco + staging/definitivo/recibos estáveis até terminar dump e cópia do storage, sob um mesmo identificador de janela. Incluir journals, arquivos `.part` e recibos, sem “limpar” staging. Usar dump transacional para InnoDB e copiar todos os artefatos; manifesto SHA protegido, reter ambos juntos e testar restauração conjunta. Locks são operacionais: restaurar diretório/ACL e recriar locks cooperativos conforme serviço, sem tratar um lock antigo como evidência de operação concluída. Pausar todos os escritores é diferente de simplesmente ocultar o link da UI.

## 10. Configuração server-side

Mecanismo adequado a esta instância: Include explícito em `C:/xampp/apache/conf/httpd.conf` de arquivo privado `C:/ProgramData/ImproovWeb/private/apache/pagamento-fechamento.conf`, diretivas dentro de Directory do projeto. Não existe esse Include/arquivo nem SetEnv/PassEnv do fechamento nas configurações inspecionadas; nada foi criado.

Configuração proposta, **não aplicada**:

```apache
<Directory "C:/xampp/htdocs/ImproovWeb">
    SetEnv PAGAMENTO_FECHAMENTO_V2_ENABLED 0
    SetEnv PAGAMENTO_FECHAMENTO_STORAGE_ROOT "C:/ProgramData/ImproovWeb/private/pagamento-fechamento"
</Directory>
```

Inicialmente pode manter conexão existente: sem DB_HOST override, somente o novo fluxo usa o fallback de `conexao.php`. Se a conta runtime dedicada for aprovada, informar DB_HOST/PORT/NAME/USER/PASSWORD com prefixo `PAGAMENTO_FECHAMENTO_` exclusivamente no mecanismo privado protegido. Não documentar segredo real, não colocar em JS/repo/novo `.env` público nem em argumento de processo. HOST exige NAME/USER; senha é necessária para esse destino. Validar conta/grants antes da troca.

SetEnv de Apache não equivale a variável do CLI. Pre-flight CLI deve receber o mesmo storage e eventual conexão segura da instância; não considerar CLI como prova de configuração do Apache. Confirmação final exige smoke autenticado no processo web. O script expõe somente identidade técnica e metadados permitidos; não publica senha, tokens, CPF ou snapshots.

## 11. Flag

CLI: variável ausente → OFF. Browser interno em HTTPS: login dev realizado, Financeiro → Pagamento abriu normalmente, zero links `fechamento.php` e ausência do script `fechamento-link.js`. Nenhum botão financeiro/documental acionado. Isto confirma rollout desligado no processo web consultado, além da configuração inspecionada. HTTP/HTTPS oficiais foram validados na 1D; nesta fase a confirmação autenticada foi em HTTPS. Não foi alterada UI, portanto não se repetiu a matriz visual da 1D.

Ativação futura: mudar exclusivamente a diretiva privada de 0 para 1, validar config e reiniciar a instância real na janela aprovada. Flag técnica não muda regras por colaborador. Não ativar com query string, checkbox ou variável JS.

## 12. Pré-flight

Executados `php scripts/check_pagamento_fechamento_deployment.php` e variante **`--readiness`**. Esta opção adicionada nesta fase faz somente SELECT/SHOW de metadados numa transação READ ONLY, identifica escopo dos grants, inventaria objetos/colisões/engines/pais e calcula hashes dos SQL. Sem mudanças de regra/API/UI; lint passou. Resultado sanitizado: [evidência JSON](evidence/pagamento-adendos-readiness-2026-10-05.json).

| Componente | Estado atual |
| --- | --- |
| MYSQL | OK, 8.0.46 |
| INNODB | OK, 15 origens e 313 tabelas base |
| SCHEMA | NOT_READY, ABSENT, 0/8 |
| TRIGGERS | NOT_READY, 0/14; nomes visíveis, nenhum parcial |
| GRANTS runtime atual | Cobre, porém ALL PRIVILEGES e segundo schema excessivos |
| GRANTS migration | BLOQUEANTE: conta separada/capacidade de triggers com binlog não definida |
| STORAGE | NOT_READY, raiz ausente e variável não configurada no CLI |
| FLAG | OFF; ausência no CLI e entrada nova ausente no browser |

Não READY é diagnóstico esperado antes de aplicação. Exit não zero desse pré-flight não autoriza “reparo”. O checker não atesta backup, restore, ACL sob a conta PHP, corpo de cada trigger/FK/CHECK nem o alcance de servidores externos. Inventário PRESENT_REQUIRES_AUDIT após aplicação planejada exige conferir etapa/quantidades; fora da janela é motivo para parar.

## 13. Smoke planejado, não executado

Depois de migrations/storage/grants/backup/restore aceitos, responsável escolhe expressamente **colaborador + competência + atos permitidos**. Registrar operador, supervisão, UUIDs, expected_version, revision/document IDs e SHA, sem dados jurídicos/valores em logs genéricos. Não usar colaborador real escolhido pelo agente ou registrar decisão fictícia.

Antes da liberação ampla, configurar acesso técnico restrito ao canary/janela de manutenção; só então ON temporário é necessário para o smoke, porque OFF bloqueia novos endpoints/página. Isso não é flag financeira por colaborador. Se não houver controle de público/janela, não habilitar para tentar testar. Procedimento:

1. Login real, confirmar sessão/nível 1 ou 5; conferir negativas sem sessão/nível incorreto/CSRF antes de writes autorizados.
2. Abrir Pagamento pelo menu, verificar legado e link novo; conferir contexto escolhido.
3. Preparar revisão com nova chave; retry da mesma chave deve devolver mesma revisão; ler resumo/snapshot/histórico.
4. Decisão simples coerente com evidência aprovada, expected_version da tela; segunda aba recebe stale, sem overwrite.
5. Gerar preview PRONTO, abrir efetivamente o PDF; verificar colaborador/competência/revisão/rubricas/total e número documental.
6. Confirmar apenas o ID/revisão/hash visualizados, incluindo aviso se há versão posterior; definitivo mantém SHA e bytes; retry não duplica.
7. Consultar operações incompletas. Se há reserva real elegível previamente autorizada, recuperar mesma operação e conferir ID/hash. Sem reserva, validar lista vazia e repetir recovery com falha controlada somente em homologação, usando os testes 1D; não instalar triggers de falha/delay no real.
8. Guardar evidências privadas e liberar público somente após aceite. Qualquer falha → OFF, preservar journal/PDF, investigar.

## 14. Rollback operacional

Mudar flag privada para **0**, testar config, reiniciar/reler processo web; conferir ausência do link e acesso ao legado, novos endpoints recusados. Impedir outros writers novos, aguardar atos já iniciados; não assumir que a troca de flag cancela um request em andamento. Conservar tabelas/revisões/decisões/documentos/journals/PDFs, inclusive reservas. Nunca rodar arquivos `_rollback.sql` como rollback produtivo.

Tempo operacional estimado: 2–5 minutos para configuração/teste/restart e verificação de acesso, mais a drenagem dos requests em andamento. Não é SLA medido. Instância atual não é serviço registrado: usar Stop/Start Apache no XAMPP sob a identidade atual na janela aprovada; procedimento CLI de serviço só se realmente houver serviço identificado. [Apache Windows: console e serviço](https://httpd.apache.org/docs/2.4/platform/windows.html).

## 15. Checklist GO / NO-GO

| Item | Classificação | Evidência / requisito |
| --- | --- | --- |
| [x] MySQL 8 confirmado | OK | 8.0.46 real |
| [x] InnoDB confirmado | OK | 15 origens / 313 tabelas |
| [x] Migrations íntegras | OK | SHA calculado, HEAD sem alteração de conteúdo |
| [x] Nenhuma instalação parcial | OK | 0/8, 0/14, zero colisões |
| [ ] Conta de migration definida | BLOQUEANTE | DBA + binlog + DEFINER persistente |
| [x] Grants runtime mapeados | OK | Inclui FOR UPDATE/LOCK TABLES e TRIGGER |
| [ ] Perfil runtime dedicado aprovado | PENDENTE | Conta compartilhada atual cobre; não reduzir legado sem auditoria |
| [ ] Backup confirmado para implantação | BLOQUEANTE | Último completo 02/10 versus tarefa 05/10; obter/verificar snapshot novo privado |
| [ ] Restauração testada | BLOQUEANTE | Evidência recente ausente |
| [x] Storage definido | OK | Raiz Windows privada proposta |
| [ ] Storage provisionado/validado | BLOQUEANTE | Raiz e subdiretórios ausentes |
| [x] Conta PHP/Apache identificada | OK | IMP-PC011\usuario, não serviço registrado |
| [x] ACL proposta | OK | Modify serviço, F admins/SYSTEM |
| [ ] ACL efetiva validada | BLOQUEANTE | Não provisionada; validar sob conta PHP |
| [x] Storage fora do webroot | OK no plano | Caminho proposto fora; validar descendentes/aliases após criação |
| [x] Configuração server-side definida | OK no plano | Include privado + Directory; ainda não aplicado |
| [x] Flag OFF confirmada | OK | CLI + browser autenticado |
| [x] Rollback operacional documentado | OK | Flag OFF, dados preservados |
| [x] Smoke test definido | OK no plano | Seleção real e janela/canary ainda dependem do responsável |

Pendências de execução da janela não significam que se deveria aplicar algo na 1D.1. Os bloqueios acima impedem pedir autorização de migrations/rollout como se o ambiente já estivesse pronto.

## 16. Comandos de deployment — preparados, NÃO EXECUTADOS

Executar somente após resolução dos bloqueios e autorização explícita de destino/janela. PowerShell nesta máquina, conta administradora para provisionamento/ACL/configuração. As contas não definidas são solicitadas interativamente; senhas sempre no prompt do cliente. Não há senha literal. Não executar sequências inteiras sem conferir os gates intermediários.

### 16.1 Identidade, ferramentas e hashes

```powershell
Set-Location -LiteralPath 'C:/xampp/htdocs/ImproovWeb'
$php = 'C:/xampp/php/php.exe'
$apache = 'C:/xampp/apache/bin/httpd.exe'
$clientBin = Join-Path $env:TEMP 'pagamento_1cb_runtime/mysql-8.0.42-winx64/bin'
$mysql = Join-Path $clientBin 'mysql.exe'
$dump = Join-Path $clientBin 'mysqldump.exe'
if (!(Test-Path -LiteralPath $mysql) -or !(Test-Path -LiteralPath $dump)) { throw 'Provisionar cliente MySQL 8 aprovado antes de continuar' }
& $mysql --version
& $dump --version
$expected = @{
  'sql/2026-10-02_pagamento_fechamento_revisao.sql' = 'ac513ca0e03e355624bbc875c59844f499734748f85d82a7efa36075d0493243'
  'sql/2026-10-02_pagamento_fechamento_documento.sql' = '3d02fd459fbfc60bb6165e184af59a2f884eb2b07d1c142558374809d9d31282'
}
foreach ($file in $expected.Keys) {
  if ((Get-FileHash -Algorithm SHA256 -LiteralPath $file).Hash.ToLowerInvariant() -ne $expected[$file]) { throw "Migration mudou: $file" }
}
$before = (& $php scripts/check_pagamento_fechamento_deployment.php --readiness | Out-String) | ConvertFrom-Json
if ($before.identity.db -ne 'flowdb' -or $before.installation_inventory -ne 'ABSENT' -or $before.flag_enabled) { throw 'Parar: destino/inventário/flag diferentes do aprovado' }
```

O cliente MySQL 8.0.42 portátil existe atualmente no caminho acima; promover binários aprovados a um local estável privado antes da janela ou manter esse caminho validado. Não utilizar automaticamente o mysqldump MariaDB do XAMPP. O gate completo também exige conferência da versão, engines, conta DDL e backup/restore pelo responsável.

### 16.2 Provisionar área privada, storage e ACL, com flag OFF

```powershell
$privateBase = 'C:/ProgramData/ImproovWeb/private'
$storageRoot = "$privateBase/pagamento-fechamento"
$backupRoot = "$privateBase/deployment-backups"
$configRoot = "$privateBase/apache"
$serviceAccount = 'IMP-PC011\usuario'
if (Test-Path -LiteralPath $privateBase) { throw 'Área privada já existe: auditar caminhos/ACL antes de adaptar esta sequência' }
New-Item -ItemType Directory -Path $privateBase -Force | Out-Null
# Remove herança antes de incluir qualquer dump/segredo.
& icacls.exe $privateBase /inheritance:r /grant:r '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F'
if ($LASTEXITCODE -ne 0) { throw 'Falha ACL base' }
# Somente passagem/leitura desta pasta; não herdar acesso aos backups.
& icacls.exe $privateBase /grant:r "${serviceAccount}:RX"
if ($LASTEXITCODE -ne 0) { throw 'Falha ACL de passagem' }
New-Item -ItemType Directory -Path $storageRoot,$backupRoot,$configRoot | Out-Null
& icacls.exe $storageRoot /grant:r "${serviceAccount}:(OI)(CI)M"
if ($LASTEXITCODE -ne 0) { throw 'Falha ACL storage' }
& icacls.exe $configRoot /grant:r "${serviceAccount}:(OI)(CI)RX"
if ($LASTEXITCODE -ne 0) { throw 'Falha ACL config' }
foreach ($child in @('staging','definitivo','locks')) { New-Item -ItemType Directory -Path "$storageRoot/$child" | Out-Null }
Get-Item -Force -LiteralPath $privateBase,$storageRoot,$backupRoot,$configRoot | Select-Object FullName,Attributes,LinkType,Target
Get-Acl -LiteralPath $storageRoot | Format-List Owner,AccessToString
```

Gate: conferir todos os pais/descendentes e ausência de reparse points, nenhum acesso herdado Users/Everyone. Rodar teste de criação/rename/lock/fsync de artefato descartável autorizado sob `IMP-PC011\usuario` depois de provisionar; teste PHP do próprio armazenamento em homologação e smoke do processo web. Não criar PDF real como “teste de permissão” fora do smoke aprovado. Se for necessária conta distinta de backup, aprovar e conceder acesso específico antes de copiar arquivos.

### 16.3 Backup e restauração isolada

```powershell
$backupUser = Read-Host 'Conta de backup aprovada pelo DBA'
$stamp = Get-Date -Format 'yyyy-MM-dd_HH-mm-ss'
$dumpFile = "$backupRoot/flowdb_pre_fechamento_$stamp.sql"
& $dump --host=72.60.137.192 --port=3306 "--user=$backupUser" --password --single-transaction --quick --routines --events --triggers --no-tablespaces --column-statistics=0 --set-gtid-purged=OFF "--result-file=$dumpFile" flowdb
if ($LASTEXITCODE -ne 0 -or !(Test-Path -LiteralPath $dumpFile) -or (Get-Item -LiteralPath $dumpFile).Length -eq 0) { throw 'Backup falhou: não aplicar migration' }
Get-Content -LiteralPath $dumpFile -Tail 3
Get-FileHash -Algorithm SHA256 -LiteralPath $dumpFile
$restoreHost = Read-Host 'Host MySQL 8 isolado aprovado para restauração'
if ($restoreHost -eq '72.60.137.192' -or $restoreHost -eq 'srv1150340.hstgr.cloud') { throw 'Restauração deve usar instância isolada, não destino' }
$restorePort = Read-Host 'Porta da instância isolada'
$restoreUser = Read-Host 'Conta de restauração aprovada'
$restoreDb = Read-Host 'Database isolado já provisionado pelo DBA'
if ($restoreDb -eq 'flowdb') { throw 'Usar nome de database exclusivo de restauração' }
# Database separado já criado, aplicações e event_scheduler desligados pelo responsável.
$OutputEncoding = [Text.UTF8Encoding]::new($false)
Get-Content -Raw -Encoding UTF8 -LiteralPath $dumpFile | & $mysql "--host=$restoreHost" "--port=$restorePort" "--user=$restoreUser" --password "--database=$restoreDb"
if ($LASTEXITCODE -ne 0) { throw 'Restauração falhou' }
```

Não considerar restore aceito antes das verificações da seção 9 e registro do responsável. Validar todos os hosts/endpoints, inclusive aliases, antes da importação; comparar strings não substitui isolamento de infraestrutura. A conta backup precisa dos privilégios adequados para views/triggers/routines/events; DBA deve verificar SHOW_ROUTINE/global SELECT/identidade quando aplicável. `--no-tablespaces` evita necessidade de PROCESS nessa opção; GTID purged OFF evita transplantar estado global de GTID. Nenhuma DDL concorrente durante dump transacional. [MySQL: mysqldump](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html).

### 16.4 Conta DDL aprovada → 1C-A → verificação → 1C-B

```powershell
$migrationUser = Read-Host 'Conta DDL aprovada, com capacidade de triggers/binlog e DEFINER persistente'
$OutputEncoding = [Text.UTF8Encoding]::new($false)
# Entrada SQL via stdin: DELIMITER é interpretado pelo cliente; sem --force.
Get-Content -Raw -Encoding UTF8 -LiteralPath 'sql/2026-10-02_pagamento_fechamento_revisao.sql' | & $mysql --host=72.60.137.192 --port=3306 "--user=$migrationUser" --password --database=flowdb
if ($LASTEXITCODE -ne 0) { throw 'PARAR: 1C-A falhou. Não completar/reaplicar automaticamente' }
$a = (& $php scripts/check_pagamento_fechamento_deployment.php --readiness | Out-String) | ConvertFrom-Json
# NOT_READY documental/exit não zero ainda é esperado nesta etapa.
if ($a.financial -ne 'OK' -or @($a.existing_new_tables).Count -ne 6 -or @($a.existing_new_triggers).Count -ne 10) { throw 'PARAR: inventário 1C-A não confere' }
Get-Content -Raw -Encoding UTF8 -LiteralPath 'sql/2026-10-02_pagamento_fechamento_documento.sql' | & $mysql --host=72.60.137.192 --port=3306 "--user=$migrationUser" --password --database=flowdb
if ($LASTEXITCODE -ne 0) { throw 'PARAR: 1C-B falhou. Não completar/reaplicar automaticamente' }
$env:PAGAMENTO_FECHAMENTO_V2_ENABLED = '0'
$env:PAGAMENTO_FECHAMENTO_STORAGE_ROOT = $storageRoot
$b = (& $php scripts/check_pagamento_fechamento_deployment.php --readiness | Out-String) | ConvertFrom-Json
if ($b.financial -ne 'OK' -or $b.documental -ne 'OK' -or $b.private_storage -ne 'OK' -or @($b.existing_new_tables).Count -ne 8 -or @($b.existing_new_triggers).Count -ne 14 -or $b.flag_enabled) { throw 'PARAR: pré-flight pós-DDL falhou' }
```

Conferir também SHOW CREATE TABLE das oito tabelas/SHOW CREATE TRIGGER dos quatorze triggers, comparando FKs, CHECKs, enums, índices, corpos/definer com os SQL de SHA aprovado. Gates do checker verificam presença/engine/nome, não equivalência completa. Guardar evidência privada; zero writes financeiros até autorização do smoke. Nenhum DROP, rollback SQL, SET GLOBAL ou GRANT está embutido nesta sequência.

### 16.5 Configuração privada e reload da instância real

```powershell
$privateConf = "$configRoot/pagamento-fechamento.conf"
$configText = @'
<Directory "C:/xampp/htdocs/ImproovWeb">
    SetEnv PAGAMENTO_FECHAMENTO_V2_ENABLED 0
    SetEnv PAGAMENTO_FECHAMENTO_STORAGE_ROOT "C:/ProgramData/ImproovWeb/private/pagamento-fechamento"
</Directory>
'@
[IO.File]::WriteAllText($privateConf,$configText,[Text.UTF8Encoding]::new($false))
$httpdConf = 'C:/xampp/apache/conf/httpd.conf'
Copy-Item -LiteralPath $httpdConf -Destination "$backupRoot/httpd_$stamp.conf"
$includeLine = 'Include "C:/ProgramData/ImproovWeb/private/apache/pagamento-fechamento.conf"'
if (!(Select-String -LiteralPath $httpdConf -SimpleMatch $includeLine -Quiet)) { Add-Content -LiteralPath $httpdConf -Value $includeLine -Encoding ASCII }
& $apache -t -f $httpdConf
if ($LASTEXITCODE -ne 0) { throw 'Config inválida: não reiniciar' }
```

**Instância atual console/XAMPP:** Stop Apache, aguardar requests terminarem, Start Apache no painel XAMPP sob `IMP-PC011\usuario`. Não emitir `Restart-Service Apache2.4` ou `httpd -k restart` presumindo serviço inexistente. Confirmar novos PIDs/owner/log e ambas as URLs. Comando exato para início manual, caso o operador tenha encerrado o Apache pelo painel e deseje iniciar fora dele:

```powershell
Start-Process -FilePath $apache -ArgumentList '-f','C:/xampp/apache/conf/httpd.conf' -WorkingDirectory 'C:/xampp/apache/bin' -WindowStyle Hidden
```

Não usar Stop-Process forçado para drenar operações. Se a infraestrutura for posteriormente um serviço **realmente identificado**, comando correspondente, condicionado à identidade e caminho inspecionados:

```powershell
$apacheService = Get-CimInstance Win32_Service | Where-Object { $_.PathName -match 'httpd.exe' }
if (@($apacheService).Count -ne 1) { throw 'Não existe serviço Apache único identificado: usar procedimento XAMPP/console' }
Restart-Service -Name $apacheService.Name
```

### 16.6 Canary, smoke e ativação posterior

Somente após controle de público/janela aprovado e seleção real supervisionada da seção 13:

```powershell
$text = [IO.File]::ReadAllText($privateConf)
if (!($text -match '(?m)^\s*SetEnv PAGAMENTO_FECHAMENTO_V2_ENABLED 0\s*$')) { throw 'Configuração da flag inesperada' }
$text = $text -replace '(?m)^(\s*SetEnv PAGAMENTO_FECHAMENTO_V2_ENABLED )0\s*$', '${1}1'
[IO.File]::WriteAllText($privateConf,$text,[Text.UTF8Encoding]::new($false))
& $apache -t -f $httpdConf
if ($LASTEXITCODE -ne 0) { throw 'Config inválida: não reiniciar' }
# Reiniciar pela modalidade real descrita na seção 16.5.
# Executar smoke autenticado da seção 13 e só então liberar o público aprovado.
```

Não executar testes HTTP de fixture contra esse banco, nem escolher automaticamente colaborador/competência. O pre-flight não contém writes de smoke. A configuração CLI de flag permanece OFF até receber ambiente correspondente deliberadamente; a ativação real acima é do Apache.

### 16.7 Rollback da flag

```powershell
$text = [IO.File]::ReadAllText($privateConf)
$text = $text -replace '(?m)^(\s*SetEnv PAGAMENTO_FECHAMENTO_V2_ENABLED )[01]\s*$', '${1}0'
[IO.File]::WriteAllText($privateConf,$text,[Text.UTF8Encoding]::new($false))
& $apache -t -f $httpdConf
if ($LASTEXITCODE -ne 0) { throw 'Config inválida: corrigir antes do restart' }
# Reiniciar pela modalidade real, verificar OFF, drenar writers e confirmar legado.
```

## 17. Riscos e bloqueios finais

**DEPLOYMENT NÃO APTO.** Resolver antes de pedir autorização de execução:

1. Definir conta DDL/DBA apta a criar triggers sob `log_bin=1/trust=0`, com plano de DEFINER persistente; ALL no schema da conta atual não comprova a autorização global necessária.
2. Diagnosticar execução de backup sem dump completo recente, obter snapshot completo verificável em destino privado e aprovar backup conjunto banco + PDFs/recibos.
3. Apresentar evidência recente de restauração testada em MySQL 8 isolado, incluindo procedimento conjunto para o novo estado banco/filesystem.
4. Provisionar raiz/subdiretórios privados, aplicar e validar ACL sob a identidade PHP, conferir reparse points/aliases e mecanismo privado de configuração com flag OFF.

Ausência de schema é esperada e as migrations estão prontas; não deve ser “corrigida” antes desses gates. Não há autorização implícita para grants, provisionamento, backup com exclusões, restore, DDL, restart ou rollout. Legado e arquivos de PDF/Backup surgidos externamente no workspace foram preservados. Não houve commit/staging.
