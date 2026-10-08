# Pagamento / Adendos — desbloqueio da implantação

05/10/2026, America/Sao_Paulo. **BACKUP: OK · RESTORE: OK · PRONTO PARA AUTORIZAR DEPLOYMENT.**

| BLOQUEIO | ANTES | AGORA | EVIDÊNCIA | PRÓXIMA AÇÃO |
| --- | --- | --- | --- | --- |
| Conta DDL / triggers | Capacidade global não comprovada | **OK:** `debian-sys-maint@localhost`, com SUPER/CREATE/REFERENCES/TRIGGER | [Identidade e grants](evidence/pagamento-unblock-dba-2026-10-05.json) | Usar a conta local na futura janela autorizada; preservar o DEFINER |
| Backup completo novo | Último completo: 02/10; runtime sem acesso às routines | **OK:** novo dump MySQL 8, exit 0, footer e SHA conferidos | [Manifesto](evidence/pagamento-unblock-backup-2026-10-05.json) | Preservar dump/SHA e conferir atualidade na janela pré-DDL |
| Restore MySQL 8 | Sem teste recente | **OK:** importação integral; readers 1A/1B passaram | [Restore](evidence/pagamento-unblock-restore-2026-10-05.json), [fidelidade](evidence/pagamento-unblock-fidelity-2026-10-05.json) | Autorizar a janela; nenhuma migration aplicada nesta tarefa |
| Storage privado | Diretórios inexistentes | **STORAGE: OK** | [Teste sob identidade PHP](evidence/pagamento-unblock-storage-2026-10-05.json) | Configurar Apache na futura janela, inicialmente com flag OFF |
| ACL | Não provisionada | **ACL: OK:** usuario Modify; SYSTEM/Administrators FullControl; sem Users/Everyone/reparse points | [ACL e caminhos](evidence/pagamento-unblock-acl-2026-10-05.json) | Revalidar se a identidade Apache mudar |

## Backup preservado

| Informação | Valor |
| --- | --- |
| Nome | `flowdb_pre_fechamento_2026-10-05_20-15-50_2c42b1.sql` |
| Origem | `72.60.137.192:3306` / `flowdb` |
| Cliente | **MySQL mysqldump 8.0.46**, Ubuntu; executado diretamente no servidor via SSH |
| Finalização | **05/10/2026 20:15:54**, America/Sao_Paulo; exit **0**; footer `Dump completed` presente |
| Tamanho | **97.143.354 bytes** |
| SHA-256 | `c58d9613518e57ecf22158fb5db904112a2f9dca14912e57db0464906209e667` |
| Cópia remota | `/root/ImproovWeb-deployment-backups/flowdb_pre_fechamento_2026-10-05_20-15-50_2c42b1.sql` — diretório 0700, dump 0600 |
| Cópia local | `C:/ProgramData/ImproovWeb/private/deployment-backups/flowdb_pre_fechamento_2026-10-05_20-15-50_2c42b1.sql` — ACL restrita validada |
| Transferência | **SFTP**, host key conferida; tamanho/SHA locais iguais aos remotos |

Opções: `--defaults-file=/etc/mysql/debian.cnf --single-transaction --quick --routines --events --triggers --no-tablespaces --column-statistics=0 --set-gtid-purged=OFF`. Sem senha em argumentos/relatório, sem túnel ou cópia local da credencial DBA. Os metadados do schema permaneceram iguais antes/depois do dump. Sem rotação; nenhum backup existente apagado. Dump e manifesto preservados em ambos os ambientes.

## Restore validado

Instância exclusiva **127.0.0.1:3321**, MySQL **8.0.42**, datadir privado `C:/ProgramData/ImproovWeb/private/deployment-backups/mysql8_restore_20261005/data`, UUID distinto do destino. Database final: **`pagamento_restore_20261005_202130_60bcf6b7`**. Restaurado exatamente o arquivo/SHA acima, sem editar o dump ou usar `--force`, com exit **0**. Validação concluída às **20:24:33**.

- **313 tabelas**, todas **InnoDB**; **461.866 linhas**. Contagens de todas as tabelas iguais aos INSERTs do dump.
- **0 views** na origem/dump/restore; **10 routines**, **28 triggers legados**, **3 eventos**. Inventários iguais ao dump e aos metadados de origem.
- **447 FKs**, com 453 componentes de coluna e definições preservadas; integridade varrida em todas as relações.
- **6 CHECKs**, todos ENFORCED, expressões preservadas e **zero violações** nos dados restaurados.
- As **15 origens financeiras/jurídicas da 1A/1B** estão presentes/InnoDB; contagens no JSON. Exemplos: `funcao_imagem` 9.416; `log_alteracoes` 37.225; `pagamento_itens` 3.783; `pagamentos` 197; `adendos` 163.
- Readers reais **1A e 1B: OK**, seis chamadas, três colaboradores com registros e competência `2026-09`. Sem persistência, decisão, fechamento ou PDF; snapshots/valores pessoais não publicados.
- **event_scheduler OFF** antes/depois da importação; nenhum evento restaurado executado. Instância desligada após os testes.

**Achado legado preservado:** 12 relações FK contêm **40 referências órfãs**, incluindo algumas origens financeiras. As contagens dessas relações são **iguais na origem e no restore**, comprovadas com SELECT COUNT em transação READ ONLY; todas as contagens de linhas também são iguais ao dump. Isso não é perda introduzida pelo backup/restore. Nenhum dado foi corrigido. O teste comprova fidelidade e leitura utilizável, sem afirmar que todo o legado está livre de inconsistências. [Agregados](evidence/pagamento-unblock-fidelity-2026-10-05.json).

O primeiro import passou, mas o verificador tentou executar o escaping de apresentação de `CHECK_CLAUSE`. Ele foi corrigido para usar o SQL de `SHOW CREATE TABLE`; o teste final repetiu o mesmo dump e concluiu todas as verificações. **Somente os dois databases descartáveis desta tarefa foram removidos.** Nenhuma migration nova aplicada nem no restore.

## Estado do destino e próxima janela

[Readiness final](evidence/pagamento-unblock-readiness-2026-10-05.json): **MYSQL OK; INNODB OK; STORAGE OK; FLAG OFF; SCHEMA ABSENT; TRIGGERS ABSENT**. O checker consulta a runtime: `DDL_ACCOUNT=DBA_REQUIRED` não invalida a conta DBA externa já verificada. `PRE_MIGRATION` está separado de `migrations_applied`; exit 2 esperado pela ausência das migrations.

Storage: `C:/ProgramData/ImproovWeb/private/pagamento-fechamento/{staging,definitivo,locks}`. Create/write/fflush/fsync/rename/lock entre processos/read/delete passaram sob `IMP-PC011\usuario`; fora do webroot, sem aliases Apache conhecidos que o publiquem. Configuração/reload Apache e ativação não ocorreram.

Na janela futura autorizada, usar `mysql --defaults-file=/etc/mysql/debian.cnf --database=flowdb` no servidor: primeiro `sql/2026-10-02_pagamento_fechamento_revisao.sql`, depois `sql/2026-10-02_pagamento_fechamento_documento.sql`, parando no primeiro erro e auditando cada etapa. A conta aplicadora torna-se DEFINER e deve permanecer válida com os privilégios necessários. Não mudar `log_bin_trust_function_creators`. Esse procedimento **não foi executado**. Runtime mínimo é hardening futuro, não gate inicial.

Backup automático antigo: último dump completo continua sendo 02/10 apesar do resultado 0 da tarefa de 05/10. O script não persiste stderr, retorna sucesso do PHP mesmo quando o dump falha e faz rotação após erro. Sem log histórico, causa exata não comprovada. Patch pequeno proposto, **não aplicado**: persistir stderr sanitizado e terminar com exit 2 antes da rotação quando exec/tamanho/rename/footer falharem. Essa manutenção não bloqueia o gate manual agora comprovado.

**Nenhuma migration/tabela/trigger/grant/variável global foi alterada no flowdb. Flag OFF; nenhum fechamento/PDF real; FASE 1E não iniciada. PRONTO PARA AUTORIZAR DEPLOYMENT, sem autorização implícita para executá-lo.**
