# Deployment controlado — Pagamento / Adendos

**Atualização 05/10/2026:** o bloqueio legado descrito abaixo foi resolvido e validado em tarefa posterior explicitamente autorizada. **FLAG OFF; PRONTO PARA CANARY**, sem iniciar canary. [Resolução, hashes e regressões](pagamento-adendos-legado-restaurado.md). O restante deste relatório conserva a evidência da janela original.

**05/10/2026, 20:46–20:52 (America/Sao_Paulo). Estrutura aplicada; gate do legado bloqueado. FLAG OFF. Canary não liberado.**

Backup preservado localmente e no servidor: `flowdb_pre_fechamento_2026-10-05_20-15-50_2c42b1.sql`, 97.143.354 bytes. SHA-256 conferido antes do primeiro DDL, entre etapas e ao encerrar: `c58d9613518e57ecf22158fb5db904112a2f9dca14912e57db0464906209e667`. [Backup/restore anterior](pagamento-adendos-deployment-unblock.md).

As 313 tabelas e demais definições legadas foram comparadas ao restore do backup antes e depois das migrations: sem mudança estrutural. A comparação dos bodies preservou literais e ignorou somente espaços/comentários SQL não executáveis. [Gate pré-DDL](evidence/pagamento-deployment-prepare-2026-10-05.json).

| Etapa em `flowdb` | Execução e validação |
|---|---|
| 1C-A, `2026-10-02_pagamento_fechamento_revisao.sql` | Exit 0; **6 tabelas / 10 triggers**, definições integrais iguais à migration aplicada na referência isolada. [Evidência](evidence/pagamento-deployment-revisao-2026-10-05.json) |
| 1C-B, `2026-10-02_pagamento_fechamento_documento.sql` | Executada somente após 1C-A validada; exit 0; **8 tabelas / 14 triggers** no total. [Evidência](evidence/pagamento-deployment-documento-2026-10-05.json) |

Validados engines InnoDB, colunas/tipos/JSON, FKs/regras/schema referenciado, CHECKs/enforcement, índices, DEFINER `debian-sys-maint@localhost`, bodies, SQL_MODE e collations. Hashes SQL: 1C-A `ac513ca0e03e355624bbc875c59844f499734748f85d82a7efa36075d0493243`; 1C-B `3d02fd459fbfc60bb6165e184af59a2f884eb2b07d1c142558374809d9d31282`. O relógio remoto está aproximadamente 1m45s atrás do local; os dois horários estão nos manifestos e não foram ajustados.

Storage configurado: `C:/ProgramData/ImproovWeb/private/pagamento-fechamento`, com ACL previamente validada para `IMP-PC011\usuario`. Configuração privada em `C:/ProgramData/ImproovWeb/private/apache/pagamento-fechamento.conf`, incluída pelo `httpd.conf`, sem credenciais. `PAGAMENTO_FECHAMENTO_V2_ENABLED=0`.

Apache: backup do `httpd.conf` preservado em diretório privado; `httpd -t` **Syntax OK, exit 0**. Reload graceful da instância console real às 20:49:02 pelo evento nativo `ap2960_restart`; parent 2960 permaneceu, child 13296 foi substituído por 23240 e terminou normalmente. O mecanismo é o utilizado pelo [MPM Windows oficial](https://raw.githubusercontent.com/apache/httpd/2.4.x/server/mpm/winnt/mpm_winnt.c). [Configuração/ACL/reload](evidence/pagamento-deployment-apache-2026-10-05.json). Houve uma interrupção do wrapper PowerShell ao tratar `Syntax OK` em stderr como erro; corrigida a captura e retomado somente o reload, após conferir os bytes da configuração. Nenhum DDL foi repetido.

Pre-flight `php scripts/check_pagamento_fechamento_deployment.php --readiness`: **exit 0**, MySQL 8.0.46 OK, InnoDB OK, financial/1C-A OK, documental/1C-B OK, storage OK, **8 tabelas / 14 triggers / FLAG OFF**. O checker CLI recebeu as mesmas variáveis do arquivo privado Apache. Os campos `PRE_MIGRATION`/`PRESENT_REQUIRES_AUDIT` são rótulos do checker; a auditoria integral está nos manifestos das migrations. [Pre-flight](evidence/pagamento-deployment-readiness-2026-10-05.json).

Validação autenticada por Financeiro → Pagamento nas duas URLs oficiais: login e visão geral OK (19 linhas carregadas), sem link para a nova tela. **Por colaborador falhou nas duas URLs:** “Não foi possível carregar as tarefas.” O lint e o log apontam `PHP Parse error: unexpected token <<`, em [getColaborador.php](../Pagamento/getColaborador.php), linha **774**, devido a marcadores de conflito Git; há outro bloco na linha 814. O erro já constava no log às 18:38:20, antes deste deployment. [Evidência do bloqueio](evidence/pagamento-deployment-legacy-2026-10-05.json).

**Falta resolver os conflitos desse arquivo e repetir a validação legada para liberar o canary.** Os blocos conflitantes contêm implementações diferentes de cálculo de valor e agregação financeira; escolher uma delas ampliaria esta janela de deployment para uma alteração da lógica financeira legada. Não foram alteradas essas implementações durante a janela controlada.

As oito tabelas novas têm zero registros. Nenhum fechamento, decisão, PDF, confirmação, grant, variável global de produção, correção de órfãos ou FASE 1E foi executado. A referência descartável foi removida e o MySQL isolado 3321 encerrado; dump/SHA, logs privados e backups de configuração foram preservados. [Encerramento](evidence/pagamento-deployment-end-2026-10-05.json).
