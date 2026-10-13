<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../helpers/referencia_arquitetonica_helper.php';
require_once __DIR__ . '/../helpers/pdf_reference_helper.php';
function mp_migrate_phase2(mysqli $c, array $associations = []): void
{
    $column = fn($table, $name) => $c->query("SHOW COLUMNS FROM `$table` LIKE '$name'")->num_rows > 0;
    $constraint = function($table, $name) use ($c) {
        return mp_row($c, 'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?', 'ss', [$table,$name]) !== null;
    };
    $c->query("CREATE TABLE IF NOT EXISTS mapa_documentos (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, obra_id INT NOT NULL,
        caminho VARCHAR(500) NOT NULL, nome_original VARCHAR(255) NOT NULL,
        tamanho_bytes BIGINT UNSIGNED NOT NULL, total_paginas INT UNSIGNED NOT NULL,
        sha256 CHAR(64) NOT NULL, criado_por INT NOT NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_mp_documento_hash (obra_id,sha256),
        CONSTRAINT fk_mp_documento_obra FOREIGN KEY (obra_id) REFERENCES obra(idobra),
        CONSTRAINT fk_mp_documento_autor FOREIGN KEY (criado_por) REFERENCES colaborador(idcolaborador)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach (['obra_id'=>'INT NULL','subtipo_id'=>'INT NULL','documento_id'=>'INT NULL','pagina'=>'INT UNSIGNED NOT NULL DEFAULT 1','legado_json'=>'JSON NULL'] as $name=>$type) {
        if (!$column('referencias_arquitetonicas',$name)) $c->query("ALTER TABLE referencias_arquitetonicas ADD COLUMN $name $type");
    }
    if ($column('referencias_arquitetonicas','pavimento_id')) {
        // A cópia é retomável: nunca remove dados antes de verificar a migração completa.
        $floors = mp_rows($c, 'SELECT * FROM obra_pavimentos ORDER BY id');
        foreach ($floors as $floor) {
            $ref=mp_row($c,'SELECT * FROM referencias_arquitetonicas WHERE pavimento_id=?','i',[$floor['id']]);
            if (!$ref) {
                mp_query($c,"INSERT INTO referencias_arquitetonicas (pavimento_id,nome_original,caminho,tamanho_bytes,largura_pontos,altura_pontos,criado_por) VALUES (?,'','',0,1,1,?)",'ii',[$floor['id'],$floor['criado_por']])->close();
                $ref=mp_row($c,'SELECT * FROM referencias_arquitetonicas WHERE id=?','i',[$c->insert_id]);
            }
            $docId=$ref['documento_id'];
            if (!$docId && $ref['caminho'] !== '') {
                $file=mp_pdf_local_path($ref['caminho']); $hash=hash_file('sha256',$file);
                $doc=mp_row($c,'SELECT id FROM mapa_documentos WHERE obra_id=? AND sha256=?','is',[$floor['obra_id'],$hash]);
                if (!$doc) {
                    mp_query($c,'INSERT INTO mapa_documentos (obra_id,caminho,nome_original,tamanho_bytes,total_paginas,sha256,criado_por,criado_em) VALUES (?,?,?,?,?,?,?,?)','issiisis',[$floor['obra_id'],$ref['caminho'],$ref['nome_original'],$ref['tamanho_bytes'],$ref['paginas'],$hash,$ref['criado_por'],$ref['criado_em']])->close();
                    $docId=$c->insert_id;
                } else $docId=$doc['id'];
            }
            $subtype=mp_row($c,'SELECT id FROM subtipo_imagem WHERE LOWER(nome)=LOWER(?) ORDER BY id LIMIT 1','s',[$floor['nome']]);
            // Não infere a classificação a partir de imagens ambíguas nem cria subtipos.
            $subtypeId=$associations[(int)$floor['id']] ?? $ref['subtipo_id'] ?? ($subtype['id'] ?? null);
            if ($subtypeId) mp_subtype($c,(int)$subtypeId);
            if ($subtypeId && mp_row($c,'SELECT id FROM referencias_arquitetonicas WHERE obra_id=? AND subtipo_id=? AND id<>?','iii',[$floor['obra_id'],$subtypeId,$ref['id']])) $subtypeId=null;
            $legacy=$ref['legado_json'] ?? json_encode(['pavimento'=>$floor,'referencia_original'=>$ref],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            mp_query($c,'UPDATE referencias_arquitetonicas SET obra_id=?,subtipo_id=?,documento_id=?,legado_json=? WHERE id=?','iiisi',[$floor['obra_id'],$subtypeId,$docId,$legacy,$ref['id']])->close();
        }
        if (mp_row($c,'SELECT id FROM referencias_arquitetonicas WHERE obra_id IS NULL OR legado_json IS NULL LIMIT 1')) throw new RuntimeException('Migração incompleta; legado mantido.');
        if ($constraint('ambientes','fk_mp_ambiente_pavimento')) $c->query('ALTER TABLE ambientes DROP FOREIGN KEY fk_mp_ambiente_pavimento');
        if ($column('ambientes','pavimento_id')) {
            if ($constraint('ambientes','uq_mp_ambiente_nome')) $c->query('ALTER TABLE ambientes DROP INDEX uq_mp_ambiente_nome');
            if (!$column('ambientes','referencia_id')) $c->query('ALTER TABLE ambientes ADD COLUMN referencia_id INT NULL');
            $c->query('UPDATE ambientes a JOIN referencias_arquitetonicas r ON r.pavimento_id=a.pavimento_id SET a.referencia_id=r.id');
            if (mp_row($c,'SELECT id FROM ambientes WHERE referencia_id IS NULL LIMIT 1')) throw new RuntimeException('Ambiente sem referência; legado mantido.');
            $c->query('ALTER TABLE ambientes DROP COLUMN pavimento_id');
        }
        $c->query('ALTER TABLE ambientes MODIFY referencia_id INT NOT NULL');
        if ($constraint('referencias_arquitetonicas','fk_mp_referencia_pavimento')) $c->query('ALTER TABLE referencias_arquitetonicas DROP FOREIGN KEY fk_mp_referencia_pavimento');
        if ($constraint('referencias_arquitetonicas','uq_mp_referencia_pavimento')) $c->query('ALTER TABLE referencias_arquitetonicas DROP INDEX uq_mp_referencia_pavimento');
        $c->query('ALTER TABLE referencias_arquitetonicas DROP COLUMN pavimento_id');
    }
    foreach (['nome_original','caminho','mime','tamanho_bytes','paginas','largura_pontos','altura_pontos','rotacao'] as $name) {
        if ($column('referencias_arquitetonicas',$name)) $c->query("ALTER TABLE referencias_arquitetonicas DROP COLUMN $name");
    }
    $c->query('ALTER TABLE referencias_arquitetonicas MODIFY obra_id INT NOT NULL');
    $ddl=[
        ['referencias_arquitetonicas','uq_mp_obra_subtipo','UNIQUE KEY uq_mp_obra_subtipo (obra_id,subtipo_id)'],
        ['referencias_arquitetonicas','fk_mp_config_obra','CONSTRAINT fk_mp_config_obra FOREIGN KEY (obra_id) REFERENCES obra(idobra)'],
        ['referencias_arquitetonicas','fk_mp_config_subtipo','CONSTRAINT fk_mp_config_subtipo FOREIGN KEY (subtipo_id) REFERENCES subtipo_imagem(id)'],
        ['referencias_arquitetonicas','fk_mp_config_documento','CONSTRAINT fk_mp_config_documento FOREIGN KEY (documento_id) REFERENCES mapa_documentos(id)'],
        ['ambientes','uq_mp_ambiente_nome','UNIQUE KEY uq_mp_ambiente_nome (referencia_id,nome)'],
        ['ambientes','fk_mp_ambiente_referencia','CONSTRAINT fk_mp_ambiente_referencia FOREIGN KEY (referencia_id) REFERENCES referencias_arquitetonicas(id) ON DELETE CASCADE'],
    ];
    foreach ($ddl as [$table,$name,$definition]) if (!$constraint($table,$name)) $c->query("ALTER TABLE $table ADD $definition");
    if(mp_row($c,'SELECT a.id FROM ambientes a LEFT JOIN referencias_arquitetonicas r ON r.id=a.referencia_id WHERE r.id IS NULL LIMIT 1')
        || mp_row($c,'SELECT d.id FROM ambiente_demarcacoes d JOIN ambientes a ON a.id=d.ambiente_id WHERE d.referencia_id<>a.referencia_id LIMIT 1')) throw new RuntimeException('Relações de ambientes divergentes; legado mantido.');
    // Cada linha original já está preservada em legado_json, sem cadastro paralelo ativo.
    $c->query('DROP TABLE IF EXISTS obra_pavimentos');
    echo "Fase 2: documentos separados; subtipos canônicos; dados legados preservados.\n";
}
