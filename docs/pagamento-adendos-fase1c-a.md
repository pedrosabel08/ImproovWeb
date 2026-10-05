# Pagamento / Adendos — FASE 1C-A

## Modelo registrado antes da migration

Proposta registrada em 02/10/2026 antes de criar/aplicar SQL. Referências: fase0, regras-alvo (D01–D07), fase1a e fase1b. Inspeção do schema do dump local e serviços atuais: IDs de colaborador/usuario são INT signed; fixo DECIMAL nullable; ledger não discrimina fixo/bônus; adendos têm payload documental incompleto. Nenhum backfill é permitido.

Convenções reutilizadas: serviços PHP/mysqli em português; migrations datadas em `sql`; InnoDB, BIGINT UNSIGNED para entidades novas, JSON para snapshots e centavos BIGINT signed; versões/lock_version como no planejamento versionado. Auditoria de planejamento é específica de entregas e login registra acesso, portanto nenhum deles comporta atos financeiros: o histórico de decisões e operações abaixo será a própria auditoria, sem log duplicado.

Modelo mínimo proposto:

- `pagamento_fechamento`: identidade única colaborador/competência, estado PENDENTE/PRONTO, lock_version e número da última revisão. Única entidade atualizável.
- `pagamento_fechamento_decisao`: atos append-only FIXO/BONUS/LIQUIDACAO; autor usuario ativo, motivo, timestamp UTC microsegundos, contexto antes/depois JSON. Último ato de cada tipo constitui a decisão vigente.
- `pagamento_fechamento_extra`: rubricas positivas por ato BONUS, categoria/referência única no conjunto, centavos, autor/timestamp e escopo herdado por FK forte.
- `pagamento_fechamento_evidencia`: conjunto explícito de evidências do ato LIQUIDACAO, referência, origem verificável, tipo aprovado, valor, motivo, autor/instante. Não é lançamento no ledger.
- `pagamento_fechamento_revisao`: número único por fechamento, um único snapshot JSON obrigatório, hash canônico SHA-256, valores indexáveis, estado, autor/instante, regras, referências às três decisões usadas. Append-only com proteção UPDATE/DELETE por triggers.
- `pagamento_fechamento_operacao`: chave de idempotência única por fechamento, autor, hash da requisição e revisão retornada, antes/depois. Append-only; retry com mesmo conteúdo retorna resultado original.

Cada ato autorizado prepara sua nova revisão na mesma transação; preparação sem alteração usa nova chave explícita. lock_version avança junto com cada revisão, assim expected_version é a versão observada da última revisão (zero antes de criar). Lock exclusivo do fechamento precede a primeira leitura consistente de dados em REPEATABLE READ; leituras financeiras 1A/1B compartilham essa visão, sem iniciar transação aninhada. Autorização ativa é conferida no servidor com níveis 1/5 já usados por Pagamento e Custos, com lock compartilhado do usuario até commit.

Rollback operacional recomendado: desabilitar consumidor e preservar tabelas para manter histórico. Rollback SQL destrutivo apenas em banco descartável ou após arquivamento/autorização explícita; nada toca tabelas legadas. FKs RESTRICT evitam eliminar autoria/colaborador/histórico por cascade. Migration não será aplicada no banco compartilhado configurado. Testes usarão instância dedicada loopback 127.0.0.1:3319, datadir temporário próprio, bancos com prefixo `pagamento_1ca_test_` e fixtures sintéticas sem importar dados pessoais.

O registro acima precedeu a criação da migration e sua aplicação às fixtures. A implementação abaixo concretiza esse modelo.

## 1. Modelo persistente

Seis tabelas novas, sem dependência de PDF. Identidade: `(colaborador_id, competencia)`. Revisão: `(fechamento_id, numero)`. Cada revisão tem **exatamente um** `snapshot_json NOT NULL` na própria linha; não existe referência opcional a snapshot separado. A linha guarda o resultado completo e os dados que o produziram. Valores financeiros principais são centavos inteiros; SQL não usa FLOAT/DOUBLE.

As FKs de usuário/colaborador são INT signed, compatíveis com o schema inspecionado. IDs novos são BIGINT UNSIGNED. FKs RESTRICT preservam autoria e vínculos. FKs compostas impedem associar extra/evidência/decisão/revisão de outro fechamento. Índices cobrem identidade, número, estado/mês, decisão vigente, referência no conjunto, hash e autor/data. `CHECK(lock_version=numero_revisao)` conserva a correspondência da versão retornada; publicação sempre avança ambos juntos.

Decisões administrativas são append-only. Seu `depois_json` é a representação completa do ato vigente; extras/evidências também são individualizados nas respectivas tabelas. A repetição desses itens no antes/depois e no snapshot serve à reconstrução histórica, não constitui um segundo sistema de logs. Escopo dos itens vem da FK forte para decisão/fechamento; todos conservam autor e instante próprios. Referências são únicas por conjunto/ato, permitindo que o mesmo recibo ou extra reapareça no histórico de uma substituição explícita do conjunto, sem duplicar o valor vigente.

Sem ato de fixo, a revisão conserva a configuração cadastral observada e o estado calculado pela 1B. Sem ato de bônus, conserva PENDENTE. Esses estados padrão ficam persistidos no snapshot; não são fabricados como atos administrativos SEM_BONUS ou prova de liquidação. O último ato explícito de cada tipo é referenciado por ID e integralmente copiado no snapshot.

## 2. Migrations e ambiente

- [Migration](../sql/2026-10-02_pagamento_fechamento_revisao.sql): seis CREATE TABLE, dez triggers contra UPDATE/DELETE do histórico, FKs/uniques/indexes/checks. Aplicação manual uma vez; não é migration reaplicável com IF NOT EXISTS que ocultaria drift.
- [Rollback](../sql/2026-10-02_pagamento_fechamento_revisao_rollback.sql): remove somente as seis tabelas novas na ordem inversa das dependências. **Destrutivo**, testado somente nas fixtures descartáveis. Em uso real, preferir desabilitar consumidor e conservar histórico.

Nenhuma migration foi aplicada ao banco compartilhado/configurado; nenhuma tabela/dado real foi escrito. DDL MySQL tem commits implícitos: aplicar por manutenção autorizada, verificar estrutura completa antes de habilitar consumidor e não assumir rollback transacional de DDL. O serviço verifica InnoDB e presença dos dez triggers antes de escrever, rejeitando instalação incompleta.

Validação de escrita: MariaDB **10.4.32** fornecido pelo XAMPP, instância separada com `--no-defaults`, bind `127.0.0.1`, porta **3319**, datadir temporário próprio `pagamento_1ca_mysql_0529a54aed5547e5af125b14237e9d5f`. Sem serviço Windows, sem reutilizar `C:/xampp/mysql/data`, sem importar dados pessoais. Cada suíte cria e elimina seu próprio banco `pagamento_1ca_test_<10 hex>`. Exemplo da execução final: `pagamento_1ca_test_f0e6699e88`.

Compatibilidade projetada: MySQL 8/InnoDB e MariaDB 10.4 (SQL/JSON, checks, triggers simples e locking reads suportados). O writer/migration foi executado no MariaDB isolado; os readers 1A/1B foram executados no MySQL configurado. **Não houve homologação de escrita/DDL no MySQL compartilhado**. Antes de implantação autorizada, homologar a migration também em MySQL 8 controlado, com os grants reais (inclusive visibilidade dos triggers).

## 3. Arquivos e extensão dos motores

| Arquivo                                              | Responsabilidade                                                        |
| ---------------------------------------------------- | ----------------------------------------------------------------------- |
| `Pagamento/services/FechamentoSnapshot.php`          | Canonização/hash versionados e serialização JSON                        |
| `Pagamento/services/FechamentoRevisaoRepository.php` | Persistência, autorização, locks, leitura íntegra e auditoria           |
| `Pagamento/services/FechamentoRevisaoService.php`    | Preparar, decidir, obter/listar/comparar revisões                       |
| `scripts/pagamento_fechamento_revisao.php`           | CLI de ambiente explícito e saída financeira resumida                   |
| `tests/fixtures/pagamento_adendos_persistencia.php`  | Schema mínimo sintético/instância dedicada, sem conexao.php             |
| `tests/pagamento_adendos_persistencia_test.php`      | Testes reais de storage, concorrência, snapshot, rollback e autorização |
| Dois SQL acima e este relatório                      | Migration/reversão/documentação                                         |

Única extensão prévia: `FechamentoFinanceiroRepository` extraiu suas mesmas consultas para método interno reutilizado por `carregar()` e adicionou `persistir()` com transação própria READ WRITE. Isso evita abrir/encerrar o snapshot read-only 1A antes da escrita e evita duplicar SQL. `carregar()` conserva contrato read-only, flags e rollback; Rules/Service 1A e todos os motores 1B ficaram intactos. Nenhuma tarifa, elegibilidade, arredondamento, saldo, classe ou regra de fixo/extras foi reimplementada nesta camada.

## 4. Estados

Fechamento/revisão: **PENDENTE** ou **PRONTO**. Não há estado intermediário publicado: preparação inteira é uma transação. O estado da revisão deriva de `total_final_determinado` da 1B. PRONTO exige resultado não bloqueado, serviços completos, fixo/liquidação determinados quando necessários, bônus decidido e extras válidos, sem pendências bloqueantes. Check SQL conserva coerência de estado/total/bloqueio.

Bônus: PENDENTE/SEM_BONUS/DEFINIDO. Fixo no ato persistido: NAO_DEFINIDO/DEFINIDO/SEM_VALOR_FIXO, com modo CADASTRO/OVERRIDE/SEM_VALOR_FIXO. Fixo/liquidação na composição conservam integralmente os estados 1B. Não foram criados ENVIADO/VISUALIZADO/ASSINADO ou outros estados documentais.

## 5. Atos e decisões

`decidir(..., tipo, input)` aceita:

- **FIXO**: `estado=OVERRIDE`, valor normalizado pela Support 1B; `SEM_VALOR_FIXO`; ou `CADASTRO` para retirar um ato mensal e voltar à fonte padrão. Original cadastral, substituto/utilizado, motivo, autor e timestamp ficam preservados. Não escreve em colaborador.
- **BONUS**: substitui estado/conjunto vigente. SEM_BONUS/PENDENTE exigem conjunto vazio; DEFINIDO exige ao menos uma rubrica positiva, categoria/referência não vazias, sem duplicidade. Zero/negativos/formatos monetários inválidos são rejeitados pela 1B. Pode reabrir explicitamente como PENDENTE, gerando revisão indeterminada.
- **LIQUIDACAO**: substitui o **conjunto completo** de evidências daquele direito mensal, com motivo obrigatório. Tipos existentes PAGAMENTO_FIXO/APURACAO_FIXO_SEM_PAGAMENTO, referência e `origem_verificavel` obrigatórias; conflitos/duplicidades rejeitados pela 1B. `estado=INDETERMINADA` com conjunto vazio revoga explicitamente o conjunto vigente, preservando o anterior no histórico. Não cria lançamento no ledger.

Cada ato gera nova revisão na **mesma** transação. Conjuntos são substituídos explicitamente, não somados aos registros de versões antigas. Pagamentos discriminados acima do devido continuam sendo evidência, mas a pendência 1B bloqueia PRONTO; não se apaga o fato para fechar o total.

Se cadastro mudar depois de override/SEM_VALOR_FIXO, o original persistido não é reescrito. A 1B detecta original divergente e mantém a nova preparação pendente até novo ato explícito. CADASTRO usa o cadastro observado na nova revisão; a anterior conserva seu valor antigo.

## 6. Autorização

Mapeamento anterior à implementação: `Pagamento/pagamento_auth.php::pagamento_is_gestor()` e `Custos/custos_auth.php` usam níveis **1 e 5**; login só autentica `usuario.ativo=1`. Leitura/preparação e atos financeiros reutilizam esses níveis de gestão financeira, sem ACL nova.

O backend recebe apenas ID de usuário e consulta `usuario` no servidor: ativo + nível 1/5. Não recebe nível/autoria/instante de decisão como autoridade do input. Há verificação inicial e revalidação com `LOCK IN SHARE MODE` até commit; revogação posterior não passa entre autorização e publicação. IDs inexistentes, inativos e nível 2 foram testados em leitura/preparação/decisão.

Não há endpoint público novo. A CLI é ferramenta confiável de operador local e resolve permissão pelo usuário do banco. Futura integração HTTP deve obter ID de sessão autenticada, reutilizar CSRF de Pagamento e nunca aceitar `usuario` escolhido pelo cliente. A CLI não é substituto para login/CSRF HTTP.

## 7. Auditoria

Atos guardam tipo, autor, UTC microsegundos, motivo e antes/depois completos. Overrides guardam original/substituto; reconciliação guarda conjunto anterior/posterior, estado, valor, referência/origem; extras conservam categoria/valor individualmente. As operações guardam chave, request hash, usuário, instante, motivo, versão anterior e revisão publicada. Preparação sem ato também é auditada.

Não foi reutilizado evento de planejamento (FK específica de entrega) nem log de acesso (não representa decisão). Não existe linha mutável de decisão que perca o antes/depois. Triggers impedem alterações/remoções dos cinco históricos; FKs impedem apagar autor/beneficiário referenciados. Um DBA com poder de remover triggers/tabelas continua fora dessa proteção de aplicação.

## 8. Idempotência

Chave ASCII de 1–128 caracteres, única por fechamento. Hash versionado da requisição inclui beneficiário, competência, usuário, expected_version, tipo e input. Sob lock: chave existente + mesmo hash/autor retorna a revisão original, mesmo que o fechamento já tenha outras revisões. Chave existente com outro conteúdo/autor é conflito. Retry não acrescenta ato, extra, evidência, operação ou revisão.

Recalcular explicitamente exige **chave nova** e expected_version atual. Timestamps do novo snapshot diferenciam essa revisão mesmo se os valores financeiros forem iguais. Chaves de operação não são apagadas/expiradas nesta fase; retenção conjunta com histórico. Não há deduplicação heurística por total.

## 9. Concorrência e transações

Transação própria: SET TRANSACTION REPEATABLE READ, READ WRITE; criação/upsert e lock exclusivo do fechamento; autorização compartilhada do usuário; leitura de operação idempotente com lock. Só então é estabelecida a read view com SELECT de colaborador. Isso evita que quem esperou pelo gestor anterior leia uma view anterior ao commit dele.

Dados 1A, complementos legados/cadastro 1B e decisões persistidas são lidos na mesma view. Decisão da própria transação é aplicada em memória, depois gravada junto com snapshot/revisão/operação e avanço do fechamento. Escritas concorrentes no ledger não são travadas globalmente: entram na próxima revisão, sem misturar versões na atual. Erro de validação, conflito, falha de INSERT ou commit aborta a transação própria.

`expected_version` corresponde à `version` retornada (número da revisão). Inicial=0. Dois gestores com a mesma versão e chaves distintas: um publica; o outro recebe STALE_VERSION e deve reler. Retry com a mesma chave é verificado **antes** de stale. Não assumir transação do chamador: autocommit desativado ou transação existente são rejeitados sem commit/rollback do trabalho dele. Deadlock/timeout transitório não é mascarado; operador pode repetir a mesma chave após rollback.

Escolha apoiada na documentação primária de [consistent reads MySQL](https://dev.mysql.com/doc/refman/8.0/en/innodb-consistent-read.html): em REPEATABLE READ, a primeira leitura consistente estabelece a view. Locks de coordenação usam estado corrente e ficam restritos a fechamento/autor; dados financeiros usam apenas a view estabelecida após esses locks. Essa ordem foi testada com dois processos e writer separado.

## 10. Snapshot completo

`schema_version=pagamento_fechamento_snapshot_v1` conserva: autor, instante/fuso, dados de origem/log/ledger/legado carregados pela 1A, contexto 1B (cadastro observado, decisões, evidências/rubricas, informação legada), IDs/conteúdo das decisões e **composição integral**, incluindo resultado 1A, fixo, especial, extras, componentes conhecidos/indeterminados, subtotal, total null/determinado, pendências, bloqueio e versões das regras. UTC em DATETIME(6); ISO com offset America/Sao_Paulo no payload.

Colunas financeiras da revisão são índices de leitura: `fixo_centavos` significa **saldo de fixo do componente**, não substituto/bruto; configurado/utilizado/pago são conservados no JSON. Extras indeterminados e total final indeterminado permanecem SQL/JSON NULL. Histórico nunca consulta cadastro/ledger atuais para se reconstruir. Teste reproduziu 1A/1B integralmente a partir dos inputs persistidos.

JSON duplica alguns resultados/inputs de propósito: autonomia histórica e diagnóstico valem o armazenamento adicional. Dados legados informativos podem conter número decimal JSON; valores financeiros principais normalizados permanecem inteiros. Snapshot não contém DSN/senha/URL de assinatura/token/arquivo: o adapter 1B já projeta apenas campos financeiros úteis do payload legado.

## 11. Hash financeiro

`financial_snapshot_canonical_v1`: SHA-256 de `VERSION + LF + JSON canônico`. Mapas ordenados por chave (SORT_STRING); listas financeiras tratadas como coleções sem ordem semântica, recursivamente ordenadas por sua representação JSON em comparação byte a byte, **preservando duplicidades**. Não se altera a ordem armazenada/consumida pelos motores. Flags JSON: UNESCAPED_UNICODE/UNESCAPED_SLASHES/THROW_ON_ERROR. Zero decimal negativo normalizado para zero; números não finitos rejeitados. Não se reivindica RFC 8785 ou assinatura criptográfica.

Hash abrange autor, timestamps, versões, inputs, decisões e resultados. Mesmo snapshot com outra ordem de SELECT/chaves produz hash igual; mudança de conteúdo produz hash diferente; outra preparação em outro instante constitui outro snapshot. Leitura recalcula/verifica hash antes de retornar. O hash é financeiro, **não PDF**; nenhuma implementação de hash documental foi criada.

## 12. Preparação e CLI

API reutilizável:

```php
$service = new FechamentoRevisaoService($conexaoDedicada);
$v1 = $service->prepararRevisao($beneficiario, '2026-09', $usuarioAutenticado, 0, $chave);
$v2 = $service->decidir($beneficiario, '2026-09', $usuarioAutenticado, $v1['version'], $outraChave,
    'BONUS', ['estado' => 'SEM_BONUS', 'motivo' => 'Decisão mensal explícita']);
$historico = $service->listarRevisoes($beneficiario, '2026-09', $usuarioAutenticado);
$congelado = $service->obterRevisao($v1['id'], $usuarioAutenticado);
$comparacao = $service->compararRevisao($v1['id'], $usuarioAutenticado);
```

Fluxo real: lock/versão/autorização → view → carregar 1A/complementos/decisões → aplicar ato quando houver → Rules 1A → Rules 1B → salvar snapshot/revisão → operação/auditoria → avanço → commit. Retry retorna revisão existente. Comparação abre somente a transação READ ONLY antiga da 1A e não escreve; informa diferenças de componentes/total/estado e hashes das estruturas financeiras, sem tratar mudança de timestamp de leitura como mudança de serviços.

CLI validada na instância isolada: fixture, preparar, decidir, listar, obter, comparar. Saída padrão resumida com IDs, componentes/centavos, estados/hash e códigos de pendência; `obter --snapshot` solicita conteúdo completo. Escritores exigem `--isolado` e banco no prefixo estrito. `--readonly-configurado` só admite listar/obter/comparar e substitui conexão por guarda SQL SELECT; não instala schema. Essas leituras só serão úteis no destino depois de implantação autorizada da migration.

```powershell
& C:/xampp/php/php.exe scripts/pagamento_fechamento_revisao.php --ajuda
& C:/xampp/php/php.exe scripts/pagamento_fechamento_revisao.php --acao=fixture --isolado
# Use o nome aleatório retornado, não um banco do sistema:
& C:/xampp/php/php.exe scripts/pagamento_fechamento_revisao.php --acao=preparar --isolado --banco=pagamento_1ca_test_XXXXXXXXXX --usuario=1 --colaborador=3 --competencia=2026-09 --expected-version=0 --chave=teste-v1
```

## 13. Versionamento e imutabilidade

Único contador por identidade, com uniqueness SQL, lock e expected_version. V1 pendente é preservada depois de V2 pronta; resolução de fixo/bônus/pendência ou pagamento posterior exige outra revisão. Revisões/atos/itens/operações rejeitam UPDATE e DELETE inclusive direto no DB; não apenas no service. Snapshot antigo não muda ao alterar cadastro ou pagamentos atuais. Nenhuma relação com adendo antigo/token/assinatura foi criada.

## 14. Testes novos

**113 verificações reais de persistência**, com mysqli e fixtures descartáveis, cobrindo:

- fechamento inicial/primeiro ato, V1/V2/nova preparação e imutabilidade por leitura e SQL direto;
- PENDENTE→SEM_BONUS/DEFINIDO, reabertura PENDENTE, múltiplos extras, valor/categoria/motivo/referência inválidos;
- fixo NULL/zero/SEM_VALOR_FIXO, override, original observado, cadastro intocado, cadastro alterado com override pendente e retirada explícita de override;
- assinatura/cabeçalho pago sem inferência, apuração zero autorizada, pagamento parcial/integral, evidência sem origem e conjuntos conflitantes;
- snapshot completo, replay offline de 1A/1B, hash armazenado/determinístico/sensível ao conteúdo/zero JSON;
- retry antigo e atual, chave com outro conteúdo/autor, dois gestores concorrentes (sucesso + stale), dois retries simultâneos (mesmo ID, uma revisão);
- autor ativo/nível no servidor, payload sem autoridade de autor/nível, FK de autoria/beneficiário;
- falha real por trigger no último INSERT da operação: decisão, extras, revisão e avanço rollback; retry posterior funciona;
- transação do chamador preservada, writer concorrente alterando origem e ledger entre consultas (não mistura snapshots);
- pendência 1A propagada, zero final determinado vs null indeterminado e especial Nicolle 400000 separado;
- golden intacta e rollback SQL sem remover tabelas legadas.

```powershell
& C:/xampp/php/php.exe tests/pagamento_adendos_persistencia_test.php
```

Requer instância dedicada previamente iniciada. Provisionamento reproduzível, sem serviço ou dados reais, usando o binário já instalado ([documentação MariaDB Windows](https://mariadb.com/docs/server/server-management/install-and-upgrade-mariadb/installing-mariadb/installing-system-tables-mariadb-install-db/mariadb-install-db-exe)):

```powershell
# Confirmar que a porta 3319 está livre; não substituir instância existente.
$pagamentoTestData = Join-Path $env:TEMP ('pagamento_1ca_mysql_' + [guid]::NewGuid().ToString('N'))
& C:/xampp/mysql/bin/mysql_install_db.exe --datadir=$pagamentoTestData --port=3319 --silent
Start-Process C:/xampp/mysql/bin/mysqld.exe -WindowStyle Hidden -ArgumentList @('--no-defaults', ('--datadir=' + $pagamentoTestData), '--basedir=C:/xampp/mysql', '--port=3319', '--bind-address=127.0.0.1', '--innodb-buffer-pool-size=64M')
# Depois dos testes, desligar somente essa instância dedicada:
& C:/xampp/mysql/bin/mysqladmin.exe --no-defaults --host=127.0.0.1 --port=3319 --user=root shutdown
```

O datadir temporário pode ser arquivado/removido após verificar seu caminho; a suíte elimina apenas os bancos que ela própria criou, com nome validado. CLI fixture conserva seu banco até limpeza explícita/desligamento do ambiente descartável.

Ao encerrar esta validação, o banco sintético de demonstração da CLI também foi removido e a instância temporária dedicada foi desligada. O datadir de teste foi conservado no diretório temporário; nenhum serviço do sistema foi alterado.

## 15. Regressões

| Suíte existente (intacta)         | Resultado |
| --------------------------------- | --------: |
| Alvo offline 1A                   |       230 |
| Read-only 1A no banco configurado |        14 |
| Alvo offline 1B                   |       169 |
| Read-only 1B no banco configurado |        26 |
| Caracterização histórica          |     1.312 |
| Custos V2                         |       329 |

Todas passaram, além do lint dos sete PHP envolvidos e da CLI prática. Shadows auditados em 02/10/2026: 1A congelado, 10 casos/16 diferenças esperadas; 1B congelado, três recortes/5 esperadas; 1A vivo, 14 pares/526 esperadas; 1B vivo, cinco pares/8 esperadas. **Zero UNEXPECTED_DIFFERENCE** em todos. Diferenças são D01/D02 e mudanças alvo já aprovadas, não adaptação do golden.

Pares vivos 1A: 27/set2026, 20/nov2025, 6/set2026, 8/set/ago2026, 13/set/ago2026, 1/jan2025/ago2026, 7/ago2026, 4/ago2026, 33/ago/set2026, 40/abr2026. 1B: 7/ago2026, 4/ago2026, 1/ago2026, 1/jan2025, 8/set2026. Cada par usa seu próprio snapshot read-only; não houve lote financeiro.

Golden SHA-256 preservado: `86e743c3d3ff0baf98ad40b74c2403ec3d884c3366d48501dea1f00fce535b20`. Testes existentes e motores puros foram conferidos contra hashes do começo desta fase. Arquivos produtivos de Pagamento/geração/confirmação mantêm os hashes iniciais desta fase; alterações anteriores/exteriores do workspace não são atribuídas a esta entrega.

## 16. Dados legados e interface

Nada foi reinterpretado/migrado automaticamente de adendos antigos ou dos totais agregados. Cabeçalhos e payloads projetados são evidência informativa preservada no snapshot. Legado indeterminado permanece indeterminado até ato autorizado. Não existe conversão heurística para fixo pago, SEM_BONUS ou extras perdidos; não há alteração de ledger/configuração.

Nenhum endpoint/UI produtivo foi integrado: Pagamento/index.php/script.js, gerar_adendo.php, confirmar_adendo.php e AdendoLocalService não receberam mudanças desta fase. Não há comportamento visível novo para navegar/testar pela tela antiga; validação prática foi serviço/CLI/DB isolado e readers reais. Navegação autenticada nas URLs oficiais e responsividade continuam requisitos da integração futura. Não se reivindica teste de PDF/DOM por meio desta validação backend.

## 17. Riscos e limites

1. Migration e writer ainda precisam de homologação em MySQL 8 isolado antes de implantação nesse destino. DDL/grants/triggers devem ser verificados pelo responsável; nenhum pedido de aplicar no compartilhado foi presumido.
2. Evidência com origem/referência é declaração administrativa rastreável; serviço não verifica autenticidade de recibo externo. Aprovação financeira exige conferência humana do documento/origem e conjunto completo aplicável.
3. Snapshot representa visão consistente no preparo; pagamento/cadastro posterior muda próxima revisão, não atualiza antiga. PRONTO não significa enviado, assinado ou quitado.
4. Hash detecta corrupção/mudança de conteúdo e não substitui assinatura, backup ou controle de privilégios contra DBA. Preserve snapshots/atos/chaves juntos em backup/retention.
5. JSON de inputs/resultados aumenta armazenamento; não houve benchmark mensal/estresse longo. Locks são por fechamento e compartilhados de autor; timeout/deadlock exige retry idempotente.
6. Futura exposição HTTP deve usar sessão/CSRF, validação de chave/expected_version e respostas de conflito claras. Não publicar CLI como endpoint nem aceitar ID/nível selecionado pelo navegador.
7. Alteração cadastral exige revisão explícita do ato mensal cujo original divergiu. Não rebasear override/SEM_VALOR_FIXO silenciosamente para fazer PRONTO.

## 18. Critérios para 1C-B

Mediante novo pedido, escolher **ID de revisão financeira específica**, verificar hash e condição PRONTO e usar somente esse snapshot na geração. Definir vínculo de documento/hash próprio, preview, confirmação da versão visualizada e idempotência documental. Preservar revisões/documentos anteriores; não reaproveitar token/URL/assinatura. Implantação do schema no destino exige autorização e homologação prévias.

Validar futura integração no navegador interno autenticado pelas URLs oficiais, com desktop/notebook/iPad e mobile quando aplicável. Não aceitar DOM como fonte de valores/itens; apresentar pendências e null corretamente. Reutilizar Thinking Orbs global se houver loading. Nenhum PDF, preview, envio, confirmação, ZapSign, assinatura, scheduler, lote ou Revisar próximo foi implementado/iniciado aqui.

**FASE 1C-A CONCLUÍDA**, no escopo autorizado de persistência financeira/revisão, provada em ambiente isolado. A aplicação no banco compartilhado e a FASE 1C-B não foram iniciadas.
