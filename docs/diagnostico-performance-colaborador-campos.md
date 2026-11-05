# Inventário de campos — Performance do Colaborador

Data: 05/11/2026. Anexo do [diagnóstico técnico](diagnostico-performance-colaborador.md).

Fonte exclusiva deste inventário: definições do backup `Backup/backup_completo_2026-11-05_02-00-01.sql`. Não é introspecção do banco ativo, nem migration proposta. Nenhum DDL foi executado. Os campos abaixo são os existentes no snapshot; regras de gravação, autoria, qualidade e cobertura estão no relatório principal.

`TIMESTAMP` é exportado com conexão do dump em UTC; `DATETIME` não carrega fuso. Defaults e `ON UPDATE` não garantem histórico de cada transição. Relação lógica sem FK não aparece nas restrições. A ausência de uma FK é relevante para retenção e integridade.

`cronograma_funcao_imagem` e `cronograma_entrega`: não localizadas no snapshot nem nos fluxos pesquisados; não há campos confirmados a listar.

## funcao_imagem

Fonte: backup, linha 5798. Engine: InnoDB.

| Campo                  | Definição existente                                                          |
| ---------------------- | ---------------------------------------------------------------------------- |
| `idfuncao_imagem`      | `int NOT NULL AUTO_INCREMENT`                                                |
| `imagem_id`            | `int NOT NULL`                                                               |
| `colaborador_id`       | `int DEFAULT NULL`                                                           |
| `funcao_id`            | `int NOT NULL`                                                               |
| `prazo`                | `date DEFAULT NULL`                                                          |
| `status`               | `varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL`  |
| `observacao`           | `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `pagamento`            | `tinyint(1) DEFAULT '0'`                                                     |
| `valor`                | `double DEFAULT '0'`                                                         |
| `data_pagamento`       | `date DEFAULT NULL`                                                          |
| `requires_file_upload` | `tinyint(1) NOT NULL DEFAULT '0'`                                            |
| `requires_render_send` | `tinyint(1) NOT NULL DEFAULT '0'`                                            |
| `file_uploaded_at`     | `datetime DEFAULT NULL`                                                      |
| `valor_aprovado`       | `tinyint(1) NOT NULL DEFAULT '0'`                                            |
| `prioridade_aprovacao` | `tinyint(1) NOT NULL DEFAULT '0'`                                            |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`idfuncao_imagem`)`
- `UNIQUE KEY `unique_funcao_imagem` (`imagem_id`,`funcao_id`)`
- `KEY `funcao_id` (`funcao_id`)`
- `KEY `status_funcao_imagem` (`status`)`
- `KEY `funcao_imagem_colaborador` (`colaborador_id`)`
- `KEY `idx_funcao_requires_upload` (`requires_file_upload`)`
- `CONSTRAINT `funcao_imagem_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaborador` (`idcolaborador`)`
- `CONSTRAINT `funcao_imagem_ibfk_1` FOREIGN KEY (`imagem_id`) REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`) ON DELETE CASCADE`
- `CONSTRAINT `funcao_imagem_ibfk_3` FOREIGN KEY (`funcao_id`) REFERENCES `funcao` (`idfuncao`)`

## log_alteracoes

Fonte: backup, linha 7754. Engine: InnoDB.

| Campo              | Definição existente                                                          |
| ------------------ | ---------------------------------------------------------------------------- |
| `idlog`            | `int NOT NULL AUTO_INCREMENT`                                                |
| `funcao_imagem_id` | `int NOT NULL`                                                               |
| `arquivo_log_id`   | `int DEFAULT NULL`                                                           |
| `status_anterior`  | `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `status_novo`      | `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `data`             | `timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP`                               |
| `colaborador_id`   | `int DEFAULT NULL`                                                           |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`idlog`)`
- `KEY `log_colaborador` (`colaborador_id`)`
- `KEY `fk_funcao_nova` (`funcao_imagem_id`)`
- `CONSTRAINT `fk_funcao_nova` FOREIGN KEY (`funcao_imagem_id`) REFERENCES `funcao_imagem` (`idfuncao_imagem`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `log_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaborador` (`idcolaborador`)`

## historico_aprovacoes

Fonte: backup, linha 6791. Engine: InnoDB.

| Campo                | Definição existente                                                         |
| -------------------- | --------------------------------------------------------------------------- |
| `id`                 | `int NOT NULL AUTO_INCREMENT`                                               |
| `funcao_imagem_id`   | `int DEFAULT NULL`                                                          |
| `funcao_animacao_id` | `int DEFAULT NULL`                                                          |
| `arquivo_log_id`     | `int DEFAULT NULL`                                                          |
| `status_anterior`    | `varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `status_novo`        | `varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `data_aprovacao`     | `datetime DEFAULT CURRENT_TIMESTAMP`                                        |
| `colaborador_id`     | `int DEFAULT NULL`                                                          |
| `observacoes`        | `text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`                     |
| `responsavel`        | `int NOT NULL`                                                              |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `colaborador_id` (`colaborador_id`)`
- `KEY `fk_responsavel` (`responsavel`)`
- `KEY `fk_funcao` (`funcao_imagem_id`)`
- `KEY `idx_ha_funcao_animacao` (`funcao_animacao_id`,`data_aprovacao`)`
- `CONSTRAINT `fk_funcao` FOREIGN KEY (`funcao_imagem_id`) REFERENCES `funcao_imagem` (`idfuncao_imagem`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `fk_responsavel` FOREIGN KEY (`responsavel`) REFERENCES `colaborador` (`idcolaborador`)`
- `CONSTRAINT `historico_aprovacoes_ibfk_2` FOREIGN KEY (`colaborador_id`) REFERENCES `colaborador` (`idcolaborador`)`

## historico_aprovacoes_imagens

Fonte: backup, linha 6832. Engine: InnoDB.

| Campo                | Definição existente                                                          |
| -------------------- | ---------------------------------------------------------------------------- |
| `id`                 | `int NOT NULL AUTO_INCREMENT`                                                |
| `funcao_imagem_id`   | `int DEFAULT NULL`                                                           |
| `funcao_animacao_id` | `int DEFAULT NULL`                                                           |
| `imagem`             | `longblob`                                                                   |
| `indice_envio`       | `int NOT NULL`                                                               |
| `data_envio`         | `datetime DEFAULT CURRENT_TIMESTAMP`                                         |
| `nome_arquivo`       | `text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`                      |
| `caminho_imagem`     | `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `media_tipo`         | `varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'imagem'`           |
| `mime_type`          | `varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                       |
| `tamanho`            | `bigint DEFAULT NULL`                                                        |
| `duracao_ms`         | `int DEFAULT NULL`                                                           |
| `poster_path`        | `varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                       |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `historico_aprovacoes_imagens_ibfk_1` (`funcao_imagem_id`)`
- `KEY `idx_hai_funcao_animacao` (`funcao_animacao_id`,`indice_envio`,`data_envio`)`
- `KEY `idx_hai_media_tipo` (`media_tipo`)`
- `CONSTRAINT `historico_aprovacoes_imagens_ibfk_1` FOREIGN KEY (`funcao_imagem_id`) REFERENCES `funcao_imagem` (`idfuncao_imagem`) ON DELETE CASCADE`

## imagens_cliente_obra

Fonte: backup, linha 7243. Engine: InnoDB.

| Campo                    | Definição existente                                                         |
| ------------------------ | --------------------------------------------------------------------------- |
| `idimagens_cliente_obra` | `int NOT NULL AUTO_INCREMENT`                                               |
| `cliente_id`             | `int NOT NULL`                                                              |
| `obra_id`                | `int NOT NULL`                                                              |
| `imagem_principal_id`    | `int DEFAULT NULL`                                                          |
| `imagem_nome`            | `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL`    |
| `recebimento_arquivos`   | `date DEFAULT NULL`                                                         |
| `data_inicio`            | `date DEFAULT NULL`                                                         |
| `prazo`                  | `date DEFAULT NULL`                                                         |
| `tipo_imagem`            | `varchar(55) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `subtipo_imagem`         | `varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                       |
| `status_id`              | `int DEFAULT NULL`                                                          |
| `antecipada`             | `tinyint(1) NOT NULL`                                                       |
| `dias_trabalhados`       | `int NOT NULL`                                                              |
| `animacao`               | `tinyint(1) NOT NULL DEFAULT '0'`                                           |
| `clima`                  | `varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL`    |
| `substatus_id`           | `int DEFAULT NULL`                                                          |
| `subtipo_id`             | `int DEFAULT NULL`                                                          |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`idimagens_cliente_obra`)`
- `KEY `cliente_id` (`cliente_id`)`
- `KEY `obra_id` (`obra_id`)`
- `KEY `status_imagem` (`status_id`)`
- `KEY `substatus_id` (`substatus_id`)`
- `KEY `fk_imagens_subtipo` (`subtipo_id`)`
- `KEY `idx_imagens_principal` (`imagem_principal_id`)`
- `CONSTRAINT `fk_imagens_imagem_principal` FOREIGN KEY (`imagem_principal_id`) REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`) ON DELETE RESTRICT ON UPDATE CASCADE`
- `CONSTRAINT `fk_imagens_subtipo` FOREIGN KEY (`subtipo_id`) REFERENCES `subtipo_imagem` (`id`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `imagens_cliente_obra_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `cliente` (`idcliente`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `imagens_cliente_obra_ibfk_2` FOREIGN KEY (`obra_id`) REFERENCES `obra` (`idobra`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `imagens_cliente_obra_ibfk_3` FOREIGN KEY (`substatus_id`) REFERENCES `substatus_imagem` (`id`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `status_imagem` FOREIGN KEY (`status_id`) REFERENCES `status_imagem` (`idstatus`) ON DELETE CASCADE ON UPDATE CASCADE`

## historico_imagens

Fonte: backup, linha 6873. Engine: InnoDB.

| Campo            | Definição existente                  |
| ---------------- | ------------------------------------ |
| `idhistorico`    | `int NOT NULL AUTO_INCREMENT`        |
| `imagem_id`      | `int NOT NULL`                       |
| `status_id`      | `int NOT NULL`                       |
| `substatus_id`   | `int NOT NULL`                       |
| `data_movimento` | `datetime DEFAULT CURRENT_TIMESTAMP` |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`idhistorico`)`
- `KEY `historico_imagens_ibfk_1` (`imagem_id`)`
- `KEY `historico_imagens_ibfk_2` (`status_id`)`
- `KEY `historico_imagens_ibfk_3` (`substatus_id`)`
- `CONSTRAINT `historico_imagens_ibfk_1` FOREIGN KEY (`imagem_id`) REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`) ON DELETE CASCADE`
- `CONSTRAINT `historico_imagens_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `status_imagem` (`idstatus`) ON DELETE CASCADE`
- `CONSTRAINT `historico_imagens_ibfk_3` FOREIGN KEY (`substatus_id`) REFERENCES `substatus_imagem` (`id`) ON DELETE CASCADE`

## status_hold

Fonte: backup, linha 12948. Engine: InnoDB.

| Campo           | Definição existente                                                          |
| --------------- | ---------------------------------------------------------------------------- |
| `id`            | `int NOT NULL AUTO_INCREMENT`                                                |
| `justificativa` | `varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `imagem_id`     | `int DEFAULT NULL`                                                           |
| `obra_id`       | `int DEFAULT NULL`                                                           |
| `data_hold`     | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                                |
| `prazo`         | `date DEFAULT NULL`                                                          |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `imagem_id` (`imagem_id`)`
- `KEY `obra_id` (`obra_id`)`

## entregas

Fonte: backup, linha 3257. Engine: InnoDB.

| Campo              | Definição existente                                                                                                                                                            |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `id`               | `int NOT NULL AUTO_INCREMENT`                                                                                                                                                  |
| `status_id`        | `int NOT NULL`                                                                                                                                                                 |
| `tipo_entrega`     | `varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PADRAO'`                                                                                                             |
| `obra_id`          | `int DEFAULT NULL`                                                                                                                                                             |
| `data_prevista`    | `date DEFAULT NULL`                                                                                                                                                            |
| `data_conclusao`   | `date DEFAULT NULL`                                                                                                                                                            |
| `status`           | `enum('Pendente','Em andamento','Entregue no prazo','Entregue com atraso','Entrega antecipada','Parcial') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Pendente'` |
| `observacoes`      | `text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`                                                                                                                        |
| `em_hold`          | `tinyint(1) NOT NULL DEFAULT '0'`                                                                                                                                              |
| `motivo_hold`      | `text COLLATE utf8mb4_unicode_ci`                                                                                                                                              |
| `created_at`       | `timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP`                                                                                                                                 |
| `updated_at`       | `timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`                                                                                                     |
| `arquivada`        | `tinyint(1) NOT NULL DEFAULT '0'`                                                                                                                                              |
| `data_recebimento` | `date DEFAULT NULL`                                                                                                                                                            |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `status_id` (`status_id`)`
- `KEY `obra_id` (`obra_id`)`
- `CONSTRAINT `entregas_ibfk_1` FOREIGN KEY (`status_id`) REFERENCES `status_imagem` (`idstatus`)`
- `CONSTRAINT `entregas_ibfk_2` FOREIGN KEY (`obra_id`) REFERENCES `obra` (`idobra`)`

## entregas_itens

Fonte: backup, linha 3398. Engine: InnoDB.

| Campo           | Definição existente                                                                                                                                                      |
| --------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `id`            | `int NOT NULL AUTO_INCREMENT`                                                                                                                                            |
| `entrega_id`    | `int NOT NULL`                                                                                                                                                           |
| `imagem_id`     | `int NOT NULL`                                                                                                                                                           |
| `data_prevista` | `timestamp NULL DEFAULT NULL`                                                                                                                                            |
| `data_entregue` | `timestamp NULL DEFAULT NULL`                                                                                                                                            |
| `status`        | `enum('Pendente','Entregue no prazo','Entregue com atraso','Entrega antecipada','Entrega pendente') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Pendente'` |
| `observacoes`   | `text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`                                                                                                                  |
| `created_at`    | `timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP`                                                                                                                           |
| `updated_at`    | `timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`                                                                                               |
| `historico_id`  | `int DEFAULT NULL`                                                                                                                                                       |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `entregas_itens_ibfk_1` (`entrega_id`)`
- `KEY `entregas_itens_ibfk_2` (`imagem_id`)`
- `KEY `historico_id` (`historico_id`)`
- `CONSTRAINT `entregas_itens_ibfk_1` FOREIGN KEY (`entrega_id`) REFERENCES `entregas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `entregas_itens_ibfk_2` FOREIGN KEY (`imagem_id`) REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `entregas_itens_ibfk_3` FOREIGN KEY (`historico_id`) REFERENCES `historico_aprovacoes_imagens` (`id`)`

## entregas_p00_versoes

Fonte: backup, linha 3479. Engine: InnoDB.

| Campo                      | Definição existente                                                       |
| -------------------------- | ------------------------------------------------------------------------- |
| `id`                       | `int NOT NULL AUTO_INCREMENT`                                             |
| `entrega_id`               | `int NOT NULL`                                                            |
| `versao_num`               | `int NOT NULL`                                                            |
| `versao_label`             | `varchar(20) NOT NULL`                                                    |
| `versao_origem_id`         | `int DEFAULT NULL`                                                        |
| `status`                   | `varchar(50) NOT NULL DEFAULT 'Pendente'`                                 |
| `imagem_id`                | `int DEFAULT NULL`                                                        |
| `funcao_imagem_id`         | `int DEFAULT NULL`                                                        |
| `historico_id`             | `int DEFAULT NULL`                                                        |
| `arquivo_principal`        | `varchar(255) DEFAULT NULL`                                               |
| `arquivos_json`            | `mediumtext`                                                              |
| `nas_path`                 | `varchar(255) DEFAULT NULL`                                               |
| `data_prevista`            | `date DEFAULT NULL`                                                       |
| `data_aprovacao`           | `datetime DEFAULT NULL`                                                   |
| `data_entregue`            | `datetime DEFAULT NULL`                                                   |
| `origem_alteracao`         | `varchar(50) DEFAULT NULL`                                                |
| `origem_alteracao_detalhe` | `varchar(255) DEFAULT NULL`                                               |
| `created_at`               | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                             |
| `updated_at`               | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uniq_entregas_p00_versao` (`entrega_id`,`versao_num`)`
- `KEY `idx_entregas_p00_entrega_status` (`entrega_id`,`status`)`

## entregas_pendencias

Fonte: backup, linha 3522. Engine: InnoDB.

| Campo              | Definição existente                                                                                                |
| ------------------ | ------------------------------------------------------------------------------------------------------------------ |
| `id`               | `int NOT NULL AUTO_INCREMENT`                                                                                      |
| `obra_id`          | `int NOT NULL`                                                                                                     |
| `status_id`        | `int NOT NULL`                                                                                                     |
| `imagem_id`        | `int NOT NULL`                                                                                                     |
| `funcao_imagem_id` | `int NOT NULL`                                                                                                     |
| `historico_id`     | `int DEFAULT NULL`                                                                                                 |
| `motivo`           | `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                       |
| `status`           | `enum('aberta','resolvida','ignorada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aberta'` |
| `entrega_id`       | `int DEFAULT NULL`                                                                                                 |
| `entrega_item_id`  | `int DEFAULT NULL`                                                                                                 |
| `criada_em`        | `timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP`                                                                     |
| `resolvida_em`     | `timestamp NULL DEFAULT NULL`                                                                                      |
| `resolvida_por`    | `int DEFAULT NULL`                                                                                                 |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `idx_entregas_pendencias_status` (`status`)`
- `KEY `idx_entregas_pendencias_obra_status` (`obra_id`,`status_id`)`
- `KEY `idx_entregas_pendencias_imagem_status` (`imagem_id`,`status_id`)`
- `KEY `idx_entregas_pendencias_funcao` (`funcao_imagem_id`)`
- `KEY `idx_entregas_pendencias_entrega` (`entrega_id`,`entrega_item_id`)`

## janela_operacional_ciclo

Fonte: backup, linha 7444. Engine: InnoDB.

| Campo                             | Definição existente                                                                                           |
| --------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| `id`                              | `bigint unsigned NOT NULL AUTO_INCREMENT`                                                                     |
| `chave_referencia`                | `varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL`                                                            |
| `numero_ciclo`                    | `smallint unsigned DEFAULT NULL`                                                                              |
| `origem_abertura`                 | `varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                                         |
| `qualidade_dados`                 | `varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CANONICO'`                                          |
| `ciclo_anterior_id`               | `bigint unsigned DEFAULT NULL`                                                                                |
| `unidade_trabalho_id`             | `bigint unsigned DEFAULT NULL`                                                                                |
| `perfil_id`                       | `bigint unsigned DEFAULT NULL`                                                                                |
| `perfil_codigo_snapshot`          | `varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                                         |
| `perfil_versao_snapshot`          | `int unsigned DEFAULT NULL`                                                                                   |
| `perfil_nome_snapshot`            | `varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                                        |
| `aplica_regra_snapshot`           | `tinyint(1) DEFAULT NULL`                                                                                     |
| `limite_dias_uteis_snapshot`      | `smallint unsigned DEFAULT NULL`                                                                              |
| `inicio_em`                       | `datetime DEFAULT NULL`                                                                                       |
| `limite_data_original`            | `date DEFAULT NULL`                                                                                           |
| `limite_data_atual`               | `date DEFAULT NULL`                                                                                           |
| `prazo_necessario_snapshot`       | `date DEFAULT NULL`                                                                                           |
| `planejamento_versao_id_snapshot` | `bigint DEFAULT NULL`                                                                                         |
| `previsao_original`               | `date DEFAULT NULL`                                                                                           |
| `previsao_atual`                  | `date DEFAULT NULL`                                                                                           |
| `estado_original`                 | `enum('NORMAL','EXCECAO_OPERACIONAL','CONFLITO_PLANEJAMENTO') COLLATE utf8mb4_unicode_ci DEFAULT NULL`        |
| `estado_atual`                    | `enum('NORMAL','EXCECAO_OPERACIONAL','CONFLITO_PLANEJAMENTO') COLLATE utf8mb4_unicode_ci DEFAULT NULL`        |
| `motivo_original_id`              | `bigint unsigned DEFAULT NULL`                                                                                |
| `motivo_original_codigo`          | `varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                                         |
| `motivo_original_label`           | `varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                                        |
| `motivo_original_texto`           | `varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                                        |
| `responsavel_original_id`         | `int NOT NULL`                                                                                                |
| `responsavel_atual_id`            | `int NOT NULL`                                                                                                |
| `situacao`                        | `enum('AGUARDANDO_INICIO','ATIVO','PAUSADO','ENCERRADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ATIVO'` |
| `ativo_token`                     | `varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'ATIVO'`                                                      |
| `encerrado_em`                    | `datetime DEFAULT NULL`                                                                                       |
| `motivo_encerramento`             | `varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                                         |
| `status_saida`                    | `varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                                         |
| `criado_por_colaborador_id`       | `int DEFAULT NULL`                                                                                            |
| `criado_por_usuario_id`           | `int DEFAULT NULL`                                                                                            |
| `criado_em`                       | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                                                                 |
| `atualizado_em`                   | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`                                     |
| `entrega_item_id`                 | `int DEFAULT NULL`                                                                                            |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uq_janela_ciclo_ativo` (`chave_referencia`,`ativo_token`)`
- `UNIQUE KEY `uq_janela_ciclo_numero` (`chave_referencia`,`numero_ciclo`)`
- `KEY `idx_janela_ciclo_estado` (`situacao`,`estado_atual`,`responsavel_atual_id`)`
- `KEY `idx_janela_ciclo_inicio` (`inicio_em`)`
- `KEY `idx_janela_ciclo_perfil` (`perfil_codigo_snapshot`,`criado_em`)`
- `KEY `fk_janela_ciclo_anterior` (`ciclo_anterior_id`)`
- `KEY `fk_janela_ciclo_unidade` (`unidade_trabalho_id`)`
- `KEY `fk_janela_ciclo_perfil` (`perfil_id`)`
- `KEY `fk_janela_ciclo_motivo_original` (`motivo_original_id`)`
- `KEY `fk_janela_ciclo_resp_original` (`responsavel_original_id`)`
- `KEY `fk_janela_ciclo_resp_atual` (`responsavel_atual_id`)`
- `KEY `fk_janela_ciclo_criador` (`criado_por_colaborador_id`)`
- `KEY `idx_janela_ciclo_origem_situacao` (`origem_abertura`,`situacao`)`
- `KEY `idx_janela_ciclo_entrega_item` (`entrega_item_id`)`
- `CONSTRAINT `fk_janela_ciclo_anterior` FOREIGN KEY (`ciclo_anterior_id`) REFERENCES `janela_operacional_ciclo` (`id`) ON DELETE SET NULL`
- `CONSTRAINT `fk_janela_ciclo_criador` FOREIGN KEY (`criado_por_colaborador_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE SET NULL`
- `CONSTRAINT `fk_janela_ciclo_entrega_item` FOREIGN KEY (`entrega_item_id`) REFERENCES `entregas_itens` (`id`)`
- `CONSTRAINT `fk_janela_ciclo_motivo_original` FOREIGN KEY (`motivo_original_id`) REFERENCES `janela_operacional_motivo` (`id`) ON DELETE SET NULL`
- `CONSTRAINT `fk_janela_ciclo_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `janela_operacional_perfil` (`id`) ON DELETE RESTRICT`
- `CONSTRAINT `fk_janela_ciclo_resp_atual` FOREIGN KEY (`responsavel_atual_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE RESTRICT`
- `CONSTRAINT `fk_janela_ciclo_resp_original` FOREIGN KEY (`responsavel_original_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE RESTRICT`
- `CONSTRAINT `fk_janela_ciclo_unidade` FOREIGN KEY (`unidade_trabalho_id`) REFERENCES `unidade_trabalho` (`id`) ON DELETE SET NULL`
- `CONSTRAINT `chk_janela_ciclo_limite` CHECK ((((`situacao` in (_utf8mb4'AGUARDANDO_INICIO',_utf8mb4'ENCERRADO')) and (`inicio_em` is null)) or ((`aplica_regra_snapshot` = 1) and (`limite_dias_uteis_snapshot` is not null) and (`limite_data_original` is not null)) or ((`aplica_regra_snapshot` = 0) and (`limite_dias_uteis_snapshot` is null) and (`limite_data_original` is null))))`

## janela_operacional_ciclo_item

Fonte: backup, linha 7527. Engine: InnoDB.

| Campo              | Definição existente                           |
| ------------------ | --------------------------------------------- |
| `ciclo_id`         | `bigint unsigned NOT NULL`                    |
| `funcao_imagem_id` | `int NOT NULL`                                |
| `ordem`            | `tinyint unsigned NOT NULL DEFAULT '1'`       |
| `criado_em`        | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP` |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`ciclo_id`,`funcao_imagem_id`)`
- `KEY `idx_janela_item_tarefa` (`funcao_imagem_id`,`ciclo_id`)`
- `CONSTRAINT `fk_janela_item_ciclo` FOREIGN KEY (`ciclo_id`) REFERENCES `janela_operacional_ciclo` (`id`) ON DELETE CASCADE`
- `CONSTRAINT `fk_janela_item_tarefa` FOREIGN KEY (`funcao_imagem_id`) REFERENCES `funcao_imagem` (`idfuncao_imagem`) ON DELETE RESTRICT`

## janela_operacional_evento

Fonte: backup, linha 7556. Engine: InnoDB.

| Campo                       | Definição existente                                    |
| --------------------------- | ------------------------------------------------------ |
| `id`                        | `bigint unsigned NOT NULL AUTO_INCREMENT`              |
| `ciclo_id`                  | `bigint unsigned NOT NULL`                             |
| `evento`                    | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL`      |
| `status_tarefa_anterior`    | `varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL`  |
| `status_tarefa_novo`        | `varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL`  |
| `estado_anterior`           | `varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL`  |
| `estado_novo`               | `varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL`  |
| `previsao_anterior`         | `date DEFAULT NULL`                                    |
| `previsao_nova`             | `date DEFAULT NULL`                                    |
| `prazo_necessario_snapshot` | `date DEFAULT NULL`                                    |
| `limite_data_snapshot`      | `date DEFAULT NULL`                                    |
| `motivo_id`                 | `bigint unsigned DEFAULT NULL`                         |
| `motivo_codigo_snapshot`    | `varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL`  |
| `motivo_label_snapshot`     | `varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `motivo_texto`              | `varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `responsavel_anterior_id`   | `int DEFAULT NULL`                                     |
| `responsavel_novo_id`       | `int DEFAULT NULL`                                     |
| `dias_uteis_consumidos`     | `smallint unsigned DEFAULT NULL`                       |
| `ator_colaborador_id`       | `int DEFAULT NULL`                                     |
| `ator_usuario_id`           | `int DEFAULT NULL`                                     |
| `detalhes`                  | `json DEFAULT NULL`                                    |
| `criado_em`                 | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`          |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `idx_janela_evento_ciclo_data` (`ciclo_id`,`criado_em`)`
- `KEY `idx_janela_evento_tipo_data` (`evento`,`criado_em`)`
- `KEY `idx_janela_evento_motivo` (`motivo_codigo_snapshot`,`criado_em`)`
- `KEY `fk_janela_evento_motivo` (`motivo_id`)`
- `CONSTRAINT `fk_janela_evento_ciclo` FOREIGN KEY (`ciclo_id`) REFERENCES `janela_operacional_ciclo` (`id`) ON DELETE CASCADE`
- `CONSTRAINT `fk_janela_evento_motivo` FOREIGN KEY (`motivo_id`) REFERENCES `janela_operacional_motivo` (`id`) ON DELETE SET NULL`

## janela_operacional_pausa

Fonte: backup, linha 7638. Engine: InnoDB.

| Campo                          | Definição existente                                              |
| ------------------------------ | ---------------------------------------------------------------- |
| `id`                           | `bigint unsigned NOT NULL AUTO_INCREMENT`                        |
| `ciclo_id`                     | `bigint unsigned NOT NULL`                                       |
| `inicio_em`                    | `datetime NOT NULL`                                              |
| `fim_em`                       | `datetime DEFAULT NULL`                                          |
| `dias_uteis_suspensos`         | `smallint unsigned DEFAULT NULL`                                 |
| `origem`                       | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'HOLD'` |
| `flow_issue_id`                | `bigint DEFAULT NULL`                                            |
| `ativa_token`                  | `varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'ATIVA'`         |
| `criado_por_colaborador_id`    | `int DEFAULT NULL`                                               |
| `criado_por_usuario_id`        | `int DEFAULT NULL`                                               |
| `encerrado_por_colaborador_id` | `int DEFAULT NULL`                                               |
| `encerrado_por_usuario_id`     | `int DEFAULT NULL`                                               |
| `criado_em`                    | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                    |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uq_janela_pausa_ativa` (`ciclo_id`,`ativa_token`)`
- `KEY `idx_janela_pausa_periodo` (`ciclo_id`,`inicio_em`,`fim_em`)`
- `CONSTRAINT `fk_janela_pausa_ciclo` FOREIGN KEY (`ciclo_id`) REFERENCES `janela_operacional_ciclo` (`id`) ON DELETE CASCADE`

## janela_operacional_perfil

Fonte: backup, linha 7676. Engine: InnoDB.

| Campo                       | Definição existente                                        |
| --------------------------- | ---------------------------------------------------------- |
| `id`                        | `bigint unsigned NOT NULL AUTO_INCREMENT`                  |
| `codigo`                    | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL`          |
| `versao`                    | `int unsigned NOT NULL DEFAULT '1'`                        |
| `nome`                      | `varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL`         |
| `aplica_regra`              | `tinyint(1) NOT NULL`                                      |
| `limite_dias_uteis`         | `smallint unsigned DEFAULT NULL`                           |
| `vigente`                   | `tinyint(1) NOT NULL DEFAULT '1'`                          |
| `vigente_token`             | `varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'VIGENTE'` |
| `criado_por_colaborador_id` | `int DEFAULT NULL`                                         |
| `criado_por_usuario_id`     | `int DEFAULT NULL`                                         |
| `criado_em`                 | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`              |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uq_janela_perfil_versao` (`codigo`,`versao`)`
- `UNIQUE KEY `uq_janela_perfil_vigente` (`codigo`,`vigente_token`)`
- `KEY `idx_janela_perfil_consulta` (`codigo`,`vigente`)`
- `KEY `fk_janela_perfil_criador` (`criado_por_colaborador_id`)`
- `CONSTRAINT `fk_janela_perfil_criador` FOREIGN KEY (`criado_por_colaborador_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE SET NULL`
- `CONSTRAINT `chk_janela_perfil_limite` CHECK ((((`aplica_regra` = 1) and (`limite_dias_uteis` is not null) and (`limite_dias_uteis` > 0)) or ((`aplica_regra` = 0) and (`limite_dias_uteis` is null))))`

## janela_operacional_motivo

Fonte: backup, linha 7606. Engine: InnoDB.

| Campo           | Definição existente                                                       |
| --------------- | ------------------------------------------------------------------------- |
| `id`            | `bigint unsigned NOT NULL AUTO_INCREMENT`                                 |
| `codigo`        | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL`                         |
| `label`         | `varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL`                        |
| `exige_texto`   | `tinyint(1) NOT NULL DEFAULT '0'`                                         |
| `ativo`         | `tinyint(1) NOT NULL DEFAULT '1'`                                         |
| `ordem`         | `smallint unsigned NOT NULL DEFAULT '0'`                                  |
| `criado_em`     | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                             |
| `atualizado_em` | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uq_janela_motivo_codigo` (`codigo`)`
- `KEY `idx_janela_motivo_ativo_ordem` (`ativo`,`ordem`)`

## unidade_trabalho

Fonte: backup, linha 13199. Engine: InnoDB.

| Campo                       | Definição existente                                                           |
| --------------------------- | ----------------------------------------------------------------------------- |
| `id`                        | `bigint unsigned NOT NULL AUTO_INCREMENT`                                     |
| `tipo`                      | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL`                             |
| `imagem_id`                 | `int NOT NULL`                                                                |
| `colaborador_id`            | `int NOT NULL`                                                                |
| `estado`                    | `enum('ATIVA','INATIVA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ATIVA'` |
| `criado_por_colaborador_id` | `int DEFAULT NULL`                                                            |
| `criado_por_usuario_id`     | `int DEFAULT NULL`                                                            |
| `criado_em`                 | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                                 |
| `atualizado_em`             | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`     |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uq_unidade_tipo_imagem` (`tipo`,`imagem_id`)`
- `KEY `idx_unidade_colaborador_estado` (`colaborador_id`,`estado`)`
- `KEY `fk_unidade_imagem` (`imagem_id`)`
- `KEY `fk_unidade_criador_colaborador` (`criado_por_colaborador_id`)`
- `CONSTRAINT `fk_unidade_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE RESTRICT`
- `CONSTRAINT `fk_unidade_criador_colaborador` FOREIGN KEY (`criado_por_colaborador_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE SET NULL`
- `CONSTRAINT `fk_unidade_imagem` FOREIGN KEY (`imagem_id`) REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`) ON DELETE CASCADE`

## unidade_trabalho_item

Fonte: backup, linha 13270. Engine: InnoDB.

| Campo                 | Definição existente                           |
| --------------------- | --------------------------------------------- |
| `unidade_trabalho_id` | `bigint unsigned NOT NULL`                    |
| `funcao_imagem_id`    | `int NOT NULL`                                |
| `ordem`               | `tinyint unsigned NOT NULL DEFAULT '1'`       |
| `criado_em`           | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP` |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`unidade_trabalho_id`,`funcao_imagem_id`)`
- `UNIQUE KEY `uq_unidade_item_funcao_imagem` (`funcao_imagem_id`)`
- `KEY `idx_unidade_item_ordem` (`unidade_trabalho_id`,`ordem`)`
- `CONSTRAINT `fk_unidade_item_funcao_imagem` FOREIGN KEY (`funcao_imagem_id`) REFERENCES `funcao_imagem` (`idfuncao_imagem`) ON DELETE CASCADE`
- `CONSTRAINT `fk_unidade_item_unidade` FOREIGN KEY (`unidade_trabalho_id`) REFERENCES `unidade_trabalho` (`id`) ON DELETE CASCADE`

## unidade_trabalho_evento

Fonte: backup, linha 13237. Engine: InnoDB.

| Campo                 | Definição existente                               |
| --------------------- | ------------------------------------------------- |
| `id`                  | `bigint unsigned NOT NULL AUTO_INCREMENT`         |
| `unidade_trabalho_id` | `bigint unsigned NOT NULL`                        |
| `evento`              | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL` |
| `ator_colaborador_id` | `int DEFAULT NULL`                                |
| `ator_usuario_id`     | `int DEFAULT NULL`                                |
| `detalhes`            | `json DEFAULT NULL`                               |
| `criado_em`           | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`     |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `idx_unidade_evento_data` (`unidade_trabalho_id`,`criado_em`)`
- `KEY `fk_unidade_evento_colaborador` (`ator_colaborador_id`)`
- `CONSTRAINT `fk_unidade_evento_colaborador` FOREIGN KEY (`ator_colaborador_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE SET NULL`
- `CONSTRAINT `fk_unidade_evento_unidade` FOREIGN KEY (`unidade_trabalho_id`) REFERENCES `unidade_trabalho` (`id`) ON DELETE CASCADE`

## funcao_imagem_prazo_historico

Fonte: backup, linha 6483. Engine: InnoDB.

| Campo                         | Definição existente                           |
| ----------------------------- | --------------------------------------------- |
| `id`                          | `int NOT NULL AUTO_INCREMENT`                 |
| `funcao_imagem_id`            | `int NOT NULL`                                |
| `prazo_anterior`              | `date DEFAULT NULL`                           |
| `prazo_novo`                  | `date DEFAULT NULL`                           |
| `alterado_em`                 | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `alterado_por_colaborador_id` | `int DEFAULT NULL`                            |
| `alterado_por_usuario_id`     | `int DEFAULT NULL`                            |
| `origem`                      | `varchar(50) NOT NULL DEFAULT 'manual'`       |
| `motivo`                      | `varchar(255) DEFAULT NULL`                   |
| `status_anterior`             | `varchar(50) DEFAULT NULL`                    |
| `status_novo`                 | `varchar(50) DEFAULT NULL`                    |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `idx_fiph_funcao` (`funcao_imagem_id`)`
- `KEY `idx_fiph_alterado_em` (`alterado_em`)`
- `KEY `idx_fiph_colaborador` (`alterado_por_colaborador_id`)`
- `KEY `idx_fiph_origem` (`origem`)`

## funcao_imagem_previsao_conclusao

Fonte: backup, linha 6520. Engine: InnoDB.

| Campo                       | Definição existente                                                       |
| --------------------------- | ------------------------------------------------------------------------- |
| `funcao_imagem_id`          | `int NOT NULL`                                                            |
| `previsao_conclusao`        | `date NOT NULL`                                                           |
| `justificativa`             | `varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                    |
| `criado_por_colaborador_id` | `int DEFAULT NULL`                                                        |
| `criado_por_usuario_id`     | `int DEFAULT NULL`                                                        |
| `criado_em`                 | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                             |
| `atualizado_em`             | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`funcao_imagem_id`)`
- `KEY `fk_fipc_colaborador` (`criado_por_colaborador_id`)`
- `CONSTRAINT `fk_fipc_colaborador` FOREIGN KEY (`criado_por_colaborador_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE SET NULL`
- `CONSTRAINT `fk_fipc_funcao_imagem` FOREIGN KEY (`funcao_imagem_id`) REFERENCES `funcao_imagem` (`idfuncao_imagem`) ON DELETE CASCADE`

## funcao_imagem_previsao_historico

Fonte: backup, linha 6552. Engine: InnoDB.

| Campo                    | Definição existente                                                                                         |
| ------------------------ | ----------------------------------------------------------------------------------------------------------- |
| `id`                     | `bigint unsigned NOT NULL AUTO_INCREMENT`                                                                   |
| `funcao_imagem_id`       | `int NOT NULL`                                                                                              |
| `evento`                 | `enum('PREVISAO_INFORMADA','PREVISAO_ALTERADA','CONCLUSAO_REGISTRADA') COLLATE utf8mb4_unicode_ci NOT NULL` |
| `prazo_necessario`       | `date DEFAULT NULL`                                                                                         |
| `previsao_anterior`      | `date DEFAULT NULL`                                                                                         |
| `previsao_conclusao`     | `date DEFAULT NULL`                                                                                         |
| `diferenca_dias_uteis`   | `int DEFAULT NULL`                                                                                          |
| `justificativa`          | `varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                                      |
| `versao_planejamento_id` | `bigint unsigned DEFAULT NULL`                                                                              |
| `ator_colaborador_id`    | `int DEFAULT NULL`                                                                                          |
| `ator_usuario_id`        | `int DEFAULT NULL`                                                                                          |
| `criado_em`              | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                                                               |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `idx_fiph_tarefa_data` (`funcao_imagem_id`,`criado_em`)`
- `KEY `fk_fiph_versao` (`versao_planejamento_id`)`
- `KEY `fk_fiph_colaborador` (`ator_colaborador_id`)`
- `CONSTRAINT `fk_fiph_colaborador` FOREIGN KEY (`ator_colaborador_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE SET NULL`
- `CONSTRAINT `fk_fiph_funcao_imagem` FOREIGN KEY (`funcao_imagem_id`) REFERENCES `funcao_imagem` (`idfuncao_imagem`) ON DELETE CASCADE`
- `CONSTRAINT `fk_fiph_versao` FOREIGN KEY (`versao_planejamento_id`) REFERENCES `entrega_planejamento_versao` (`id`) ON DELETE SET NULL`

## funcao_imagem_registro_mensal

Fonte: backup, linha 6592. Engine: InnoDB.

| Campo               | Definição existente                           |
| ------------------- | --------------------------------------------- |
| `id`                | `int NOT NULL AUTO_INCREMENT`                 |
| `funcao_imagem_id`  | `int NOT NULL`                                |
| `colaborador_id`    | `int NOT NULL`                                |
| `imagem_id`         | `int NOT NULL`                                |
| `funcao_id`         | `int NOT NULL`                                |
| `status_registrado` | `varchar(50) NOT NULL`                        |
| `data_evento`       | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `ano`               | `smallint NOT NULL`                           |
| `mes`               | `tinyint NOT NULL`                            |
| `criado_em`         | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `observacao`        | `varchar(100) NOT NULL DEFAULT ''`            |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uq_funcao_imagem_ano_mes` (`funcao_imagem_id`,`ano`,`mes`)`
- `KEY `idx_registro_mensal_colab_ano_mes` (`colaborador_id`,`ano`,`mes`)`
- `KEY `idx_registro_mensal_imagem` (`imagem_id`)`
- `KEY `idx_registro_mensal_funcao` (`funcao_id`)`
- `KEY `idx_registro_mensal_data_evento` (`data_evento`)`

## gantt_prazos

Fonte: backup, linha 6658. Engine: InnoDB.

| Campo         | Definição existente                                                      |
| ------------- | ------------------------------------------------------------------------ |
| `id`          | `int NOT NULL AUTO_INCREMENT`                                            |
| `obra_id`     | `int NOT NULL`                                                           |
| `imagem_id`   | `int DEFAULT NULL`                                                       |
| `tipo_imagem` | `varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL` |
| `etapa`       | `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL` |
| `dias`        | `int NOT NULL`                                                           |
| `data_inicio` | `date NOT NULL`                                                          |
| `data_fim`    | `date NOT NULL`                                                          |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `unique_gantt_prazo` (`obra_id`,`tipo_imagem`,`imagem_id`,`etapa`)`
- `KEY `imagem_id` (`imagem_id`)`
- `CONSTRAINT `gantt_prazos_ibfk_1` FOREIGN KEY (`obra_id`) REFERENCES `obra` (`idobra`)`
- `CONSTRAINT `gantt_prazos_ibfk_2` FOREIGN KEY (`imagem_id`) REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`)`

## etapa_colaborador

Fonte: backup, linha 3562. Engine: InnoDB.

| Campo            | Definição existente           |
| ---------------- | ----------------------------- |
| `id`             | `int NOT NULL AUTO_INCREMENT` |
| `gantt_id`       | `int NOT NULL`                |
| `colaborador_id` | `int NOT NULL`                |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `unique_gantt` (`gantt_id`)`
- `KEY `colaborador_id` (`colaborador_id`)`
- `CONSTRAINT `etapa_colaborador_ibfk_1` FOREIGN KEY (`gantt_id`) REFERENCES `gantt_prazos` (`id`)`
- `CONSTRAINT `etapa_colaborador_ibfk_2` FOREIGN KEY (`colaborador_id`) REFERENCES `colaborador` (`idcolaborador`)`

## entrega_planejamento_producao

Fonte: backup, linha 3101. Engine: InnoDB.

| Campo                | Definição existente                                                                                                                 |
| -------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| `id`                 | `bigint unsigned NOT NULL AUTO_INCREMENT`                                                                                           |
| `entrega_id`         | `int NOT NULL`                                                                                                                      |
| `estado`             | `enum('RASCUNHO','CONFIRMADO','DESATUALIZADO','REPLANEJAMENTO','CONCLUIDO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RASCUNHO'` |
| `versao_atual_id`    | `bigint unsigned DEFAULT NULL`                                                                                                      |
| `baseline_versao_id` | `bigint unsigned DEFAULT NULL`                                                                                                      |
| `lock_version`       | `int unsigned NOT NULL DEFAULT '1'`                                                                                                 |
| `ultimo_fingerprint` | `char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                                                                  |
| `criado_em`          | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                                                                                       |
| `atualizado_em`      | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`                                                           |
| `modelo`             | `enum('FUNCOES_R00','IMAGENS_ALTERACAO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FUNCOES_R00'`                                 |
| `rascunho_json`      | `json DEFAULT NULL`                                                                                                                 |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uq_entrega_planejamento_entrega` (`entrega_id`)`
- `KEY `idx_entrega_planejamento_estado` (`estado`)`
- `CONSTRAINT `fk_entrega_planejamento_entrega` FOREIGN KEY (`entrega_id`) REFERENCES `entregas` (`id`) ON DELETE CASCADE`

## entrega_planejamento_versao

Fonte: backup, linha 3210. Engine: InnoDB.

| Campo                           | Definição existente                                                     |
| ------------------------------- | ----------------------------------------------------------------------- |
| `id`                            | `bigint unsigned NOT NULL AUTO_INCREMENT`                               |
| `planejamento_id`               | `bigint unsigned NOT NULL`                                              |
| `numero`                        | `int unsigned NOT NULL`                                                 |
| `tipo`                          | `enum('BASELINE','REPLANEJAMENTO') COLLATE utf8mb4_unicode_ci NOT NULL` |
| `vigente`                       | `tinyint(1) NOT NULL DEFAULT '0'`                                       |
| `vigente_token`                 | `char(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                       |
| `fingerprint`                   | `char(64) COLLATE utf8mb4_unicode_ci NOT NULL`                          |
| `data_inicio`                   | `date NOT NULL`                                                         |
| `prazo_r00`                     | `date DEFAULT NULL`                                                     |
| `fim_previsto`                  | `date DEFAULT NULL`                                                     |
| `margem_dias_uteis`             | `int DEFAULT NULL`                                                      |
| `status_plano`                  | `varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL`                       |
| `motivo_codigo`                 | `varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                   |
| `motivo_observacao`             | `varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                  |
| `confirmado_por_colaborador_id` | `int DEFAULT NULL`                                                      |
| `confirmado_em`                 | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                           |
| `snapshot_json`                 | `json NOT NULL`                                                         |
| `contexto_fingerprint_json`     | `json NOT NULL`                                                         |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uq_entrega_planejamento_versao_numero` (`planejamento_id`,`numero`)`
- `UNIQUE KEY `uq_entrega_planejamento_uma_vigente` (`planejamento_id`,`vigente_token`)`
- `KEY `idx_entrega_planejamento_versao_vigente` (`planejamento_id`,`vigente`)`
- `KEY `idx_entrega_planejamento_versao_fingerprint` (`fingerprint`)`
- `KEY `fk_entrega_planejamento_versao_confirmado_por` (`confirmado_por_colaborador_id`)`
- `CONSTRAINT `fk_entrega_planejamento_versao_confirmado_por` FOREIGN KEY (`confirmado_por_colaborador_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE SET NULL`
- `CONSTRAINT `fk_entrega_planejamento_versao_planejamento` FOREIGN KEY (`planejamento_id`) REFERENCES `entrega_planejamento_producao` (`id`) ON DELETE CASCADE`

## entrega_planejamento_funcao

Fonte: backup, linha 3016. Engine: InnoDB.

| Campo                 | Definição existente                                   |
| --------------------- | ----------------------------------------------------- |
| `id`                  | `bigint unsigned NOT NULL AUTO_INCREMENT`             |
| `versao_id`           | `bigint unsigned NOT NULL`                            |
| `codigo_etapa`        | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL`     |
| `ordem_apresentacao`  | `smallint unsigned NOT NULL`                          |
| `nome_etapa`          | `varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL`    |
| `volume`              | `int unsigned NOT NULL DEFAULT '0'`                   |
| `pessoas_alocadas`    | `smallint unsigned NOT NULL DEFAULT '1'`              |
| `capacidade_editavel` | `tinyint(1) NOT NULL DEFAULT '0'`                     |
| `estrategia_duracao`  | `varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `produtividade_json`  | `json DEFAULT NULL`                                   |
| `formula_calculo`     | `text COLLATE utf8mb4_unicode_ci`                     |
| `duracao_dias_uteis`  | `int DEFAULT NULL`                                    |
| `data_inicio`         | `date DEFAULT NULL`                                   |
| `data_limite`         | `date DEFAULT NULL`                                   |
| `dependencias_json`   | `json DEFAULT NULL`                                   |
| `confianca`           | `varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `origem_calculo`      | `varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `caminho_critico`     | `tinyint(1) NOT NULL DEFAULT '0'`                     |
| `metadados_json`      | `json DEFAULT NULL`                                   |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uq_entrega_planejamento_funcao_versao_etapa` (`versao_id`,`codigo_etapa`)`
- `KEY `idx_entrega_planejamento_funcao_etapa` (`codigo_etapa`)`
- `CONSTRAINT `fk_entrega_planejamento_funcao_versao` FOREIGN KEY (`versao_id`) REFERENCES `entrega_planejamento_versao` (`id`) ON DELETE CASCADE`

## entrega_planejamento_item

Fonte: backup, linha 3060. Engine: InnoDB.

| Campo                         | Definição existente                       |
| ----------------------------- | ----------------------------------------- |
| `id`                          | `bigint unsigned NOT NULL AUTO_INCREMENT` |
| `versao_id`                   | `bigint unsigned NOT NULL`                |
| `entrega_item_id`             | `int NOT NULL`                            |
| `funcao_imagem_id`            | `int NOT NULL`                            |
| `pre_alt_item_id`             | `int unsigned DEFAULT NULL`               |
| `responsavel_planejado_id`    | `int NOT NULL`                            |
| `inicio_planejado`            | `date DEFAULT NULL`                       |
| `fim_planejado`               | `date NOT NULL`                           |
| `nivel_complexidade_snapshot` | `tinyint unsigned DEFAULT NULL`           |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uq_planejamento_item_versao` (`versao_id`,`entrega_item_id`)`
- `KEY `idx_planejamento_item_tarefa` (`funcao_imagem_id`)`
- `KEY `fk_planejamento_item_entrega` (`entrega_item_id`)`
- `KEY `fk_planejamento_item_triagem` (`pre_alt_item_id`)`
- `KEY `fk_planejamento_item_responsavel` (`responsavel_planejado_id`)`
- `CONSTRAINT `fk_planejamento_item_entrega` FOREIGN KEY (`entrega_item_id`) REFERENCES `entregas_itens` (`id`)`
- `CONSTRAINT `fk_planejamento_item_responsavel` FOREIGN KEY (`responsavel_planejado_id`) REFERENCES `colaborador` (`idcolaborador`)`
- `CONSTRAINT `fk_planejamento_item_tarefa` FOREIGN KEY (`funcao_imagem_id`) REFERENCES `funcao_imagem` (`idfuncao_imagem`)`
- `CONSTRAINT `fk_planejamento_item_triagem` FOREIGN KEY (`pre_alt_item_id`) REFERENCES `pre_alt_itens` (`id`)`
- `CONSTRAINT `fk_planejamento_item_versao` FOREIGN KEY (`versao_id`) REFERENCES `entrega_planejamento_versao` (`id`)`

## entrega_planejamento_evento

Fonte: backup, linha 2934. Engine: InnoDB.

| Campo                 | Definição existente                                    |
| --------------------- | ------------------------------------------------------ |
| `id`                  | `bigint unsigned NOT NULL AUTO_INCREMENT`              |
| `planejamento_id`     | `bigint unsigned NOT NULL`                             |
| `versao_id`           | `bigint unsigned DEFAULT NULL`                         |
| `entrega_id`          | `int NOT NULL`                                         |
| `tipo`                | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL`      |
| `motivo_codigo`       | `varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL`  |
| `motivo_observacao`   | `varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `ator_colaborador_id` | `int DEFAULT NULL`                                     |
| `metadados_json`      | `json DEFAULT NULL`                                    |
| `criado_em`           | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`          |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `idx_entrega_planejamento_evento_entrega_data` (`entrega_id`,`criado_em`)`
- `KEY `idx_entrega_planejamento_evento_planejamento_data` (`planejamento_id`,`criado_em`)`
- `KEY `fk_entrega_planejamento_evento_versao` (`versao_id`)`
- `KEY `fk_entrega_planejamento_evento_ator` (`ator_colaborador_id`)`
- `CONSTRAINT `fk_entrega_planejamento_evento_ator` FOREIGN KEY (`ator_colaborador_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE SET NULL`
- `CONSTRAINT `fk_entrega_planejamento_evento_entrega` FOREIGN KEY (`entrega_id`) REFERENCES `entregas` (`id`) ON DELETE CASCADE`
- `CONSTRAINT `fk_entrega_planejamento_evento_planejamento` FOREIGN KEY (`planejamento_id`) REFERENCES `entrega_planejamento_producao` (`id`) ON DELETE CASCADE`
- `CONSTRAINT `fk_entrega_planejamento_evento_versao` FOREIGN KEY (`versao_id`) REFERENCES `entrega_planejamento_versao` (`id`) ON DELETE SET NULL`

## entrega_planejamento_fila_operacional

Fonte: backup, linha 2974. Engine: InnoDB.

| Campo                           | Definição existente                                                       |
| ------------------------------- | ------------------------------------------------------------------------- |
| `id`                            | `bigint unsigned NOT NULL AUTO_INCREMENT`                                 |
| `planejamento_id`               | `bigint unsigned NOT NULL`                                                |
| `versao_id`                     | `bigint unsigned NOT NULL`                                                |
| `entrega_id`                    | `int NOT NULL`                                                            |
| `obra_id`                       | `int NOT NULL`                                                            |
| `codigo_etapa`                  | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL`                         |
| `referencia_fila`               | `varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''`             |
| `colaborador_id`                | `int NOT NULL`                                                            |
| `posicao`                       | `smallint unsigned NOT NULL`                                              |
| `ativo`                         | `tinyint(1) NOT NULL DEFAULT '1'`                                         |
| `fingerprint`                   | `char(64) COLLATE utf8mb4_unicode_ci NOT NULL`                            |
| `motivo`                        | `varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                    |
| `confirmado_por_colaborador_id` | `int DEFAULT NULL`                                                        |
| `confirmado_em`                 | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                             |
| `criado_em`                     | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                             |
| `atualizado_em`                 | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `idx_fila_ativa_colaborador` (`ativo`,`colaborador_id`,`codigo_etapa`,`posicao`)`
- `KEY `idx_fila_entrega` (`entrega_id`,`ativo`)`
- `KEY `idx_fila_planejamento_versao` (`planejamento_id`,`versao_id`)`
- `KEY `idx_fila_referencia` (`colaborador_id`,`codigo_etapa`,`referencia_fila`,`ativo`)`

## entrega_planejamento_projecao_operacional

Fonte: backup, linha 3172. Engine: InnoDB.

| Campo                           | Definição existente                                                                     |
| ------------------------------- | --------------------------------------------------------------------------------------- |
| `id`                            | `bigint unsigned NOT NULL AUTO_INCREMENT`                                               |
| `planejamento_id`               | `bigint unsigned NOT NULL`                                                              |
| `versao_id`                     | `bigint unsigned NOT NULL`                                                              |
| `entrega_id`                    | `int NOT NULL`                                                                          |
| `obra_id`                       | `int NOT NULL`                                                                          |
| `tipo`                          | `enum('FILA_CONFIRMADA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FILA_CONFIRMADA'` |
| `status_operacional`            | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL`                                       |
| `data_referencia`               | `date NOT NULL`                                                                         |
| `fim_operacional_projetado`     | `date DEFAULT NULL`                                                                     |
| `margem_operacional_dias_uteis` | `int DEFAULT NULL`                                                                      |
| `fingerprint`                   | `char(64) COLLATE utf8mb4_unicode_ci NOT NULL`                                          |
| `confirmado_por_colaborador_id` | `int DEFAULT NULL`                                                                      |
| `confirmado_em`                 | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                                           |
| `snapshot_json`                 | `json NOT NULL`                                                                         |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `idx_projecao_entrega` (`entrega_id`,`confirmado_em`)`
- `KEY `idx_projecao_fingerprint` (`fingerprint`)`

## entrega_planejamento_projecao_etapa

Fonte: backup, linha 3137. Engine: InnoDB.

| Campo                             | Definição existente                               |
| --------------------------------- | ------------------------------------------------- |
| `id`                              | `bigint unsigned NOT NULL AUTO_INCREMENT`         |
| `projecao_id`                     | `bigint unsigned NOT NULL`                        |
| `codigo_etapa`                    | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL` |
| `inicio_operacional_projetado`    | `date DEFAULT NULL`                               |
| `fim_operacional_projetado`       | `date DEFAULT NULL`                               |
| `desvio_baseline_dias_uteis`      | `int DEFAULT NULL`                                |
| `desvio_plano_vigente_dias_uteis` | `int DEFAULT NULL`                                |
| `margem_operacional_dias_uteis`   | `int DEFAULT NULL`                                |
| `status_operacional`              | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL` |
| `confianca`                       | `varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL` |
| `explicacao_json`                 | `json DEFAULT NULL`                               |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `idx_projecao_etapa` (`projecao_id`,`codigo_etapa`)`
- `CONSTRAINT `fk_projecao_etapa_cabecalho` FOREIGN KEY (`projecao_id`) REFERENCES `entrega_planejamento_projecao_operacional` (`id`) ON DELETE CASCADE`

## flow_issue

Fonte: backup, linha 4297. Engine: InnoDB.

| Campo                           | Definição existente                                                                            |
| ------------------------------- | ---------------------------------------------------------------------------------------------- |
| `id`                            | `int NOT NULL AUTO_INCREMENT`                                                                  |
| `codigo`                        | `varchar(24) DEFAULT NULL`                                                                     |
| `funcao_imagem_id`              | `int DEFAULT NULL`                                                                             |
| `tipo_id`                       | `int NOT NULL`                                                                                 |
| `fila_id`                       | `int DEFAULT NULL`                                                                             |
| `responsavel_colaborador_id`    | `int DEFAULT NULL`                                                                             |
| `descricao`                     | `text NOT NULL`                                                                                |
| `urgencia`                      | `enum('BAIXA','NORMAL','ALTA','CRITICA') NOT NULL DEFAULT 'NORMAL'`                            |
| `status`                        | `enum('ABERTA','AGUARDANDO_ACAO','PAUSADA','RESOLVIDA','CANCELADA') NOT NULL DEFAULT 'ABERTA'` |
| `bloqueante`                    | `tinyint(1) NOT NULL DEFAULT '1'`                                                              |
| `criado_por_colaborador_id`     | `int NOT NULL`                                                                                 |
| `criado_em`                     | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                                                  |
| `atualizado_em`                 | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`                      |
| `resolvido_por_colaborador_id`  | `int DEFAULT NULL`                                                                             |
| `resolvido_em`                  | `datetime DEFAULT NULL`                                                                        |
| `encerramento_observacao`       | `text`                                                                                         |
| `confirmada_por_colaborador_id` | `int DEFAULT NULL`                                                                             |
| `confirmada_em`                 | `datetime DEFAULT NULL`                                                                        |
| `confirmacao_observacao`        | `text`                                                                                         |
| `sla_atendimento_em`            | `datetime DEFAULT NULL`                                                                        |
| `primeira_tratativa_em`         | `datetime DEFAULT NULL`                                                                        |
| `pausada_em`                    | `datetime DEFAULT NULL`                                                                        |
| `pausada_por_colaborador_id`    | `int DEFAULT NULL`                                                                             |
| `pausa_motivo`                  | `text`                                                                                         |
| `pausa_observacao`              | `text`                                                                                         |
| `retorno_previsto_em`           | `datetime DEFAULT NULL`                                                                        |
| `proxima_cobranca_em`           | `datetime DEFAULT NULL`                                                                        |
| `legado`                        | `tinyint(1) NOT NULL DEFAULT '0'`                                                              |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `codigo` (`codigo`)`
- `KEY `idx_flow_issue_task_status` (`funcao_imagem_id`,`status`)`
- `KEY `idx_flow_issue_updated` (`atualizado_em`)`
- `KEY `idx_flow_issue_responsavel` (`responsavel_colaborador_id`)`
- `KEY `idx_flow_issue_tipo` (`tipo_id`)`
- `KEY `idx_flow_issue_fila` (`fila_id`)`
- `KEY `idx_flow_issue_cobranca` (`proxima_cobranca_em`,`status`)`
- `KEY `idx_flow_issue_confirmacao` (`status`,`confirmada_em`)`

## flow_issue_atividade

Fonte: backup, linha 4387. Engine: InnoDB.

| Campo                         | Definição existente                           |
| ----------------------------- | --------------------------------------------- |
| `id`                          | `bigint NOT NULL AUTO_INCREMENT`              |
| `issue_id`                    | `int NOT NULL`                                |
| `tipo`                        | `varchar(40) NOT NULL`                        |
| `conteudo`                    | `text`                                        |
| `metadados`                   | `json DEFAULT NULL`                           |
| `criado_por_colaborador_id`   | `int DEFAULT NULL`                            |
| `atividade_pai_id`            | `bigint DEFAULT NULL`                         |
| `criado_em`                   | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `atualizado_em`               | `datetime DEFAULT NULL`                       |
| `excluido_em`                 | `datetime DEFAULT NULL`                       |
| `excluido_por_colaborador_id` | `int DEFAULT NULL`                            |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `idx_flow_issue_atividade_issue` (`issue_id`,`criado_em`)`
- `KEY `idx_flow_issue_atividade_pai` (`atividade_pai_id`,`criado_em`)`

## flow_issue_ciclo

Fonte: backup, linha 4422. Engine: InnoDB.

| Campo            | Definição existente                           |
| ---------------- | --------------------------------------------- |
| `id`             | `int NOT NULL AUTO_INCREMENT`                 |
| `issue_id`       | `int NOT NULL`                                |
| `iniciado_em`    | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `finalizado_em`  | `datetime DEFAULT NULL`                       |
| `status_inicial` | `varchar(50) DEFAULT NULL`                    |
| `status_final`   | `varchar(50) DEFAULT NULL`                    |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `idx_flow_issue_ciclo_issue` (`issue_id`,`iniciado_em`)`

## flow_issue_operacional

Fonte: backup, linha 4548. Engine: InnoDB.

| Campo                   | Definição existente                                    |
| ----------------------- | ------------------------------------------------------ |
| `issue_id`              | `int NOT NULL`                                         |
| `source_type`           | `varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL`      |
| `source_id`             | `bigint unsigned NOT NULL`                             |
| `source_title`          | `varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL`     |
| `source_url`            | `varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `obra_id`               | `int DEFAULT NULL`                                     |
| `obra_nome`             | `varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `source_responsavel_id` | `int DEFAULT NULL`                                     |
| `source_status_before`  | `varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL`  |
| `source_native_hold_id` | `bigint unsigned DEFAULT NULL`                         |
| `criado_por`            | `int DEFAULT NULL`                                     |
| `criado_em`             | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`          |
| `liberado_por`          | `int DEFAULT NULL`                                     |
| `liberado_em`           | `datetime DEFAULT NULL`                                |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`issue_id`)`
- `KEY `idx_flow_issue_operacional_source` (`source_type`,`source_id`,`liberado_em`)`
- `KEY `idx_flow_issue_operacional_obra` (`obra_id`)`
- `KEY `idx_flow_issue_operacional_responsavel` (`source_responsavel_id`)`

## flow_issue_dependencia

Fonte: backup, linha 4451. Engine: InnoDB.

| Campo                           | Definição existente                                |
| ------------------------------- | -------------------------------------------------- |
| `issue_id`                      | `int NOT NULL`                                     |
| `requirement_code`              | `varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL` |
| `tarefa_bloqueada_id`           | `int NOT NULL`                                     |
| `predecessora_funcao_imagem_id` | `int NOT NULL`                                     |
| `approval_cycle_key`            | `varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL` |
| `aprovacao_status`              | `varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL`  |
| `aprovacao_historico_id`        | `int DEFAULT NULL`                                 |
| `aprovadores_json`              | `json DEFAULT NULL`                                |
| `criado_em`                     | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`      |
| `encerrada_em`                  | `datetime DEFAULT NULL`                            |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`issue_id`)`
- `UNIQUE KEY `uq_flow_issue_dependencia_ciclo` (`tarefa_bloqueada_id`,`requirement_code`,`predecessora_funcao_imagem_id`,`approval_cycle_key`)`
- `KEY `idx_flow_issue_dependencia_predecessora` (`predecessora_funcao_imagem_id`,`encerrada_em`)`
- `KEY `idx_flow_issue_dependencia_bloqueada` (`tarefa_bloqueada_id`,`encerrada_em`)`

## flow_connect_events

Fonte: backup, linha 3961. Engine: InnoDB.

| Campo                   | Definição existente                                                 |
| ----------------------- | ------------------------------------------------------------------- |
| `id`                    | `bigint unsigned NOT NULL AUTO_INCREMENT`                           |
| `event_uuid`            | `char(36) COLLATE utf8mb4_unicode_ci NOT NULL`                      |
| `event_type`            | `varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL`                  |
| `event_version`         | `int unsigned NOT NULL`                                             |
| `source_module`         | `varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL`                   |
| `entity_type`           | `varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL`                   |
| `entity_id`             | `varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL`                  |
| `actor_id`              | `bigint DEFAULT NULL`                                               |
| `occurred_at`           | `datetime(6) NOT NULL`                                              |
| `received_at`           | `datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)`                 |
| `correlation_id`        | `char(36) COLLATE utf8mb4_unicode_ci NOT NULL`                      |
| `causation_event_uuid`  | `char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                  |
| `idempotency_key`       | `varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL`                  |
| `payload_json`          | `json NOT NULL`                                                     |
| `metadata_json`         | `json DEFAULT NULL`                                                 |
| `status`                | `varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING'` |
| `failure_count`         | `int unsigned NOT NULL DEFAULT '0'`                                 |
| `claimed_by`            | `varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL`              |
| `claimed_at`            | `datetime(6) DEFAULT NULL`                                          |
| `claim_expires_at`      | `datetime(6) DEFAULT NULL`                                          |
| `processing_started_at` | `datetime(6) DEFAULT NULL`                                          |
| `processed_at`          | `datetime(6) DEFAULT NULL`                                          |
| `last_error_code`       | `varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL`              |
| `last_error_safe`       | `varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL`              |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uq_flow_connect_event_uuid` (`event_uuid`)`
- `UNIQUE KEY `uq_flow_connect_event_idempotency` (`idempotency_key`)`
- `KEY `ix_flow_connect_event_queue` (`status`,`claim_expires_at`,`id`)`
- `KEY `ix_flow_connect_event_entity` (`source_module`,`entity_type`,`entity_id`)`
- `KEY `ix_flow_connect_event_correlation` (`correlation_id`)`
- `KEY `ix_flow_connect_event_causation` (`causation_event_uuid`)`

## imagem_decisoes

Fonte: backup, linha 7022. Engine: InnoDB.

| Campo                 | Definição existente                                                                                          |
| --------------------- | ------------------------------------------------------------------------------------------------------------ |
| `id`                  | `int NOT NULL AUTO_INCREMENT`                                                                                |
| `entrega_item_id`     | `int NOT NULL`                                                                                               |
| `historico_imagem_id` | `int NOT NULL`                                                                                               |
| `decisao`             | `enum('aprovado','aprovado_com_ajustes','ajuste') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL` |
| `usuario_id`          | `int NOT NULL`                                                                                               |
| `created_at`          | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                                                                |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uniq_item_versao` (`entrega_item_id`,`historico_imagem_id`)`
- `KEY `idx_entrega_item_id` (`entrega_item_id`)`
- `KEY `idx_historico_imagem_id` (`historico_imagem_id`)`
- `KEY `idx_usuario_id` (`usuario_id`)`
- `CONSTRAINT `fk_imagem_decisoes_entrega_item` FOREIGN KEY (`entrega_item_id`) REFERENCES `entregas_itens` (`id`) ON DELETE CASCADE`
- `CONSTRAINT `fk_imagem_decisoes_historico` FOREIGN KEY (`historico_imagem_id`) REFERENCES `historico_aprovacoes_imagens` (`id`) ON DELETE CASCADE`
- `CONSTRAINT `fk_imagem_decisoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuario_externo` (`idusuario`)`

## imagem_decisoes_angulos

Fonte: backup, linha 7056. Engine: InnoDB.

| Campo             | Definição existente           |
| ----------------- | ----------------------------- |
| `id`              | `int NOT NULL AUTO_INCREMENT` |
| `entrega_item_id` | `int NOT NULL`                |
| `angulo_id`       | `int NOT NULL`                |
| `decisao`         | `varchar(64) NOT NULL`        |
| `observacao`      | `text`                        |
| `usuario_id`      | `int NOT NULL`                |
| `created_at`      | `datetime NOT NULL`           |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `ux_entrega_angulo` (`entrega_item_id`,`angulo_id`)`

## review_batch

Fonte: backup, linha 12048. Engine: InnoDB.

| Campo               | Definição existente                                                                                                   |
| ------------------- | --------------------------------------------------------------------------------------------------------------------- |
| `id`                | `int NOT NULL AUTO_INCREMENT`                                                                                         |
| `entrega_id`        | `int NOT NULL`                                                                                                        |
| `data_entrega_lote` | `date NOT NULL`                                                                                                       |
| `review_round`      | `smallint unsigned NOT NULL DEFAULT '1'`                                                                              |
| `status`            | `enum('OPEN','OVERDUE','NOTIFIED','SNOOZED','RESOLVED','IGNORED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN'` |
| `batch_active_slot` | `tinyint unsigned DEFAULT '1'`                                                                                        |
| `created_at`        | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                                                                         |
| `updated_at`        | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`                                             |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `ux_review_batch_active` (`entrega_id`,`data_entrega_lote`,`review_round`,`batch_active_slot`)`
- `KEY `idx_review_batch_entrega_status` (`entrega_id`,`status`,`data_entrega_lote`)`
- `KEY `idx_review_batch_status_date` (`status`,`data_entrega_lote`)`
- `CONSTRAINT `fk_review_batch_entrega` FOREIGN KEY (`entrega_id`) REFERENCES `entregas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE`

## review_batch_items

Fonte: backup, linha 12139. Engine: InnoDB.

| Campo              | Definição existente                           |
| ------------------ | --------------------------------------------- |
| `id`               | `int NOT NULL AUTO_INCREMENT`                 |
| `review_batch_id`  | `int NOT NULL`                                |
| `entrega_item_id`  | `int DEFAULT NULL`                            |
| `p00_versao_id`    | `int DEFAULT NULL`                            |
| `imagem_id`        | `int NOT NULL`                                |
| `entered_rvw_at`   | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `left_rvw_at`      | `datetime DEFAULT NULL`                       |
| `created_at`       | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `item_active_slot` | `tinyint unsigned DEFAULT '1'`                |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `ux_review_batch_item_active` (`review_batch_id`,`entrega_item_id`,`imagem_id`,`item_active_slot`)`
- `UNIQUE KEY `ux_review_batch_p00_item_active` (`review_batch_id`,`p00_versao_id`,`imagem_id`,`item_active_slot`)`
- `KEY `idx_review_batch_items_batch_active` (`review_batch_id`,`left_rvw_at`)`
- `KEY `idx_review_batch_items_imagem_history` (`imagem_id`,`entered_rvw_at`)`
- `KEY `idx_review_batch_items_entrega_history` (`entrega_item_id`,`entered_rvw_at`)`
- `KEY `idx_review_batch_items_p00_history` (`p00_versao_id`,`entered_rvw_at`)`
- `CONSTRAINT `fk_review_batch_items_batch` FOREIGN KEY (`review_batch_id`) REFERENCES `review_batch` (`id`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `fk_review_batch_items_entrega_item` FOREIGN KEY (`entrega_item_id`) REFERENCES `entregas_itens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `fk_review_batch_items_imagem` FOREIGN KEY (`imagem_id`) REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `fk_review_batch_items_p00_versao` FOREIGN KEY (`p00_versao_id`) REFERENCES `entregas_p00_versoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE`

## funcao_animacao

Fonte: backup, linha 5613. Engine: InnoDB.

| Campo            | Definição existente                                                      |
| ---------------- | ------------------------------------------------------------------------ |
| `id`             | `int NOT NULL AUTO_INCREMENT`                                            |
| `animacao_id`    | `int NOT NULL`                                                           |
| `funcao_id`      | `int NOT NULL`                                                           |
| `colaborador_id` | `int DEFAULT NULL`                                                       |
| `status`         | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Não iniciado'` |
| `prazo`          | `date DEFAULT NULL`                                                      |
| `valor`          | `double NOT NULL DEFAULT '0'`                                            |
| `pagamento`      | `tinyint(1) NOT NULL DEFAULT '0'`                                        |
| `data_pagamento` | `date DEFAULT NULL`                                                      |
| `observacao`     | `varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                   |
| `created_at`     | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                            |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `unique_animacao_funcao` (`animacao_id`,`funcao_id`)`
- `KEY `fk_fa_funcao` (`funcao_id`)`
- `KEY `fk_fa_colab` (`colaborador_id`)`
- `CONSTRAINT `fk_fa_animacao` FOREIGN KEY (`animacao_id`) REFERENCES `animacao` (`idanimacao`) ON DELETE CASCADE`
- `CONSTRAINT `fk_fa_colab` FOREIGN KEY (`colaborador_id`) REFERENCES `colaborador` (`idcolaborador`)`
- `CONSTRAINT `fk_fa_funcao` FOREIGN KEY (`funcao_id`) REFERENCES `funcao` (`idfuncao`)`

## log_funcao_animacao

Fonte: backup, linha 7886. Engine: InnoDB.

| Campo                | Definição existente                                                          |
| -------------------- | ---------------------------------------------------------------------------- |
| `idlog`              | `int NOT NULL AUTO_INCREMENT`                                                |
| `funcao_animacao_id` | `int NOT NULL`                                                               |
| `status_anterior`    | `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `status_novo`        | `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `data`               | `timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP`                               |
| `colaborador_id`     | `int DEFAULT NULL`                                                           |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`idlog`)`
- `KEY `log_colaborador` (`colaborador_id`)`
- `KEY `fk_funcao_nova` (`funcao_animacao_id`)`
- `CONSTRAINT `fk_log_funcao_animacao` FOREIGN KEY (`funcao_animacao_id`) REFERENCES `funcao_animacao` (`id`) ON DELETE CASCADE`

## animacao

Fonte: backup, linha 901. Engine: InnoDB.

| Campo            | Definição existente                                |
| ---------------- | -------------------------------------------------- |
| `idanimacao`     | `int NOT NULL AUTO_INCREMENT`                      |
| `imagem_id`      | `int DEFAULT NULL`                                 |
| `substatus_id`   | `int NOT NULL DEFAULT '7'`                         |
| `tipo_animacao`  | `varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL` |
| `colaborador_id` | `int DEFAULT NULL`                                 |
| `duracao`        | `int DEFAULT NULL`                                 |
| `cliente_id`     | `int NOT NULL`                                     |
| `obra_id`        | `int NOT NULL`                                     |
| `data_anima`     | `date NOT NULL`                                    |
| `pagamento`      | `tinyint(1) NOT NULL DEFAULT '0'`                  |
| `valor`          | `int NOT NULL DEFAULT '0'`                         |
| `data_pagamento` | `date DEFAULT NULL`                                |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`idanimacao`)`
- `KEY `cliente_animacao` (`cliente_id`)`
- `KEY `obra_animacao` (`obra_id`)`
- `KEY `fk_animacao_imagem` (`imagem_id`)`
- `KEY `fk_animacao_substatus` (`substatus_id`)`
- `CONSTRAINT `cliente_animacao` FOREIGN KEY (`cliente_id`) REFERENCES `cliente` (`idcliente`)`
- `CONSTRAINT `fk_animacao_imagem` FOREIGN KEY (`imagem_id`) REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`)`
- `CONSTRAINT `fk_animacao_substatus` FOREIGN KEY (`substatus_id`) REFERENCES `substatus_imagem` (`id`)`
- `CONSTRAINT `obra_animacao` FOREIGN KEY (`obra_id`) REFERENCES `obra` (`idobra`)`

## tarefas

Fonte: backup, linha 13078. Engine: InnoDB.

| Campo            | Definição existente                                                                                                                                       |
| ---------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `id`             | `int NOT NULL AUTO_INCREMENT`                                                                                                                             |
| `colaborador_id` | `int NOT NULL`                                                                                                                                            |
| `titulo`         | `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL`                                                                                  |
| `descricao`      | `text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`                                                                                                   |
| `prioridade`     | `enum('alta','media','baixa') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'baixa'`                                                  |
| `prazo`          | `date NOT NULL`                                                                                                                                           |
| `status`         | `enum('Não iniciado','Em andamento','Em aprovação','Finalizado','Hold') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Não iniciado'` |
| `origem`         | `varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                                                                                     |
| `referencia_id`  | `int DEFAULT NULL`                                                                                                                                        |
| `data_criacao`   | `timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP`                                                                                                            |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `ux_tarefas_origem_referencia` (`origem`,`referencia_id`)`
- `KEY `colaborador_id` (`colaborador_id`)`
- `CONSTRAINT `tarefas_ibfk_1` FOREIGN KEY (`colaborador_id`) REFERENCES `colaborador` (`idcolaborador`)`

## arquivo_log

Fonte: backup, linha 1129. Engine: InnoDB.

| Campo                | Definição existente                  |
| -------------------- | ------------------------------------ |
| `id`                 | `int NOT NULL AUTO_INCREMENT`        |
| `funcao_imagem_id`   | `int DEFAULT NULL`                   |
| `tipo_tarefa`        | `varchar(20) DEFAULT NULL`           |
| `funcao_animacao_id` | `int DEFAULT NULL`                   |
| `caminho`            | `varchar(1024) NOT NULL`             |
| `caminho_vps`        | `varchar(1024) DEFAULT NULL`         |
| `caminho_nas`        | `varchar(1024) DEFAULT NULL`         |
| `preview_path`       | `varchar(1024) DEFAULT NULL`         |
| `nome_arquivo`       | `varchar(255) DEFAULT NULL`          |
| `tamanho`            | `bigint DEFAULT NULL`                |
| `tipo`               | `varchar(50) DEFAULT NULL`           |
| `colaborador_id`     | `int DEFAULT NULL`                   |
| `status`             | `varchar(50) DEFAULT 'atualizado'`   |
| `publicado_em`       | `datetime DEFAULT NULL`              |
| `criado_em`          | `datetime DEFAULT CURRENT_TIMESTAMP` |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `funcao_imagem_id` (`funcao_imagem_id`)`
- `KEY `colaborador_id` (`colaborador_id`)`
- `CONSTRAINT `arquivo_log_ibfk_1` FOREIGN KEY (`funcao_imagem_id`) REFERENCES `funcao_imagem` (`idfuncao_imagem`) ON DELETE CASCADE`
- `CONSTRAINT `arquivo_log_ibfk_2` FOREIGN KEY (`colaborador_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE SET NULL`

## arquivos

Fonte: backup, linha 1206. Engine: InnoDB.

| Campo            | Definição existente                                                                                                     |
| ---------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `idarquivo`      | `int NOT NULL AUTO_INCREMENT`                                                                                           |
| `obra_id`        | `int NOT NULL`                                                                                                          |
| `tipo_imagem_id` | `int DEFAULT NULL`                                                                                                      |
| `imagem_id`      | `int DEFAULT NULL`                                                                                                      |
| `nome_original`  | `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL`                                                |
| `nome_interno`   | `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL`                                                |
| `caminho`        | `varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL`                                                |
| `descricao`      | `text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`                                                                 |
| `tipo`           | `varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                             |
| `versao`         | `int NOT NULL DEFAULT '1'`                                                                                              |
| `status`         | `enum('pendente','atualizado','antigo','invalido') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendente'` |
| `origem`         | `varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                            |
| `recebido_por`   | `varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                            |
| `recebido_em`    | `datetime DEFAULT CURRENT_TIMESTAMP`                                                                                    |
| `revisado_em`    | `datetime DEFAULT NULL`                                                                                                 |
| `revisado_por`   | `varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                            |
| `categoria_id`   | `int DEFAULT NULL`                                                                                                      |
| `sufixo`         | `varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                            |
| `colaborador_id` | `int DEFAULT NULL`                                                                                                      |
| `tamanho`        | `varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                             |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`idarquivo`)`
- `KEY `obra_id` (`obra_id`)`
- `KEY `tipo_imagem_id` (`tipo_imagem_id`)`
- `KEY `fk_categoria` (`categoria_id`)`
- `KEY `colaborador_id` (`colaborador_id`)`
- `CONSTRAINT `arquivos_ibfk_1` FOREIGN KEY (`obra_id`) REFERENCES `obra` (`idobra`)`
- `CONSTRAINT `arquivos_ibfk_2` FOREIGN KEY (`tipo_imagem_id`) REFERENCES `tipo_imagem` (`id_tipo_imagem`)`
- `CONSTRAINT `arquivos_ibfk_3` FOREIGN KEY (`colaborador_id`) REFERENCES `colaborador` (`idcolaborador`)`
- `CONSTRAINT `fk_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`idcategoria`) ON DELETE SET NULL ON UPDATE CASCADE`

## render_alta

Fonte: backup, linha 11626. Engine: InnoDB.

| Campo             | Definição existente                                                                    |
| ----------------- | -------------------------------------------------------------------------------------- |
| `idrender_alta`   | `int NOT NULL AUTO_INCREMENT`                                                          |
| `imagem_id`       | `int DEFAULT NULL`                                                                     |
| `status`          | `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Em andamento'` |
| `data`            | `datetime DEFAULT CURRENT_TIMESTAMP`                                                   |
| `responsavel_id`  | `int DEFAULT NULL`                                                                     |
| `status_id`       | `int DEFAULT NULL`                                                                     |
| `computer`        | `varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL`           |
| `submitted`       | `datetime DEFAULT CURRENT_TIMESTAMP`                                                   |
| `last_updated`    | `datetime DEFAULT CURRENT_TIMESTAMP`                                                   |
| `has_error`       | `tinyint(1) DEFAULT NULL`                                                              |
| `errors`          | `text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`                                |
| `job_folder`      | `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL`           |
| `deadline_job_id` | `varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                  |
| `excluido_em`     | `datetime DEFAULT NULL`                                                                |
| `previa_jpg`      | `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL`           |
| `numero_bg`       | `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL`           |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`idrender_alta`)`
- `UNIQUE KEY `idx_imagem_status` (`imagem_id`,`status_id`)`
- `KEY `responsavel_id` (`responsavel_id`)`
- `KEY `status_id` (`status_id`)`
- `KEY `idx_render_alta_deadline_job_id` (`deadline_job_id`)`
- `CONSTRAINT `render_alta_ibfk_1` FOREIGN KEY (`imagem_id`) REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`)`
- `CONSTRAINT `render_alta_ibfk_2` FOREIGN KEY (`responsavel_id`) REFERENCES `colaborador` (`idcolaborador`)`
- `CONSTRAINT `render_alta_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `status_imagem` (`idstatus`)`

## log_render

Fonte: backup, linha 7917. Engine: InnoDB.

| Campo             | Definição existente                                                              |
| ----------------- | -------------------------------------------------------------------------------- |
| `id`              | `int NOT NULL AUTO_INCREMENT COMMENT 'Primary Key'`                              |
| `status_anterior` | `varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Previous Status'` |
| `status_novo`     | `varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Current Status'`  |
| `data`            | `datetime DEFAULT NULL COMMENT 'Change Date'`                                    |
| `render_id`       | `int DEFAULT NULL`                                                               |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `render_id` (`render_id`)`
- `CONSTRAINT `log_render_ibfk_1` FOREIGN KEY (`render_id`) REFERENCES `render_alta` (`idrender_alta`) ON DELETE CASCADE`

## render_tentativas

Fonte: backup, linha 11847. Engine: InnoDB.

| Campo                 | Definição existente                                                                       |
| --------------------- | ----------------------------------------------------------------------------------------- |
| `id`                  | `bigint unsigned NOT NULL AUTO_INCREMENT`                                                 |
| `render_id`           | `int NOT NULL`                                                                            |
| `imagem_id`           | `int NOT NULL`                                                                            |
| `status_id`           | `int NOT NULL`                                                                            |
| `numero_tentativa`    | `int NOT NULL`                                                                            |
| `deadline_job_id`     | `varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                     |
| `deadline_job_name`   | `varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                    |
| `status`              | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AGUARDANDO_JOB'`                |
| `ativa`               | `tinyint(1) NOT NULL DEFAULT '1'`                                                         |
| `motivo_encerramento` | `varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                    |
| `criado_em`           | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                                             |
| `vinculado_em`        | `datetime DEFAULT NULL`                                                                   |
| `iniciado_em`         | `datetime DEFAULT NULL`                                                                   |
| `concluido_em`        | `datetime DEFAULT NULL`                                                                   |
| `reprovado_em`        | `datetime DEFAULT NULL`                                                                   |
| `encerrado_em`        | `datetime DEFAULT NULL`                                                                   |
| `atualizado_em`       | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`                 |
| `ativa_render_id`     | `int GENERATED ALWAYS AS ((case when (`ativa`= 1) then`render_id` else NULL end)) STORED` |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uniq_render_numero_tentativa` (`render_id`,`numero_tentativa`)`
- `UNIQUE KEY `uniq_deadline_job_id` (`deadline_job_id`)`
- `UNIQUE KEY `uniq_render_tentativa_ativa` (`ativa_render_id`)`
- `KEY `idx_render_tentativas_render` (`render_id`)`
- `KEY `idx_render_tentativas_imagem_status` (`imagem_id`,`status_id`)`
- `KEY `idx_render_tentativas_ativa` (`ativa`,`status`)`
- `KEY `fk_render_tentativas_status` (`status_id`)`
- `CONSTRAINT `fk_render_tentativas_imagem` FOREIGN KEY (`imagem_id`) REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`) ON DELETE RESTRICT ON UPDATE CASCADE`
- `CONSTRAINT `fk_render_tentativas_render` FOREIGN KEY (`render_id`) REFERENCES `render_alta` (`idrender_alta`) ON DELETE RESTRICT ON UPDATE RESTRICT`
- `CONSTRAINT `fk_render_tentativas_status` FOREIGN KEY (`status_id`) REFERENCES `status_imagem` (`idstatus`) ON DELETE RESTRICT ON UPDATE CASCADE`

## render_tentativa_eventos

Fonte: backup, linha 11815. Engine: InnoDB.

| Campo          | Definição existente                                |
| -------------- | -------------------------------------------------- |
| `id`           | `bigint unsigned NOT NULL AUTO_INCREMENT`          |
| `tentativa_id` | `bigint unsigned NOT NULL`                         |
| `tipo`         | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL`  |
| `chave`        | `varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL` |
| `dados_json`   | `json DEFAULT NULL`                                |
| `criado_em`    | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`      |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uniq_tentativa_evento` (`tentativa_id`,`tipo`,`chave`)`
- `KEY `idx_tentativa_eventos_tentativa` (`tentativa_id`,`criado_em`)`
- `CONSTRAINT `fk_tentativa_eventos_tentativa` FOREIGN KEY (`tentativa_id`) REFERENCES `render_tentativas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE`

## render_conclusoes_manuais

Fonte: backup, linha 11709. Engine: InnoDB.

| Campo             | Definição existente                               |
| ----------------- | ------------------------------------------------- |
| `id`              | `bigint unsigned NOT NULL AUTO_INCREMENT`         |
| `render_id`       | `int NOT NULL`                                    |
| `tentativa_id`    | `bigint unsigned NOT NULL`                        |
| `colaborador_id`  | `int NOT NULL`                                    |
| `status_anterior` | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL` |
| `justificativa`   | `text COLLATE utf8mb4_unicode_ci NOT NULL`        |
| `criado_em`       | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`     |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uniq_render_conclusao_manual_tentativa` (`tentativa_id`)`
- `KEY `idx_render_conclusoes_manuais_render` (`render_id`,`criado_em`)`
- `KEY `idx_render_conclusoes_manuais_colaborador` (`colaborador_id`)`
- `CONSTRAINT `fk_render_conclusoes_manuais_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE RESTRICT ON UPDATE CASCADE`
- `CONSTRAINT `fk_render_conclusoes_manuais_render` FOREIGN KEY (`render_id`) REFERENCES `render_alta` (`idrender_alta`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `fk_render_conclusoes_manuais_tentativa` FOREIGN KEY (`tentativa_id`) REFERENCES `render_tentativas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE`

## imagem_funcao_planejada

Fonte: backup, linha 7086. Engine: InnoDB.

| Campo                       | Definição existente                                                                                   |
| --------------------------- | ----------------------------------------------------------------------------------------------------- |
| `idimagem_funcao_planejada` | `int NOT NULL AUTO_INCREMENT`                                                                         |
| `imagem_id`                 | `int NOT NULL`                                                                                        |
| `funcao_id`                 | `int NOT NULL`                                                                                        |
| `template_id`               | `int DEFAULT NULL`                                                                                    |
| `template_item_id`          | `int DEFAULT NULL`                                                                                    |
| `template_versao`           | `int DEFAULT NULL`                                                                                    |
| `funcao_imagem_id`          | `int DEFAULT NULL`                                                                                    |
| `ordem`                     | `smallint unsigned NOT NULL DEFAULT '1'`                                                              |
| `obrigatoria`               | `tinyint(1) NOT NULL DEFAULT '1'`                                                                     |
| `status`                    | `enum('TODO','INICIADO','CANCELADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TODO'`              |
| `origem`                    | `enum('PLANEJAMENTO','MANUAL','EXECUCAO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PLANEJAMENTO'` |
| `responsavel_sugerido_id`   | `int DEFAULT NULL`                                                                                    |
| `created_at`                | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                                                         |
| `updated_at`                | `datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`                                                   |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`idimagem_funcao_planejada`)`
- `UNIQUE KEY `uq_ifp_imagem_funcao` (`imagem_id`,`funcao_id`)`
- `KEY `idx_ifp_status` (`status`)`
- `KEY `idx_ifp_funcao` (`funcao_id`)`
- `KEY `idx_ifp_imagem` (`imagem_id`)`
- `KEY `idx_ifp_template` (`template_id`)`
- `KEY `idx_ifp_funcao_imagem` (`funcao_imagem_id`)`
- `KEY `fk_ifp_template_item` (`template_item_id`)`
- `KEY `fk_ifp_responsavel` (`responsavel_sugerido_id`)`
- `CONSTRAINT `fk_ifp_funcao` FOREIGN KEY (`funcao_id`) REFERENCES `funcao` (`idfuncao`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `fk_ifp_funcao_imagem` FOREIGN KEY (`funcao_imagem_id`) REFERENCES `funcao_imagem` (`idfuncao_imagem`) ON DELETE SET NULL ON UPDATE CASCADE`
- `CONSTRAINT `fk_ifp_imagem` FOREIGN KEY (`imagem_id`) REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `fk_ifp_responsavel` FOREIGN KEY (`responsavel_sugerido_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE SET NULL ON UPDATE CASCADE`
- `CONSTRAINT `fk_ifp_template` FOREIGN KEY (`template_id`) REFERENCES `imagem_funcao_template` (`idimagem_funcao_template`) ON DELETE SET NULL ON UPDATE CASCADE`
- `CONSTRAINT `fk_ifp_template_item` FOREIGN KEY (`template_item_id`) REFERENCES `imagem_funcao_template_item` (`idimagem_funcao_template_item`) ON DELETE SET NULL ON UPDATE CASCADE`

## imagem_funcao_planejada_historico

Fonte: backup, linha 7136. Engine: InnoDB.

| Campo                                 | Definição existente                               |
| ------------------------------------- | ------------------------------------------------- |
| `idimagem_funcao_planejada_historico` | `int NOT NULL AUTO_INCREMENT`                     |
| `imagem_funcao_planejada_id`          | `int DEFAULT NULL`                                |
| `imagem_id`                           | `int NOT NULL`                                    |
| `funcao_id`                           | `int NOT NULL`                                    |
| `acao`                                | `varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL` |
| `payload`                             | `json DEFAULT NULL`                               |
| `responsavel_id`                      | `int DEFAULT NULL`                                |
| `created_at`                          | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`     |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`idimagem_funcao_planejada_historico`)`
- `KEY `idx_ifph_imagem` (`imagem_id`)`
- `KEY `idx_ifph_funcao` (`funcao_id`)`
- `KEY `idx_ifph_planejada` (`imagem_funcao_planejada_id`)`
- `KEY `idx_ifph_acao` (`acao`)`
- `KEY `fk_ifph_responsavel` (`responsavel_id`)`
- `CONSTRAINT `fk_ifph_funcao` FOREIGN KEY (`funcao_id`) REFERENCES `funcao` (`idfuncao`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `fk_ifph_imagem` FOREIGN KEY (`imagem_id`) REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `fk_ifph_planejada` FOREIGN KEY (`imagem_funcao_planejada_id`) REFERENCES `imagem_funcao_planejada` (`idimagem_funcao_planejada`) ON DELETE SET NULL ON UPDATE CASCADE`
- `CONSTRAINT `fk_ifph_responsavel` FOREIGN KEY (`responsavel_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE SET NULL ON UPDATE CASCADE`

## funcao_imagem_angulo_ciencia

Fonte: backup, linha 6382. Engine: InnoDB.

| Campo                          | Definição existente                           |
| ------------------------------ | --------------------------------------------- |
| `id`                           | `bigint unsigned NOT NULL AUTO_INCREMENT`     |
| `funcao_imagem_id`             | `int NOT NULL`                                |
| `historico_imagem_id`          | `int NOT NULL`                                |
| `colaborador_id`               | `int NOT NULL`                                |
| `escolhido_por_colaborador_id` | `int DEFAULT NULL`                            |
| `escolhido_em`                 | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `visualizado_em`               | `datetime DEFAULT NULL`                       |
| `criado_em`                    | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP` |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `uq_angulo_ciencia_destinatario` (`funcao_imagem_id`,`historico_imagem_id`,`colaborador_id`)`
- `KEY `idx_angulo_ciencia_pendente` (`colaborador_id`,`visualizado_em`,`escolhido_em`)`
- `KEY `fk_angulo_ciencia_escolhedor` (`escolhido_por_colaborador_id`)`
- `CONSTRAINT `fk_angulo_ciencia_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE RESTRICT`
- `CONSTRAINT `fk_angulo_ciencia_escolhedor` FOREIGN KEY (`escolhido_por_colaborador_id`) REFERENCES `colaborador` (`idcolaborador`) ON DELETE SET NULL`
- `CONSTRAINT `fk_angulo_ciencia_funcao` FOREIGN KEY (`funcao_imagem_id`) REFERENCES `funcao_imagem` (`idfuncao_imagem`) ON DELETE CASCADE`

## pre_alt_lote

Fonte: backup, linha 11357. Engine: InnoDB.

| Campo                      | Definição existente                                                                                                                                     |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `id`                       | `int unsigned NOT NULL AUTO_INCREMENT`                                                                                                                  |
| `obra_id`                  | `int NOT NULL`                                                                                                                                          |
| `status_id`                | `int NOT NULL`                                                                                                                                          |
| `data_finalizacao_cliente` | `date NOT NULL`                                                                                                                                         |
| `status`                   | `enum('EM_TRIAGEM','AGUARDANDO_CLIENTE','PRONTO_PLANEJAMENTO','PLANEJADO','CANCELADO','HOLD') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EM_TRIAGEM'` |
| `status_antes_hold`        | `varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                                                                                   |
| `prioridade`               | `enum('BAIXA','NORMAL','ALTA','CRITICA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NORMAL'`                                                          |
| `prazo`                    | `date DEFAULT NULL`                                                                                                                                     |
| `responsavel_id`           | `int unsigned DEFAULT NULL`                                                                                                                             |
| `created_by`               | `int unsigned DEFAULT NULL`                                                                                                                             |
| `created_at`               | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                                                                                                           |
| `updated_at`               | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`                                                                               |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `idx_pre_alt_lote_status` (`status`,`data_finalizacao_cliente`)`
- `KEY `idx_pre_alt_lote_obra_status_data` (`obra_id`,`status_id`,`data_finalizacao_cliente`)`
- `CONSTRAINT `fk_pre_alt_lote_obra` FOREIGN KEY (`obra_id`) REFERENCES `obra` (`idobra`) ON DELETE CASCADE ON UPDATE CASCADE`

## pre_alt_lote_historico

Fonte: backup, linha 11424. Engine: InnoDB.

| Campo             | Definição existente                                   |
| ----------------- | ----------------------------------------------------- |
| `id`              | `int unsigned NOT NULL AUTO_INCREMENT`                |
| `pre_alt_lote_id` | `int unsigned NOT NULL`                               |
| `item_id`         | `int unsigned DEFAULT NULL`                           |
| `batch_id`        | `varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `tipo_evento`     | `varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL`     |
| `campo`           | `varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `valor_anterior`  | `text COLLATE utf8mb4_unicode_ci`                     |
| `valor_novo`      | `text COLLATE utf8mb4_unicode_ci`                     |
| `observacao`      | `text COLLATE utf8mb4_unicode_ci`                     |
| `usuario_id`      | `int unsigned DEFAULT NULL`                           |
| `colaborador_id`  | `int unsigned DEFAULT NULL`                           |
| `contexto_json`   | `longtext COLLATE utf8mb4_unicode_ci`                 |
| `created_at`      | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`         |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `idx_pre_alt_hist_lote_data` (`pre_alt_lote_id`,`created_at`)`
- `KEY `idx_pre_alt_hist_batch` (`batch_id`)`
- `KEY `idx_pre_alt_hist_evento` (`tipo_evento`)`
- `CONSTRAINT `fk_pre_alt_hist_lote` FOREIGN KEY (`pre_alt_lote_id`) REFERENCES `pre_alt_lote` (`id`) ON DELETE CASCADE ON UPDATE CASCADE`

## pre_alt_itens

Fonte: backup, linha 11239. Engine: InnoDB.

| Campo                    | Definição existente                                                                                     |
| ------------------------ | ------------------------------------------------------------------------------------------------------- |
| `id`                     | `int unsigned NOT NULL AUTO_INCREMENT`                                                                  |
| `pre_alt_lote_id`        | `int unsigned NOT NULL`                                                                                 |
| `review_batch_item_id`   | `int NOT NULL`                                                                                          |
| `entrega_id`             | `int NOT NULL`                                                                                          |
| `entrega_item_id`        | `int DEFAULT NULL`                                                                                      |
| `imagem_id`              | `int NOT NULL`                                                                                          |
| `resultado`              | `enum('ALTERACAO','SEM_ALTERACAO','AGUARDANDO_CLIENTE') COLLATE utf8mb4_unicode_ci DEFAULT 'ALTERACAO'` |
| `nivel_complexidade`     | `tinyint unsigned DEFAULT NULL`                                                                         |
| `tipo_alteracao`         | `varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                                                   |
| `acao`                   | `text COLLATE utf8mb4_unicode_ci`                                                                       |
| `necessita_retorno`      | `tinyint(1) NOT NULL DEFAULT '0'`                                                                       |
| `quantidade_comentarios` | `int unsigned DEFAULT NULL`                                                                             |
| `reanalise_pos_retorno`  | `tinyint(1) NOT NULL DEFAULT '0'`                                                                       |
| `responsavel_id`         | `int unsigned DEFAULT NULL`                                                                             |
| `created_at`             | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`                                                           |
| `updated_at`             | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`                               |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `ux_pre_alt_item_batch_item` (`review_batch_item_id`)`
- `KEY `idx_pre_alt_itens_lote` (`pre_alt_lote_id`)`
- `KEY `idx_pre_alt_itens_imagem` (`imagem_id`)`
- `KEY `idx_pre_alt_itens_resultado` (`resultado`,`nivel_complexidade`)`
- `KEY `fk_pre_alt_itens_entrega` (`entrega_id`)`
- `CONSTRAINT `fk_pre_alt_itens_entrega` FOREIGN KEY (`entrega_id`) REFERENCES `entregas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `fk_pre_alt_itens_imagem` FOREIGN KEY (`imagem_id`) REFERENCES `imagens_cliente_obra` (`idimagens_cliente_obra`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `fk_pre_alt_itens_lote` FOREIGN KEY (`pre_alt_lote_id`) REFERENCES `pre_alt_lote` (`id`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `fk_pre_alt_itens_review_item` FOREIGN KEY (`review_batch_item_id`) REFERENCES `review_batch_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE`

## pre_alt_liberacoes

Fonte: backup, linha 11321. Engine: InnoDB.

| Campo                  | Definição existente                           |
| ---------------------- | --------------------------------------------- |
| `id`                   | `int unsigned NOT NULL AUTO_INCREMENT`        |
| `pre_alt_lote_id`      | `int unsigned NOT NULL`                       |
| `data_triagem`         | `date NOT NULL`                               |
| `entrega_ef_id`        | `int DEFAULT NULL`                            |
| `entrega_alteracao_id` | `int DEFAULT NULL`                            |
| `observacao`           | `text COLLATE utf8mb4_unicode_ci`             |
| `created_by`           | `int unsigned DEFAULT NULL`                   |
| `created_at`           | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP` |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `idx_pre_alt_liberacoes_lote_data` (`pre_alt_lote_id`,`created_at`)`
- `KEY `idx_pre_alt_liberacoes_entrega_ef` (`entrega_ef_id`)`
- `KEY `idx_pre_alt_liberacoes_entrega_alt` (`entrega_alteracao_id`)`
- `CONSTRAINT `fk_pre_alt_liberacoes_entrega_alt` FOREIGN KEY (`entrega_alteracao_id`) REFERENCES `entregas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE`
- `CONSTRAINT `fk_pre_alt_liberacoes_entrega_ef` FOREIGN KEY (`entrega_ef_id`) REFERENCES `entregas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE`
- `CONSTRAINT `fk_pre_alt_liberacoes_lote` FOREIGN KEY (`pre_alt_lote_id`) REFERENCES `pre_alt_lote` (`id`) ON DELETE CASCADE ON UPDATE CASCADE`

## pre_alt_liberacao_itens

Fonte: backup, linha 11286. Engine: InnoDB.

| Campo                | Definição existente                           |
| -------------------- | --------------------------------------------- |
| `id`                 | `int unsigned NOT NULL AUTO_INCREMENT`        |
| `liberacao_id`       | `int unsigned NOT NULL`                       |
| `pre_alt_item_id`    | `int unsigned NOT NULL`                       |
| `entrega_destino_id` | `int NOT NULL`                                |
| `status_destino_id`  | `int NOT NULL`                                |
| `prazo`              | `date NOT NULL`                               |
| `created_at`         | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP` |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `UNIQUE KEY `ux_pre_alt_liberacao_item` (`pre_alt_item_id`)`
- `KEY `idx_pre_alt_liberacao_itens_liberacao` (`liberacao_id`)`
- `KEY `idx_pre_alt_liberacao_itens_entrega` (`entrega_destino_id`)`
- `CONSTRAINT `fk_pre_alt_liberacao_itens_entrega` FOREIGN KEY (`entrega_destino_id`) REFERENCES `entregas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE`
- `CONSTRAINT `fk_pre_alt_liberacao_itens_item` FOREIGN KEY (`pre_alt_item_id`) REFERENCES `pre_alt_itens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `fk_pre_alt_liberacao_itens_liberacao` FOREIGN KEY (`liberacao_id`) REFERENCES `pre_alt_liberacoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE`

## acompanhamento_email

Fonte: backup, linha 61. Engine: InnoDB.

| Campo                    | Definição existente                                                                        |
| ------------------------ | ------------------------------------------------------------------------------------------ |
| `idacompanhamento_email` | `int NOT NULL AUTO_INCREMENT`                                                              |
| `obra_id`                | `int DEFAULT NULL`                                                                         |
| `colaborador_id`         | `int DEFAULT NULL`                                                                         |
| `assunto`                | `varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL`               |
| `data`                   | `date DEFAULT NULL`                                                                        |
| `ordem`                  | `int NOT NULL`                                                                             |
| `entrega_id`             | `int DEFAULT NULL`                                                                         |
| `arquivo_id`             | `int DEFAULT NULL`                                                                         |
| `tipo`                   | `varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL`                    |
| `status`                 | `varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente'` |
| `metadata`               | `json DEFAULT NULL`                                                                        |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`idacompanhamento_email`)`
- `KEY `obra_id` (`obra_id`)`
- `KEY `colaborador_id` (`colaborador_id`)`
- `KEY `idx_ae_entrega_id` (`entrega_id`)`
- `CONSTRAINT `acompanhamento_email_ibfk_1` FOREIGN KEY (`obra_id`) REFERENCES `obra` (`idobra`) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT `acompanhamento_email_ibfk_2` FOREIGN KEY (`colaborador_id`) REFERENCES `colaborador` (`idcolaborador`)`
- `CONSTRAINT `fk_ae_entrega` FOREIGN KEY (`entrega_id`) REFERENCES `entregas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE`

## logs_usuarios

Fonte: backup, linha 8004. Engine: InnoDB.

| Campo                   | Definição existente                                                          |
| ----------------------- | ---------------------------------------------------------------------------- |
| `id`                    | `int NOT NULL AUTO_INCREMENT`                                                |
| `usuario_id`            | `int DEFAULT NULL`                                                           |
| `ip`                    | `varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL`  |
| `tela_atual`            | `varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `login_time`            | `datetime DEFAULT NULL`                                                      |
| `ultima_atividade`      | `datetime DEFAULT NULL`                                                      |
| `last_panel_shown_date` | `date DEFAULT NULL`                                                          |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`

## logs_usuarios_historico

Fonte: backup, linha 8036. Engine: InnoDB.

| Campo        | Definição existente                                     |
| ------------ | ------------------------------------------------------- |
| `id`         | `int unsigned NOT NULL AUTO_INCREMENT`                  |
| `usuario_id` | `int unsigned NOT NULL`                                 |
| `tela`       | `varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL`  |
| `url`        | `varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `ip`         | `varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL`   |
| `user_agent` | `varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL`  |
| `created_at` | `datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`           |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
- `KEY `idx_usuario_created_at` (`usuario_id`,`created_at`)`

## colaborador

Fonte: backup, linha 2322. Engine: InnoDB.

| Campo                         | Definição existente                                                                                                   |
| ----------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| `idcolaborador`               | `int NOT NULL AUTO_INCREMENT`                                                                                         |
| `nome_colaborador`            | `varchar(45) NOT NULL`                                                                                                |
| `telefone`                    | `varchar(20) DEFAULT NULL`                                                                                            |
| `ativo`                       | `tinyint(1) NOT NULL DEFAULT '1'`                                                                                     |
| `elegivel_capacidade`         | `tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indica se o colaborador representa capacidade produtiva nos planejamentos'` |
| `cargo_id`                    | `int DEFAULT NULL`                                                                                                    |
| `imagem`                      | `varchar(255) DEFAULT NULL`                                                                                           |
| `valor_fixo`                  | `decimal(10,2) DEFAULT '0.00'`                                                                                        |
| `tipo_remuneracao`            | `enum('FIXO','VARIAVEL','FIXO_VARIAVEL') DEFAULT NULL`                                                                |
| `participa_fechamento_mensal` | `tinyint DEFAULT NULL`                                                                                                |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`idcolaborador`)`
- `KEY `cargo_id` (`cargo_id`)`
- `CONSTRAINT `colaborador_ibfk_1` FOREIGN KEY (`cargo_id`) REFERENCES `cargo` (`id`)`
- `CONSTRAINT `chk_colaborador_participa_fechamento` CHECK ((`participa_fechamento_mensal` in (0,1)))`

## usuario

Fonte: backup, linha 13300. Engine: InnoDB.

| Campo           | Definição existente                                                      |
| --------------- | ------------------------------------------------------------------------ |
| `idusuario`     | `int NOT NULL AUTO_INCREMENT`                                            |
| `nome_usuario`  | `varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL` |
| `login`         | `varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL`  |
| `senha`         | `varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL`  |
| `nivel_acesso`  | `int DEFAULT NULL`                                                       |
| `email`         | `varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                    |
| `idcolaborador` | `int DEFAULT NULL`                                                       |
| `ultimo_acesso` | `datetime DEFAULT NULL`                                                  |
| `nome_slack`    | `varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL`                    |
| `ativo`         | `tinyint(1) NOT NULL DEFAULT '1'`                                        |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`idusuario`)`

## funcao

Fonte: backup, linha 5588. Engine: InnoDB.

| Campo         | Definição existente                                                     |
| ------------- | ----------------------------------------------------------------------- |
| `idfuncao`    | `int NOT NULL AUTO_INCREMENT`                                           |
| `nome_funcao` | `varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL` |
| `limite`      | `int NOT NULL`                                                          |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`idfuncao`)`

## obra

Fonte: backup, linha 8696. Engine: InnoDB.

| Campo                       | Definição existente               |
| --------------------------- | --------------------------------- |
| `idobra`                    | `int NOT NULL AUTO_INCREMENT`     |
| `dias_uteis`                | `int DEFAULT '0'`                 |
| `prazo_dias_corridos`       | `tinyint(1) NOT NULL DEFAULT '0'` |
| `nome_obra`                 | `varchar(45) DEFAULT NULL`        |
| `nome_completo`             | `varchar(150) DEFAULT NULL`       |
| `nome_real`                 | `varchar(100) DEFAULT NULL`       |
| `status_obra`               | `tinyint DEFAULT NULL`            |
| `complexidade_modelagem_id` | `tinyint unsigned DEFAULT NULL`   |
| `nomenclatura`              | `varchar(10) DEFAULT NULL`        |
| `cliente`                   | `int DEFAULT NULL`                |
| `data_final`                | `date DEFAULT NULL`               |
| `animacao`                  | `tinyint(1) DEFAULT NULL`         |
| `recebimento_arquivos`      | `date DEFAULT NULL`               |
| `local`                     | `varchar(100) DEFAULT NULL`       |
| `maps_url`                  | `varchar(700) DEFAULT NULL`       |
| `altura_drone`              | `int DEFAULT NULL`                |
| `fotografico`               | `varchar(255) DEFAULT NULL`       |
| `link_drive`                | `varchar(100) DEFAULT NULL`       |
| `link_review`               | `varchar(100) DEFAULT NULL`       |
| `google_earth`              | `varchar(2048) DEFAULT NULL`      |
| `token`                     | `varchar(64) DEFAULT NULL`        |
| `liberar_modelagem`         | `tinyint(1) NOT NULL DEFAULT '0'` |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`idobra`)`
- `UNIQUE KEY `token` (`token`)`
- `KEY `idx_obra_complexidade_modelagem` (`complexidade_modelagem_id`)`
- `CONSTRAINT `fk_obra_complexidade_modelagem` FOREIGN KEY (`complexidade_modelagem_id`) REFERENCES `complexidade_modelagem` (`id`) ON DELETE SET NULL ON UPDATE CASCADE`

## status_imagem

Fonte: backup, linha 12978. Engine: InnoDB.

| Campo         | Definição existente           |
| ------------- | ----------------------------- |
| `idstatus`    | `int NOT NULL AUTO_INCREMENT` |
| `nome_status` | `varchar(50) DEFAULT NULL`    |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`idstatus`)`

## substatus_imagem

Fonte: backup, linha 13002. Engine: InnoDB.

| Campo            | Definição existente                                   |
| ---------------- | ----------------------------------------------------- |
| `id`             | `int NOT NULL AUTO_INCREMENT`                         |
| `nome_substatus` | `varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL` |
| `nome_completo`  | `text COLLATE utf8mb4_unicode_ci NOT NULL`            |

Chaves, índices e restrições declarados:

- `PRIMARY KEY (`id`)`
