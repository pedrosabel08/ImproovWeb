-- Biblioteca Oficial ALMA v1.0 - seed imutavel.
-- Gerado de Biblioteca Oficial ALMA v1.0.pdf por ALMA/scripts/build_library_v1_seed.py.
-- O conteudo oficial existe uma unica vez na Biblioteca; revisoes guardam somente vinculos e contexto.
START TRANSACTION;

INSERT INTO
    alma_biblioteca_versao (
        codigo,
        nome,
        estado,
        origem_documento,
        checksum_origem,
        criada_em,
        publicada_em
    )
VALUES (
        '1.0',
        'ALMA Library v1.0',
        'PUBLICADA',
        'Biblioteca Oficial ALMA v1.0.pdf',
        'a9f08cfb7bc7a28ad881706903c864108de2474aba76ec96312509fa31369d9a',
        NOW(),
        NOW()
    )
ON DUPLICATE KEY UPDATE
    id = LAST_INSERT_ID(id);

SET
    @alma_v1 = (
        SELECT id
        FROM alma_biblioteca_versao
        WHERE
            codigo = '1.0'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_dimensao (
        versao_id,
        dimensao_pai_id,
        codigo,
        etapa_codigo,
        etapa_nome,
        pilar_codigo,
        pilar_nome,
        nome,
        tipo_conteudo,
        ordem_jornada,
        ordem_no_pilar,
        permite_multiplas,
        exige_item_biblioteca
    )
VALUES (
        @alma_v1,
        NULL,
        'atmosfera',
        'SENTIR',
        'Sentir',
        'atmosfera',
        'Atmosfera',
        'Atmosfera',
        'PILAR',
        1,
        0,
        0,
        1
    );

INSERT IGNORE INTO
    alma_biblioteca_dimensao (
        versao_id,
        dimensao_pai_id,
        codigo,
        etapa_codigo,
        etapa_nome,
        pilar_codigo,
        pilar_nome,
        nome,
        tipo_conteudo,
        ordem_jornada,
        ordem_no_pilar,
        permite_multiplas,
        exige_item_biblioteca
    )
VALUES (
        @alma_v1,
        NULL,
        'arquitetura',
        'CONSTRUIR',
        'Construir',
        'arquitetura',
        'Arquitetura',
        'Arquitetura',
        'PILAR',
        2,
        0,
        0,
        1
    );

INSERT IGNORE INTO
    alma_biblioteca_dimensao (
        versao_id,
        dimensao_pai_id,
        codigo,
        etapa_codigo,
        etapa_nome,
        pilar_codigo,
        pilar_nome,
        nome,
        tipo_conteudo,
        ordem_jornada,
        ordem_no_pilar,
        permite_multiplas,
        exige_item_biblioteca
    )
VALUES (
        @alma_v1,
        NULL,
        'materialidade',
        'MATERIALIZAR',
        'Materializar',
        'materialidade',
        'Materialidade',
        'Materialidade',
        'PILAR',
        3,
        0,
        0,
        1
    );

INSERT IGNORE INTO
    alma_biblioteca_dimensao (
        versao_id,
        dimensao_pai_id,
        codigo,
        etapa_codigo,
        etapa_nome,
        pilar_codigo,
        pilar_nome,
        nome,
        tipo_conteudo,
        ordem_jornada,
        ordem_no_pilar,
        permite_multiplas,
        exige_item_biblioteca
    )
VALUES (
        @alma_v1,
        NULL,
        'luz',
        'ILUMINAR',
        'Iluminar',
        'luz',
        'Luz',
        'Luz',
        'PILAR',
        4,
        0,
        0,
        0
    );

SET
    @alma_parent_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'luz'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_dimensao (
        versao_id,
        dimensao_pai_id,
        codigo,
        etapa_codigo,
        etapa_nome,
        pilar_codigo,
        pilar_nome,
        nome,
        tipo_conteudo,
        ordem_jornada,
        ordem_no_pilar,
        permite_multiplas,
        exige_item_biblioteca
    )
VALUES (
        @alma_v1,
        @alma_parent_dim,
        'luz_momento',
        'ILUMINAR',
        'Iluminar',
        'luz',
        'Luz',
        'Momento da Luz',
        'DIMENSAO',
        4,
        1,
        0,
        1
    );

SET
    @alma_parent_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'luz'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_dimensao (
        versao_id,
        dimensao_pai_id,
        codigo,
        etapa_codigo,
        etapa_nome,
        pilar_codigo,
        pilar_nome,
        nome,
        tipo_conteudo,
        ordem_jornada,
        ordem_no_pilar,
        permite_multiplas,
        exige_item_biblioteca
    )
VALUES (
        @alma_v1,
        @alma_parent_dim,
        'luz_linguagem',
        'ILUMINAR',
        'Iluminar',
        'luz',
        'Luz',
        'Linguagem da Luz',
        'DIMENSAO',
        4,
        2,
        0,
        1
    );

INSERT IGNORE INTO
    alma_biblioteca_dimensao (
        versao_id,
        dimensao_pai_id,
        codigo,
        etapa_codigo,
        etapa_nome,
        pilar_codigo,
        pilar_nome,
        nome,
        tipo_conteudo,
        ordem_jornada,
        ordem_no_pilar,
        permite_multiplas,
        exige_item_biblioteca
    )
VALUES (
        @alma_v1,
        NULL,
        'lifestyle',
        'VIVER',
        'Viver',
        'lifestyle',
        'Lifestyle',
        'Lifestyle',
        'PILAR',
        5,
        0,
        0,
        1
    );

INSERT IGNORE INTO
    alma_biblioteca_dimensao (
        versao_id,
        dimensao_pai_id,
        codigo,
        etapa_codigo,
        etapa_nome,
        pilar_codigo,
        pilar_nome,
        nome,
        tipo_conteudo,
        ordem_jornada,
        ordem_no_pilar,
        permite_multiplas,
        exige_item_biblioteca
    )
VALUES (
        @alma_v1,
        NULL,
        'fotografia',
        'OBSERVAR',
        'Observar',
        'fotografia',
        'Fotografia',
        'Fotografia',
        'PILAR',
        6,
        0,
        0,
        0
    );

SET
    @alma_parent_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'fotografia'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_dimensao (
        versao_id,
        dimensao_pai_id,
        codigo,
        etapa_codigo,
        etapa_nome,
        pilar_codigo,
        pilar_nome,
        nome,
        tipo_conteudo,
        ordem_jornada,
        ordem_no_pilar,
        permite_multiplas,
        exige_item_biblioteca
    )
VALUES (
        @alma_v1,
        @alma_parent_dim,
        'fotografia_direcao',
        'OBSERVAR',
        'Observar',
        'fotografia',
        'Fotografia',
        'Direção Fotográfica',
        'DIMENSAO',
        6,
        1,
        0,
        0
    );

SET
    @alma_parent_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'fotografia'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_dimensao (
        versao_id,
        dimensao_pai_id,
        codigo,
        etapa_codigo,
        etapa_nome,
        pilar_codigo,
        pilar_nome,
        nome,
        tipo_conteudo,
        ordem_jornada,
        ordem_no_pilar,
        permite_multiplas,
        exige_item_biblioteca
    )
VALUES (
        @alma_v1,
        @alma_parent_dim,
        'fotografia_teste_angulos',
        'OBSERVAR',
        'Observar',
        'fotografia',
        'Fotografia',
        'Teste de Ângulos',
        'DIMENSAO',
        6,
        2,
        0,
        0
    );

SET
    @alma_parent_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'fotografia'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_dimensao (
        versao_id,
        dimensao_pai_id,
        codigo,
        etapa_codigo,
        etapa_nome,
        pilar_codigo,
        pilar_nome,
        nome,
        tipo_conteudo,
        ordem_jornada,
        ordem_no_pilar,
        permite_multiplas,
        exige_item_biblioteca
    )
VALUES (
        @alma_v1,
        @alma_parent_dim,
        'fotografia_enquadramento',
        'OBSERVAR',
        'Observar',
        'fotografia',
        'Fotografia',
        'Enquadramento',
        'DIMENSAO',
        6,
        3,
        0,
        0
    );

SET
    @alma_parent_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'fotografia'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_dimensao (
        versao_id,
        dimensao_pai_id,
        codigo,
        etapa_codigo,
        etapa_nome,
        pilar_codigo,
        pilar_nome,
        nome,
        tipo_conteudo,
        ordem_jornada,
        ordem_no_pilar,
        permite_multiplas,
        exige_item_biblioteca
    )
VALUES (
        @alma_v1,
        @alma_parent_dim,
        'fotografia_referencias_sire',
        'OBSERVAR',
        'Observar',
        'fotografia',
        'Fotografia',
        'Referências SIRE',
        'DIMENSAO',
        6,
        4,
        1,
        0
    );

INSERT IGNORE INTO
    alma_biblioteca_dimensao (
        versao_id,
        dimensao_pai_id,
        codigo,
        etapa_codigo,
        etapa_nome,
        pilar_codigo,
        pilar_nome,
        nome,
        tipo_conteudo,
        ordem_jornada,
        ordem_no_pilar,
        permite_multiplas,
        exige_item_biblioteca
    )
VALUES (
        @alma_v1,
        NULL,
        'composicao',
        'CONTAR',
        'Contar',
        'composicao',
        'Composição',
        'Composição',
        'PILAR',
        7,
        0,
        0,
        1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'luz_momento'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        ordem
    )
VALUES (
        @alma_dim,
        'amanhecer',
        'Amanhecer',
        'Momento da Luz: Amanhecer.',
        1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'luz_momento'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        ordem
    )
VALUES (
        @alma_dim,
        'manha',
        'Manhã',
        'Momento da Luz: Manhã.',
        2
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'luz_momento'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        ordem
    )
VALUES (
        @alma_dim,
        'meio_dia',
        'Meio-dia',
        'Momento da Luz: Meio-dia.',
        3
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'luz_momento'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        ordem
    )
VALUES (
        @alma_dim,
        'tarde',
        'Tarde',
        'Momento da Luz: Tarde.',
        4
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'luz_momento'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        ordem
    )
VALUES (
        @alma_dim,
        'golden_hour',
        'Golden Hour',
        'Momento da Luz: Golden Hour.',
        5
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'luz_momento'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        ordem
    )
VALUES (
        @alma_dim,
        'blue_hour',
        'Blue Hour',
        'Momento da Luz: Blue Hour.',
        6
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'luz_momento'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        ordem
    )
VALUES (
        @alma_dim,
        'dusk',
        'Dusk',
        'Momento da Luz: Dusk.',
        7
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'luz_momento'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        ordem
    )
VALUES (
        @alma_dim,
        'noite',
        'Noite',
        'Momento da Luz: Noite.',
        8
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'atmosfera'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'contemplacao',
        'Contemplação',
        'O empreendimento valoriza a desaceleração, integração com a natureza e experiências sensoriais dos espaços.',
        'Atmosfera voltada para desaceleração, observação e conexão silenciosa com o ambiente.',
        'O empreendimento valoriza a desaceleração, integração com a natureza e experiências sensoriais dos espaços.',
        'Contemplação não é ausência de atividade. Contemplação é presença no momento. Uma imagem contemplativa não depende de espaços vazios ou da ausência de pessoas, mas da capacidade de transmitir desaceleração, observação e conexão genuína com o ambiente.',
        'Como queremos que o observador se sinta? A imagem deve transmitir calma, respiro e conexão com o ambiente. O observador deve sentir que existe tempo para permanecer no espaço, observar seus detalhes e apreciar a experiência proposta pela arquitetura. O que deve dominar a percepção da imagem? A sensação de tranquilidade deve ser percebida antes mesmo dos elementos arquitetônicos. A natureza, a luz e os vazios visuais devem conduzir o olhar e criar uma experiência de observação sem pressa. O que reforça essa atmosfera? ● Integração com áreas verdes ● Luz natural suave ● Poucos elementos competindo pela atenção ● Espaços organizados e equilibrados ● Ritmo visual tranquilo ● Relação entre interior e exterior O que enfraquece essa atmosfera? ● Excesso de pessoas ● Poluição visual ● Objetos desnecessários em cena ● Contrastes agressivos ● Sensação de pressa ou movimento excessivo ● Excesso de informação competindo pela atenção',
        1
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'contemplacao'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Desaceleração', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Conexão com a natureza', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Experiência sensorial', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Silêncio visual', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Permanência nos espaços', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Integração com áreas verdes', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Luz natural suave', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Poucos elementos competindo pela atenção', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Espaços organizados e equilibrados', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Ritmo visual tranquilo', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Relação entre interior e exterior', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de pessoas', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Poluição visual', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Movimento excessivo', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Contrastes agressivos', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida (Exibida no Card) Diferença Principal Atmosfera voltada para desaceleração, observação e conexão silenciosa com o ambiente. Descrição O empreendimento valoriza a desaceleração, integração com a natureza e experiências sensoriais dos espaços. Características ● Desaceleração ● Conexão com a natureza ● Experiência sensorial ● Silêncio visual ● Permanência nos espaços Evitar ● Excesso de pessoas ● Poluição visual ● Movimento excessivo ● Contrastes agressivos Princípio Fundamental Contemplação não é ausência de atividade. Contemplação é presença no momento. Uma imagem contemplativa não depende de espaços vazios ou da ausência de pessoas, mas da capacidade de transmitir desaceleração, observação e conexão genuína com o ambiente. Diretriz Completa (Ver Mais) Como queremos que o observador se sinta? A imagem deve transmitir calma, respiro e conexão com o ambiente. O observador deve sentir que existe tempo para permanecer no espaço, observar seus detalhes e apreciar a experiência proposta pela arquitetura. O que deve dominar a percepção da imagem? A sensação de tranquilidade deve ser percebida antes mesmo dos elementos arquitetônicos. A natureza, a luz e os vazios visuais devem conduzir o olhar e criar uma experiência de observação sem pressa. O que reforça essa atmosfera? ● Integração com áreas verdes ● Luz natural suave ● Poucos elementos competindo pela atenção ● Espaços organizados e equilibrados ● Ritmo visual tranquilo ● Relação entre interior e exterior O que enfraquece essa atmosfera? ● Excesso de pessoas ● Poluição visual ● Objetos desnecessários em cena ● Contrastes agressivos ● Sensação de pressa ou movimento excessivo ● Excesso de informação competindo pela atenção',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'atmosfera'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'bem_estar',
        'Bem-Estar',
        'O empreendimento valoriza qualidade de vida, conforto físico e equilíbrio entre rotina, descanso e contato com o ambiente.',
        'Atmosfera voltada para conforto físico, equilíbrio emocional e qualidade de vida no uso cotidiano dos espaços. Versão Resumida (Exibida no Card)',
        'O empreendimento valoriza qualidade de vida, conforto físico e equilíbrio entre rotina, descanso e contato com o ambiente.',
        'O conforto deve ser percebido antes da estética. Uma imagem de bem-estar não é definida pela beleza do espaço, mas pela sensação de equilíbrio, qualidade de vida e conforto que ele transmite para quem o utiliza.',
        'Como queremos que o observador se sinta? A imagem deve transmitir conforto, equilíbrio e qualidade de vida. O observador deve sentir que o espaço contribui positivamente para sua rotina, promovendo relaxamento, saúde e bem-estar. O que deve dominar a percepção da imagem? A sensação de conforto deve ser percebida antes dos elementos estéticos. A relação entre luz, espaço, natureza e ergonomia deve comunicar uma experiência agradável, equilibrada e acolhedora. O que reforça essa atmosfera? ● Luz natural abundante ● Integração com áreas verdes ● Espaços amplos e organizados ● Materiais acolhedores ● Ambientes limpos e arejados ● Relação harmoniosa entre interior e exterior O que enfraquece essa atmosfera? ● Ambientes visualmente carregados ● Sensação de confinamento ● Excesso de elementos competindo pela atenção ● Contrastes muito agressivos ● Espaços que transmitam tensão ou desconforto ● Falta de conexão com elementos naturais Princípio Fundamental O conforto deve ser percebido antes da estética. Uma imagem de bem-estar não é definida pela beleza do espaço, mas pela sensação de equilíbrio, qualidade de vida e conforto que ele transmite para quem o utiliza.',
        2
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'bem_estar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Conforto físico', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Equilíbrio emocional', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Qualidade de vida', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Luz natural', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Conexão com o entorno', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Luz natural abundante', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Integração com áreas verdes', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Espaços amplos e organizados', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Materiais acolhedores', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Ambientes limpos e arejados', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Relação harmoniosa entre interior e exterior', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sensação de estresse', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de informação visual', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes congestionados', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Contrastes agressivos', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Diferença Principal Atmosfera voltada para conforto físico, equilíbrio emocional e qualidade de vida no uso cotidiano dos espaços. Versão Resumida (Exibida no Card) Descrição O empreendimento valoriza qualidade de vida, conforto físico e equilíbrio entre rotina, descanso e contato com o ambiente. Características ● Conforto físico ● Equilíbrio emocional ● Qualidade de vida ● Luz natural ● Conexão com o entorno Evitar ● Sensação de estresse ● Excesso de informação visual ● Ambientes congestionados ● Contrastes agressivos Diretriz Completa (Ver Mais) Como queremos que o observador se sinta? A imagem deve transmitir conforto, equilíbrio e qualidade de vida. O observador deve sentir que o espaço contribui positivamente para sua rotina, promovendo relaxamento, saúde e bem-estar. O que deve dominar a percepção da imagem? A sensação de conforto deve ser percebida antes dos elementos estéticos. A relação entre luz, espaço, natureza e ergonomia deve comunicar uma experiência agradável, equilibrada e acolhedora. O que reforça essa atmosfera? ● Luz natural abundante ● Integração com áreas verdes ● Espaços amplos e organizados ● Materiais acolhedores ● Ambientes limpos e arejados ● Relação harmoniosa entre interior e exterior O que enfraquece essa atmosfera? ● Ambientes visualmente carregados ● Sensação de confinamento ● Excesso de elementos competindo pela atenção ● Contrastes muito agressivos ● Espaços que transmitam tensão ou desconforto ● Falta de conexão com elementos naturais Princípio Fundamental O conforto deve ser percebido antes da estética. Uma imagem de bem-estar não é definida pela beleza do espaço, mas pela sensação de equilíbrio, qualidade de vida e conforto que ele transmite para quem o utiliza.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'atmosfera'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'sofisticacao',
        'Sofisticação',
        'O empreendimento valoriza elegância, refinamento e atenção aos detalhes, criando uma experiência visual marcada pela qualidade e pelo cuidado na execução.',
        'Atmosfera voltada para transmitir refinamento, elegância e percepção de qualidade através das escolhas arquitetônicas, materiais e composição dos espaços. Versão Resumida (Exibida no Card)',
        'O empreendimento valoriza elegância, refinamento e atenção aos detalhes, criando uma experiência visual marcada pela qualidade e pelo cuidado na execução.',
        'Sofisticação Sofisticação não é quantidade. Sofisticação é intenção.',
        'Como queremos que o observador se sinta? A imagem deve transmitir confiança, admiração e percepção de qualidade. O observador deve perceber que cada elemento do espaço foi cuidadosamente pensado, gerando uma sensação de refinamento sem excessos. O que deve dominar a percepção da imagem? A qualidade da arquitetura, dos materiais e dos detalhes deve ser percebida antes dos elementos decorativos. O conjunto deve transmitir equilíbrio, cuidado e sofisticação através da composição dos espaços. O que reforça essa atmosfera? ● Materiais nobres e bem aplicados ● Paleta de cores equilibrada ● Detalhamento arquitetônico refinado ● Ambientes organizados ● Iluminação cuidadosamente trabalhada ● Composição limpa e intencional O que enfraquece essa atmosfera? ● Excesso de objetos decorativos ● Poluição visual ● Elementos genéricos ou sem curadoria ● Mistura excessiva de estilos ● Sensação de improviso ● Ostentação que sobreponha a arquitetura Princípio Fundamental Sofisticação Sofisticação não é quantidade. Sofisticação é intenção.',
        3
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'sofisticacao'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Elegância', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Refinamento', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Curadoria', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Materiais nobres', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Precisão nos detalhes', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Materiais nobres e bem aplicados', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Paleta de cores equilibrada', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Detalhamento arquitetônico refinado', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Ambientes organizados', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Iluminação cuidadosamente trabalhada', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Composição limpa e intencional', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Exageros visuais', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ostentação desnecessária', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Acúmulo de elementos decorativos', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Mistura excessiva de linguagens', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Diferença Principal Atmosfera voltada para transmitir refinamento, elegância e percepção de qualidade através das escolhas arquitetônicas, materiais e composição dos espaços. Versão Resumida (Exibida no Card) Descrição O empreendimento valoriza elegância, refinamento e atenção aos detalhes, criando uma experiência visual marcada pela qualidade e pelo cuidado na execução. Características ● Elegância ● Refinamento ● Curadoria ● Materiais nobres ● Precisão nos detalhes Evitar ● Exageros visuais ● Ostentação desnecessária ● Acúmulo de elementos decorativos ● Mistura excessiva de linguagens Diretriz Completa (Ver Mais) Como queremos que o observador se sinta? A imagem deve transmitir confiança, admiração e percepção de qualidade. O observador deve perceber que cada elemento do espaço foi cuidadosamente pensado, gerando uma sensação de refinamento sem excessos. O que deve dominar a percepção da imagem? A qualidade da arquitetura, dos materiais e dos detalhes deve ser percebida antes dos elementos decorativos. O conjunto deve transmitir equilíbrio, cuidado e sofisticação através da composição dos espaços. O que reforça essa atmosfera? ● Materiais nobres e bem aplicados ● Paleta de cores equilibrada ● Detalhamento arquitetônico refinado ● Ambientes organizados ● Iluminação cuidadosamente trabalhada ● Composição limpa e intencional O que enfraquece essa atmosfera? ● Excesso de objetos decorativos ● Poluição visual ● Elementos genéricos ou sem curadoria ● Mistura excessiva de estilos ● Sensação de improviso ● Ostentação que sobreponha a arquitetura Princípio Fundamental Sofisticação Sofisticação não é quantidade. Sofisticação é intenção.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'atmosfera'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'exclusividade',
        'Exclusividade',
        'O empreendimento valoriza a sensação de privilégio, singularidade e acesso diferenciado, criando uma experiência percebida como rara e especial.',
        'Atmosfera voltada para transmitir privilégio, singularidade e acesso diferenciado. O observador deve sentir que está diante de algo raro, reservado e difícil de replicar. Versão Resumida (Exibida no Card)',
        'O empreendimento valoriza a sensação de privilégio, singularidade e acesso diferenciado, criando uma experiência percebida como rara e especial.',
        'Exclusividade não é luxo. Exclusividade é raridade. Uma experiência exclusiva não é definida apenas pela qualidade dos materiais ou pelo nível de acabamento, mas pela percepção de acesso a algo especial, singular e reservado.',
        'Como queremos que o observador se sinta? A imagem deve transmitir privilégio e distinção. O observador deve sentir que está diante de uma experiência reservada a poucos, com características únicas e dificilmente encontradas em outros empreendimentos. O que deve dominar a percepção da imagem? Os atributos exclusivos do empreendimento devem receber protagonismo. Vistas privilegiadas, espaços reservados, diferenciais arquitetônicos ou experiências únicas devem ser percebidos antes dos demais elementos. O que reforça essa atmosfera? ● Vistas privilegiadas ● Baixa densidade de ocupação ● Espaços amplos e reservados ● Diferenciais arquitetônicos marcantes ● Sensação de privacidade ● Elementos únicos do empreendimento ● Experiências difíceis de reproduzir O que enfraquece essa atmosfera? ● Excesso de pessoas ● Ambientes congestionados ● Elementos genéricos ● Sensação de produção em massa ● Espaços sem diferenciação clara ● Falta de protagonismo dos atributos exclusivos Princípio Fundamental Exclusividade não é luxo. Exclusividade é raridade. Uma experiência exclusiva não é definida apenas pela qualidade dos materiais ou pelo nível de acabamento, mas pela percepção de acesso a algo especial, singular e reservado.',
        4
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'exclusividade'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Privilégio', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Singularidade', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Raridade', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Privacidade', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Diferenciação', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Vistas privilegiadas', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Baixa densidade de ocupação', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Espaços amplos e reservados', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Diferenciais arquitetônicos marcantes', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sensação de privacidade', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Elementos únicos do empreendimento', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Experiências difíceis de reproduzir', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sensação de massificação', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes genéricos', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de ocupação', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Espaços visualmente comuns', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Diferença Principal Atmosfera voltada para transmitir privilégio, singularidade e acesso diferenciado. O observador deve sentir que está diante de algo raro, reservado e difícil de replicar. Versão Resumida (Exibida no Card) Descrição O empreendimento valoriza a sensação de privilégio, singularidade e acesso diferenciado, criando uma experiência percebida como rara e especial. Características ● Privilégio ● Singularidade ● Raridade ● Privacidade ● Diferenciação Evitar ● Sensação de massificação ● Ambientes genéricos ● Excesso de ocupação ● Espaços visualmente comuns Diretriz Completa (Ver Mais) Como queremos que o observador se sinta? A imagem deve transmitir privilégio e distinção. O observador deve sentir que está diante de uma experiência reservada a poucos, com características únicas e dificilmente encontradas em outros empreendimentos. O que deve dominar a percepção da imagem? Os atributos exclusivos do empreendimento devem receber protagonismo. Vistas privilegiadas, espaços reservados, diferenciais arquitetônicos ou experiências únicas devem ser percebidos antes dos demais elementos. O que reforça essa atmosfera? ● Vistas privilegiadas ● Baixa densidade de ocupação ● Espaços amplos e reservados ● Diferenciais arquitetônicos marcantes ● Sensação de privacidade ● Elementos únicos do empreendimento ● Experiências difíceis de reproduzir O que enfraquece essa atmosfera? ● Excesso de pessoas ● Ambientes congestionados ● Elementos genéricos ● Sensação de produção em massa ● Espaços sem diferenciação clara ● Falta de protagonismo dos atributos exclusivos Princípio Fundamental Exclusividade não é luxo. Exclusividade é raridade. Uma experiência exclusiva não é definida apenas pela qualidade dos materiais ou pelo nível de acabamento, mas pela percepção de acesso a algo especial, singular e reservado.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'atmosfera'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'acolhimento',
        'Acolhimento',
        'O empreendimento valoriza a sensação de pertencimento, proximidade humana e conforto emocional, criando espaços que convidam as pessoas a viver, compartilhar e criar memórias.',
        'Atmosfera voltada para transmitir pertencimento, proximidade humana e sensação de ser bem recebido. O observador deve sentir que o espaço foi pensado para pessoas, relações e momentos compartilhados. Versão Resumida (Exibida no Card)',
        'O empreendimento valoriza a sensação de pertencimento, proximidade humana e conforto emocional, criando espaços que convidam as pessoas a viver, compartilhar e criar memórias.',
        'Acolhimento não é conforto. Acolhimento é pertencimento. Um espaço acolhedor não é definido apenas pelo conforto físico, mas pela capacidade de fazer as pessoas se sentirem bem-vindas, representadas e emocionalmente conectadas ao ambiente. Bem-estar fala da relação entre a pessoa e o espaço, já o acolhimento fala da relação entre as pessoas dentro do espaço.',
        'Como queremos que o observador se sinta? A imagem deve transmitir acolhimento, proximidade e pertencimento. O observador deve sentir que existe espaço para viver experiências reais, compartilhar momentos e construir memórias. O que deve dominar a percepção da imagem? A sensação de que o ambiente foi pensado para as pessoas deve ser percebida antes dos aspectos estéticos. O espaço deve parecer convidativo, confortável e preparado para receber. O que reforça essa atmosfera? ● Escala humana ● Espaços de encontro e convivência ● Ambientes preparados para uso cotidiano ● Materiais visualmente aconchegantes ● Integração entre pessoas e espaço ● Sensação de vida acontecendo naturalmente O que enfraquece essa atmosfera? ● Ambientes excessivamente formais ● Espaços que parecem intocáveis ● Sensação de distanciamento emocional ● Composições rígidas ● Frieza visual ● Ausência de elementos que sugiram uso humano Princípio Fundamental Acolhimento não é conforto. Acolhimento é pertencimento. Um espaço acolhedor não é definido apenas pelo conforto físico, mas pela capacidade de fazer as pessoas se sentirem bem-vindas, representadas e emocionalmente conectadas ao ambiente. Bem-estar fala da relação entre a pessoa e o espaço, já o acolhimento fala da relação entre as pessoas dentro do espaço.',
        5
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'acolhimento'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Pertencimento', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Proximidade humana', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Conforto emocional', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Bem receber', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Relações humanas', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Escala humana', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Espaços de encontro e convivência', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Ambientes preparados para uso cotidiano', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Materiais visualmente aconchegantes', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Integração entre pessoas e espaço', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sensação de vida acontecendo naturalmente', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes impessoais', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sensação de frieza', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de formalidade', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Espaços excessivamente rígidos', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Diferença Principal Atmosfera voltada para transmitir pertencimento, proximidade humana e sensação de ser bem recebido. O observador deve sentir que o espaço foi pensado para pessoas, relações e momentos compartilhados. Versão Resumida (Exibida no Card) Descrição O empreendimento valoriza a sensação de pertencimento, proximidade humana e conforto emocional, criando espaços que convidam as pessoas a viver, compartilhar e criar memórias. Características ● Pertencimento ● Proximidade humana ● Conforto emocional ● Bem receber ● Relações humanas Evitar ● Ambientes impessoais ● Sensação de frieza ● Excesso de formalidade ● Espaços excessivamente rígidos Diretriz Completa (Ver Mais) Como queremos que o observador se sinta? A imagem deve transmitir acolhimento, proximidade e pertencimento. O observador deve sentir que existe espaço para viver experiências reais, compartilhar momentos e construir memórias. O que deve dominar a percepção da imagem? A sensação de que o ambiente foi pensado para as pessoas deve ser percebida antes dos aspectos estéticos. O espaço deve parecer convidativo, confortável e preparado para receber. O que reforça essa atmosfera? ● Escala humana ● Espaços de encontro e convivência ● Ambientes preparados para uso cotidiano ● Materiais visualmente aconchegantes ● Integração entre pessoas e espaço ● Sensação de vida acontecendo naturalmente O que enfraquece essa atmosfera? ● Ambientes excessivamente formais ● Espaços que parecem intocáveis ● Sensação de distanciamento emocional ● Composições rígidas ● Frieza visual ● Ausência de elementos que sugiram uso humano Princípio Fundamental Acolhimento não é conforto. Acolhimento é pertencimento. Um espaço acolhedor não é definido apenas pelo conforto físico, mas pela capacidade de fazer as pessoas se sentirem bem-vindas, representadas e emocionalmente conectadas ao ambiente. Bem-estar fala da relação entre a pessoa e o espaço, já o acolhimento fala da relação entre as pessoas dentro do espaço.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'atmosfera'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'conexao_com_a_natureza',
        'Conexão com a Natureza',
        'O empreendimento valoriza a integração entre pessoas, arquitetura e ambiente natural, criando espaços onde a natureza participa ativamente da experiência cotidiana.',
        'Atmosfera voltada para fortalecer a relação entre as pessoas e o ambiente natural, promovendo integração, equilíbrio e sensação de pertencimento ao lugar. Versão Resumida (Exibida no Card)',
        'O empreendimento valoriza a integração entre pessoas, arquitetura e ambiente natural, criando espaços onde a natureza participa ativamente da experiência cotidiana.',
        'Conexão com a Natureza não é vegetação. Conexão com a Natureza é integração entre pessoas, arquitetura e ambiente natural. Uma imagem não comunica essa atmosfera apenas porque possui árvores, jardins ou paisagismo. Ela comunica essa atmosfera quando os elementos naturais participam ativamente da experiência de viver o espaço.',
        'Como queremos que o observador se sinta? A imagem deve transmitir proximidade com a natureza e sensação de equilíbrio. O observador deve sentir que o ambiente natural faz parte da experiência de viver aquele espaço e não apenas da sua composição visual. O que deve dominar a percepção da imagem? A relação entre arquitetura, paisagem e ambiente natural deve ser percebida como uma experiência única. A vegetação, a luz natural, a topografia, a paisagem e os materiais devem trabalhar juntos para criar essa conexão. O que reforça essa atmosfera? ● Integração entre interior e exterior ● Presença significativa de elementos naturais ● Vistas para áreas verdes ou paisagens naturais ● Materiais com origem ou aparência natural ● Luz natural abundante ● Relação harmoniosa entre construção e entorno ● Arquitetura que valoriza o ambiente onde está inserida O que enfraquece essa atmosfera? ● Paisagismo tratado apenas como decoração ● Excesso de elementos artificiais competindo pela atenção ● Barreiras visuais para a paisagem ● Ambientes excessivamente mineralizados ● Falta de integração entre arquitetura e entorno ● Natureza presente visualmente, mas ausente da experiência do espaço Princípio Fundamental Conexão com a Natureza não é vegetação. Conexão com a Natureza é integração entre pessoas, arquitetura e ambiente natural. Uma imagem não comunica essa atmosfera apenas porque possui árvores, jardins ou paisagismo. Ela comunica essa atmosfera quando os elementos naturais participam ativamente da experiência de viver o espaço.',
        6
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'conexao_com_a_natureza'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Integração com o ambiente natural', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Relação entre interior e exterior', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Elementos naturais protagonistas', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Equilíbrio ambiental', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de respiro', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Integração entre interior e exterior', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Presença significativa de elementos naturais', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Vistas para áreas verdes ou paisagens naturais', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Materiais com origem ou aparência natural', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Luz natural abundante', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Relação harmoniosa entre construção e entorno', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Arquitetura que valoriza o ambiente onde está inserida', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Natureza meramente decorativa', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de elementos artificiais', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Barreiras visuais para a paisagem', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Paisagismo sem protagonismo na experiência', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Diferença Principal Atmosfera voltada para fortalecer a relação entre as pessoas e o ambiente natural, promovendo integração, equilíbrio e sensação de pertencimento ao lugar. Versão Resumida (Exibida no Card) Descrição O empreendimento valoriza a integração entre pessoas, arquitetura e ambiente natural, criando espaços onde a natureza participa ativamente da experiência cotidiana. Características ● Integração com o ambiente natural ● Relação entre interior e exterior ● Elementos naturais protagonistas ● Equilíbrio ambiental ● Sensação de respiro Evitar ● Natureza meramente decorativa ● Excesso de elementos artificiais ● Barreiras visuais para a paisagem ● Paisagismo sem protagonismo na experiência Diretriz Completa (Ver Mais) Como queremos que o observador se sinta? A imagem deve transmitir proximidade com a natureza e sensação de equilíbrio. O observador deve sentir que o ambiente natural faz parte da experiência de viver aquele espaço e não apenas da sua composição visual. O que deve dominar a percepção da imagem? A relação entre arquitetura, paisagem e ambiente natural deve ser percebida como uma experiência única. A vegetação, a luz natural, a topografia, a paisagem e os materiais devem trabalhar juntos para criar essa conexão. O que reforça essa atmosfera? ● Integração entre interior e exterior ● Presença significativa de elementos naturais ● Vistas para áreas verdes ou paisagens naturais ● Materiais com origem ou aparência natural ● Luz natural abundante ● Relação harmoniosa entre construção e entorno ● Arquitetura que valoriza o ambiente onde está inserida O que enfraquece essa atmosfera? ● Paisagismo tratado apenas como decoração ● Excesso de elementos artificiais competindo pela atenção ● Barreiras visuais para a paisagem ● Ambientes excessivamente mineralizados ● Falta de integração entre arquitetura e entorno ● Natureza presente visualmente, mas ausente da experiência do espaço Princípio Fundamental Conexão com a Natureza não é vegetação. Conexão com a Natureza é integração entre pessoas, arquitetura e ambiente natural. Uma imagem não comunica essa atmosfera apenas porque possui árvores, jardins ou paisagismo. Ela comunica essa atmosfera quando os elementos naturais participam ativamente da experiência de viver o espaço.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'atmosfera'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'leveza',
        'Leveza',
        'O empreendimento valoriza fluidez, simplicidade e equilíbrio visual, criando espaços que parecem naturais, descomplicados e agradáveis de percorrer.',
        'Atmosfera voltada para transmitir fluidez, simplicidade e ausência de peso visual. O observador deve sentir que o espaço acontece de forma natural e sem esforço. Versão Resumida (Exibida no Card)',
        'O empreendimento valoriza fluidez, simplicidade e equilíbrio visual, criando espaços que parecem naturais, descomplicados e agradáveis de percorrer.',
        'Leveza não é vazio. Leveza é ausência de peso visual. Uma imagem leve não depende da falta de elementos, mas da capacidade de organizar pessoas, arquitetura, materiais e composição de forma equilibrada e fluida.',
        'Como queremos que o observador se sinta? A imagem deve transmitir liberdade, fluidez e facilidade. O observador deve sentir que o espaço é agradável de percorrer, compreender e experienciar. O que deve dominar a percepção da imagem? A sensação de respiro visual deve ser percebida antes dos elementos individuais. A composição deve parecer natural e equilibrada, sem que nenhum elemento imponha peso excessivo ao conjunto. O que reforça essa atmosfera? ● Espaços bem proporcionados ● Transições suaves entre ambientes ● Circulações claras ● Composição equilibrada ● Poucos elementos competindo pela atenção ● Relação harmoniosa entre cheios e vazios ● Sensação de continuidade espacial O que enfraquece essa atmosfera? ● Poluição visual ● Excesso de mobiliário ● Acúmulo de informações ● Composição congestionada ● Barreiras visuais desnecessárias ● Elementos excessivamente pesados ou dominantes Princípio Fundamental Leveza não é vazio. Leveza é ausência de peso visual. Uma imagem leve não depende da falta de elementos, mas da capacidade de organizar pessoas, arquitetura, materiais e composição de forma equilibrada e fluida.',
        7
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'leveza'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Fluidez', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Simplicidade', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Respiro visual', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Movimento natural', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Equilíbrio', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Espaços bem proporcionados', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Transições suaves entre ambientes', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Circulações claras', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Composição equilibrada', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Poucos elementos competindo pela atenção', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Relação harmoniosa entre cheios e vazios', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sensação de continuidade espacial', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de informação visual', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes carregados', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sensação de peso', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Acúmulo de elementos competindo pela atenção', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Diferença Principal Atmosfera voltada para transmitir fluidez, simplicidade e ausência de peso visual. O observador deve sentir que o espaço acontece de forma natural e sem esforço. Versão Resumida (Exibida no Card) Descrição O empreendimento valoriza fluidez, simplicidade e equilíbrio visual, criando espaços que parecem naturais, descomplicados e agradáveis de percorrer. Características ● Fluidez ● Simplicidade ● Respiro visual ● Movimento natural ● Equilíbrio Evitar ● Excesso de informação visual ● Ambientes carregados ● Sensação de peso ● Acúmulo de elementos competindo pela atenção Diretriz Completa (Ver Mais) Como queremos que o observador se sinta? A imagem deve transmitir liberdade, fluidez e facilidade. O observador deve sentir que o espaço é agradável de percorrer, compreender e experienciar. O que deve dominar a percepção da imagem? A sensação de respiro visual deve ser percebida antes dos elementos individuais. A composição deve parecer natural e equilibrada, sem que nenhum elemento imponha peso excessivo ao conjunto. O que reforça essa atmosfera? ● Espaços bem proporcionados ● Transições suaves entre ambientes ● Circulações claras ● Composição equilibrada ● Poucos elementos competindo pela atenção ● Relação harmoniosa entre cheios e vazios ● Sensação de continuidade espacial O que enfraquece essa atmosfera? ● Poluição visual ● Excesso de mobiliário ● Acúmulo de informações ● Composição congestionada ● Barreiras visuais desnecessárias ● Elementos excessivamente pesados ou dominantes Princípio Fundamental Leveza não é vazio. Leveza é ausência de peso visual. Uma imagem leve não depende da falta de elementos, mas da capacidade de organizar pessoas, arquitetura, materiais e composição de forma equilibrada e fluida.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'atmosfera'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'vitalidade_urbana',
        'Vitalidade Urbana',
        'O empreendimento valoriza movimento, dinamismo e conexão com a cidade, criando espaços que refletem uma rotina ativa e contemporânea.',
        'Atmosfera voltada para transmitir energia, movimento e conexão com a vida urbana. O observador deve sentir que o empreendimento está inserido em um ambiente ativo, conectado e pulsante. Versão Resumida (Exibida no Card)',
        'O empreendimento valoriza movimento, dinamismo e conexão com a cidade, criando espaços que refletem uma rotina ativa e contemporânea.',
        'Vitalidade Urbana não é agitação. Vitalidade Urbana é energia com propósito. Uma imagem não comunica essa atmosfera apenas porque possui muitas pessoas ou elementos. Ela comunica essa atmosfera quando transmite uma rotina ativa, conectada e desejável.',
        'Como queremos que o observador se sinta? A imagem deve transmitir energia e atividade. O observador deve perceber que existe vida acontecendo, oportunidades ao redor e uma relação constante com a dinâmica urbana. O que deve dominar a percepção da imagem? A sensação de movimento e conexão com a cidade deve ser percebida antes da arquitetura em si. O espaço deve parecer vivo, ativo e integrado à rotina contemporânea. O que reforça essa atmosfera? ● Presença sutil de pessoas em atividade ● Relação com a cidade ● Espaços de encontro e circulação ● Conexão com serviços e conveniências ● Ambientes multifuncionais ● Sensação de vida acontecendo naturalmente O que enfraquece essa atmosfera? ● Espaços excessivamente vazios ● Sensação de isolamento ● Falta de atividade humana ● Ambientes estagnados ● Movimento artificial ou exagerado ● Poluição visual que transforme energia em caos Princípio Fundamental Vitalidade Urbana não é agitação. Vitalidade Urbana é energia com propósito. Uma imagem não comunica essa atmosfera apenas porque possui muitas pessoas ou elementos. Ela comunica essa atmosfera quando transmite uma rotina ativa, conectada e desejável.',
        8
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'vitalidade_urbana'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Movimento', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Energia', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Conexão urbana', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Dinamismo', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Vida cotidiana', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Presença sutil de pessoas em atividade', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Relação com a cidade', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Espaços de encontro e circulação', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Conexão com serviços e conveniências', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Ambientes multifuncionais', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sensação de vida acontecendo naturalmente', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sensação de caos', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Poluição visual excessiva', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes congestionados', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de estímulos competindo pela atenção', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Diferença Principal Atmosfera voltada para transmitir energia, movimento e conexão com a vida urbana. O observador deve sentir que o empreendimento está inserido em um ambiente ativo, conectado e pulsante. Versão Resumida (Exibida no Card) Descrição O empreendimento valoriza movimento, dinamismo e conexão com a cidade, criando espaços que refletem uma rotina ativa e contemporânea. Características ● Movimento ● Energia ● Conexão urbana ● Dinamismo ● Vida cotidiana Evitar ● Sensação de caos ● Poluição visual excessiva ● Ambientes congestionados ● Excesso de estímulos competindo pela atenção Diretriz Completa (Ver Mais) Como queremos que o observador se sinta? A imagem deve transmitir energia e atividade. O observador deve perceber que existe vida acontecendo, oportunidades ao redor e uma relação constante com a dinâmica urbana. O que deve dominar a percepção da imagem? A sensação de movimento e conexão com a cidade deve ser percebida antes da arquitetura em si. O espaço deve parecer vivo, ativo e integrado à rotina contemporânea. O que reforça essa atmosfera? ● Presença sutil de pessoas em atividade ● Relação com a cidade ● Espaços de encontro e circulação ● Conexão com serviços e conveniências ● Ambientes multifuncionais ● Sensação de vida acontecendo naturalmente O que enfraquece essa atmosfera? ● Espaços excessivamente vazios ● Sensação de isolamento ● Falta de atividade humana ● Ambientes estagnados ● Movimento artificial ou exagerado ● Poluição visual que transforme energia em caos Princípio Fundamental Vitalidade Urbana não é agitação. Vitalidade Urbana é energia com propósito. Uma imagem não comunica essa atmosfera apenas porque possui muitas pessoas ou elementos. Ela comunica essa atmosfera quando transmite uma rotina ativa, conectada e desejável.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'atmosfera'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'convivencia',
        'Convivência',
        'O empreendimento valoriza a interação entre pessoas, criando espaços que estimulam encontros, trocas e momentos compartilhados.',
        'Atmosfera voltada para promover encontros, interação humana e construção de relações. O observador deve perceber o espaço como um facilitador de experiências compartilhadas. Versão Resumida (Exibida no Card)',
        'O empreendimento valoriza a interação entre pessoas, criando espaços que estimulam encontros, trocas e momentos compartilhados.',
        'Convivência não é proximidade. Convivência é interação. Pessoas ocupando o mesmo espaço não necessariamente comunicam convivência. A atmosfera surge quando existe troca, compartilhamento e construção de experiências em conjunto.',
        'Como queremos que o observador se sinta? A imagem deve transmitir conexão entre pessoas. O observador deve perceber que o espaço favorece encontros espontâneos e experiências compartilhadas. O que deve dominar a percepção da imagem? A relação entre as pessoas deve ser mais importante do que os objetos ou a arquitetura. O espaço deve funcionar como palco para experiências coletivas. O que reforça essa atmosfera? ● Ambientes voltados para encontros ● Mesas compartilhadas ● Espaços de permanência coletiva ● Áreas de lazer integradas ● Interações naturais entre pessoas ● Sensação de comunidade O que enfraquece essa atmosfera? ● Espaços excessivamente individualizados ● Ambientes vazios sem intenção ● Pessoas isoladas sem conexão aparente ● Barreiras físicas entre usuários ● Composições que afastem visualmente as pessoas Princípio Fundamental Convivência não é proximidade. Convivência é interação. Pessoas ocupando o mesmo espaço não necessariamente comunicam convivência. A atmosfera surge quando existe troca, compartilhamento e construção de experiências em conjunto.',
        9
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'convivencia'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Interação humana', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Compartilhamento', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Encontros', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Comunidade', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Experiências coletivas', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Ambientes voltados para encontros', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Mesas compartilhadas', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Espaços de permanência coletiva', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Áreas de lazer integradas', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Interações naturais entre pessoas', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sensação de comunidade', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Isolamento', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Distanciamento entre usuários', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes excessivamente individuais', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Espaços que não favoreçam interação', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Diferença Principal Atmosfera voltada para promover encontros, interação humana e construção de relações. O observador deve perceber o espaço como um facilitador de experiências compartilhadas. Versão Resumida (Exibida no Card) Descrição O empreendimento valoriza a interação entre pessoas, criando espaços que estimulam encontros, trocas e momentos compartilhados. Características ● Interação humana ● Compartilhamento ● Encontros ● Comunidade ● Experiências coletivas Evitar ● Isolamento ● Distanciamento entre usuários ● Ambientes excessivamente individuais ● Espaços que não favoreçam interação Diretriz Completa (Ver Mais) Como queremos que o observador se sinta? A imagem deve transmitir conexão entre pessoas. O observador deve perceber que o espaço favorece encontros espontâneos e experiências compartilhadas. O que deve dominar a percepção da imagem? A relação entre as pessoas deve ser mais importante do que os objetos ou a arquitetura. O espaço deve funcionar como palco para experiências coletivas. O que reforça essa atmosfera? ● Ambientes voltados para encontros ● Mesas compartilhadas ● Espaços de permanência coletiva ● Áreas de lazer integradas ● Interações naturais entre pessoas ● Sensação de comunidade O que enfraquece essa atmosfera? ● Espaços excessivamente individualizados ● Ambientes vazios sem intenção ● Pessoas isoladas sem conexão aparente ● Barreiras físicas entre usuários ● Composições que afastem visualmente as pessoas Princípio Fundamental Convivência não é proximidade. Convivência é interação. Pessoas ocupando o mesmo espaço não necessariamente comunicam convivência. A atmosfera surge quando existe troca, compartilhamento e construção de experiências em conjunto.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'luz_linguagem'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'luz_difusa',
        'Luz Difusa',
        'A Luz Difusa caracteriza-se pela suavidade das transições entre áreas iluminadas e áreas em sombra. A iluminação distribui-se de maneira equilibrada pelo ambiente, reduzindo contrastes excessivos e preservando a leitura dos espaços, materiais e volumes arquitetônicos.',
        'A transição entre luz e sombra acontece de forma suave e natural.',
        'A Luz Difusa caracteriza-se pela suavidade das transições entre áreas iluminadas e áreas em sombra. A iluminação distribui-se de maneira equilibrada pelo ambiente, reduzindo contrastes excessivos e preservando a leitura dos espaços, materiais e volumes arquitetônicos.',
        'Luz Difusa não significa ausência de sombras. Significa suavidade na transição entre luz e sombra.',
        'Como essa luz se comporta? A luz se espalha pelo ambiente de maneira uniforme, reduzindo a intensidade das sombras e suavizando a transição entre áreas iluminadas e áreas escuras. A sensação visual predominante deve ser de equilíbrio, continuidade e naturalidade. O que o observador percebe primeiro? A suavidade da iluminação. A luz não busca chamar atenção para si mesma. Ela atua como suporte para a leitura da arquitetura, da materialidade e da atmosfera da cena. O que reforça essa linguagem? ✓ Céus parcialmente encobertos ou com grande difusão atmosférica ✓ Ambientes amplos e bem iluminados ✓ Reflexões suaves ✓ Materiais com resposta luminosa equilibrada ✓ Contraste moderado ✓ Preservação de detalhes em luz e sombra O que enfraquece essa linguagem? ✕ Sombras com bordas muito definidas ✕ Sol excessivamente dominante ✕ Grandes áreas sem informação luminosa ✕ Contraste exagerado entre interior e exterior ✕ Pós-produção agressiva ✕ Saturação excessiva Princípio Fundamental Luz Difusa não significa ausência de sombras. Significa suavidade na transição entre luz e sombra.',
        1
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'luz_difusa'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sombras suaves', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Baixo contraste', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Transições graduais entre luz e sombra', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Distribuição equilibrada da iluminação', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Preservação de detalhes em luz e sombra', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Reflexões suaves', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Leitura clara da materialidade', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Céus parcialmente encobertos ou com grande difusão atmosférica', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Ambientes amplos e bem iluminados', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Reflexões suaves', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Materiais com resposta luminosa equilibrada', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Contraste moderado', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Preservação de detalhes em luz e sombra', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sombras excessivamente duras', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Contraste elevado', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Highlights estourados', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Áreas excessivamente escuras', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Recortes agressivos de luz', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Contrastes localizados excessivos', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Diferença Principal A transição entre luz e sombra acontece de forma suave e natural. Descrição A Luz Difusa caracteriza-se pela suavidade das transições entre áreas iluminadas e áreas em sombra. A iluminação distribui-se de maneira equilibrada pelo ambiente, reduzindo contrastes excessivos e preservando a leitura dos espaços, materiais e volumes arquitetônicos. Características ✓ Sombras suaves ✓ Baixo contraste ✓ Transições graduais entre luz e sombra ✓ Distribuição equilibrada da iluminação ✓ Preservação de detalhes em luz e sombra ✓ Reflexões suaves ✓ Leitura clara da materialidade Evitar ✕ Sombras excessivamente duras ✕ Contraste elevado ✕ Highlights estourados ✕ Áreas excessivamente escuras ✕ Recortes agressivos de luz ✕ Contrastes localizados excessivos Diretriz Completa Como essa luz se comporta? A luz se espalha pelo ambiente de maneira uniforme, reduzindo a intensidade das sombras e suavizando a transição entre áreas iluminadas e áreas escuras. A sensação visual predominante deve ser de equilíbrio, continuidade e naturalidade. O que o observador percebe primeiro? A suavidade da iluminação. A luz não busca chamar atenção para si mesma. Ela atua como suporte para a leitura da arquitetura, da materialidade e da atmosfera da cena. O que reforça essa linguagem? ✓ Céus parcialmente encobertos ou com grande difusão atmosférica ✓ Ambientes amplos e bem iluminados ✓ Reflexões suaves ✓ Materiais com resposta luminosa equilibrada ✓ Contraste moderado ✓ Preservação de detalhes em luz e sombra O que enfraquece essa linguagem? ✕ Sombras com bordas muito definidas ✕ Sol excessivamente dominante ✕ Grandes áreas sem informação luminosa ✕ Contraste exagerado entre interior e exterior ✕ Pós-produção agressiva ✕ Saturação excessiva Princípio Fundamental Luz Difusa não significa ausência de sombras. Significa suavidade na transição entre luz e sombra.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'luz_linguagem'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'luz_direcional',
        'Luz Direcional',
        'A Luz Direcional caracteriza-se pela presença de uma fonte principal de iluminação que define a leitura do ambiente. A incidência da luz contribui diretamente para a percepção dos volumes, profundidade e formas arquitetônicas, tornando a direção da iluminação um elemento relevante da composição.',
        'Existe uma fonte luminosa dominante claramente identificável na cena.',
        'A Luz Direcional caracteriza-se pela presença de uma fonte principal de iluminação que define a leitura do ambiente. A incidência da luz contribui diretamente para a percepção dos volumes, profundidade e formas arquitetônicas, tornando a direção da iluminação um elemento relevante da composição.',
        'Luz Direcional não significa luz forte. Significa a presença clara de uma fonte luminosa dominante.',
        'Como essa luz se comporta? A iluminação é organizada a partir de uma fonte principal claramente percebida. Essa fonte determina o comportamento das sombras, dos volumes e das áreas de destaque da cena. As fontes secundárias existem para complementar a leitura, mas não devem competir com a fonte dominante. O que o observador percebe primeiro? A origem da luz. O observador deve conseguir compreender intuitivamente de onde a iluminação principal está vindo e como ela influencia a leitura do espaço. O que reforça essa linguagem luminosa? ✓ Fonte principal claramente definida ✓ Sombras coerentes e consistentes ✓ Boa leitura dos volumes arquitetônicos ✓ Profundidade gerada pela incidência da luz ✓ Contraste controlado entre áreas iluminadas e sombreadas ✓ Destaque natural para elementos importantes da cena O que enfraquece essa linguagem luminosa? ✕ Excesso de preenchimento luminoso ✕ Múltiplas áreas competindo pela atenção ✕ Sombras pouco legíveis ✕ Fontes de luz sem hierarquia ✕ Ambientes excessivamente uniformes ✕ Falta de percepção da direção luminosa Princípio Fundamental Luz Direcional não significa luz forte. Significa a presença clara de uma fonte luminosa dominante.',
        2
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'luz_direcional'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Fonte luminosa dominante claramente identificável', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sombras coerentes com a direção da luz', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Leitura acentuada dos volumes arquitetônicos', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de profundidade', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Hierarquia visual criada pela incidência luminosa', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Destaque para formas e texturas', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Fonte principal claramente definida', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sombras coerentes e consistentes', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Boa leitura dos volumes arquitetônicos', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Profundidade gerada pela incidência da luz', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Contraste controlado entre áreas iluminadas e sombreadas', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Destaque natural para elementos importantes da cena', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Múltiplas fontes competindo pelo protagonismo', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Iluminação excessivamente homogênea', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sombras contraditórias', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Perda da percepção da origem da luz', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Preenchimento excessivo que neutralize a direção luminosa', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Contrastes artificiais sem justificativa física', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Diferença Principal Existe uma fonte luminosa dominante claramente identificável na cena. Descrição A Luz Direcional caracteriza-se pela presença de uma fonte principal de iluminação que define a leitura do ambiente. A incidência da luz contribui diretamente para a percepção dos volumes, profundidade e formas arquitetônicas, tornando a direção da iluminação um elemento relevante da composição. Características ✓ Fonte luminosa dominante claramente identificável ✓ Sombras coerentes com a direção da luz ✓ Leitura acentuada dos volumes arquitetônicos ✓ Sensação de profundidade ✓ Hierarquia visual criada pela incidência luminosa ✓ Destaque para formas e texturas Evitar ✕ Múltiplas fontes competindo pelo protagonismo ✕ Iluminação excessivamente homogênea ✕ Sombras contraditórias ✕ Perda da percepção da origem da luz ✕ Preenchimento excessivo que neutralize a direção luminosa ✕ Contrastes artificiais sem justificativa física Diretriz Completa Como essa luz se comporta? A iluminação é organizada a partir de uma fonte principal claramente percebida. Essa fonte determina o comportamento das sombras, dos volumes e das áreas de destaque da cena. As fontes secundárias existem para complementar a leitura, mas não devem competir com a fonte dominante. O que o observador percebe primeiro? A origem da luz. O observador deve conseguir compreender intuitivamente de onde a iluminação principal está vindo e como ela influencia a leitura do espaço. O que reforça essa linguagem luminosa? ✓ Fonte principal claramente definida ✓ Sombras coerentes e consistentes ✓ Boa leitura dos volumes arquitetônicos ✓ Profundidade gerada pela incidência da luz ✓ Contraste controlado entre áreas iluminadas e sombreadas ✓ Destaque natural para elementos importantes da cena O que enfraquece essa linguagem luminosa? ✕ Excesso de preenchimento luminoso ✕ Múltiplas áreas competindo pela atenção ✕ Sombras pouco legíveis ✕ Fontes de luz sem hierarquia ✕ Ambientes excessivamente uniformes ✕ Falta de percepção da direção luminosa Princípio Fundamental Luz Direcional não significa luz forte. Significa a presença clara de uma fonte luminosa dominante.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'luz_linguagem'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'luz_contrastada',
        'Luz Contrastada',
        'A Luz Contrastada caracteriza-se pela presença de diferenças marcantes entre luz e sombra. Essa relação cria uma leitura mais intensa dos volumes, das formas arquitetônicas e da profundidade da cena, utilizando o contraste como ferramenta de valorização visual.',
        'A separação entre áreas iluminadas e áreas em sombra é um elemento dominante da leitura da imagem.',
        'A Luz Contrastada caracteriza-se pela presença de diferenças marcantes entre luz e sombra. Essa relação cria uma leitura mais intensa dos volumes, das formas arquitetônicas e da profundidade da cena, utilizando o contraste como ferramenta de valorização visual.',
        'Luz Contrastada não significa imagem escura. Significa uma diferença clara e controlada entre luz e sombra.',
        'Como essa luz se comporta? A Luz Contrastada cria uma diferença perceptível entre áreas iluminadas e áreas em sombra. Essa diferença deve reforçar a leitura dos volumes e da arquitetura sem comprometer a compreensão dos elementos da cena. O contraste deve ser utilizado como ferramenta de direcionamento visual e não como efeito estético isolado. O que o observador percebe primeiro? A relação entre luz e sombra. O olhar identifica imediatamente as áreas de destaque e as áreas de repouso visual criadas pelo contraste. O que reforça essa linguagem luminosa? ✓ Sombras bem definidas ✓ Preservação de detalhes nas áreas escuras ✓ Destaque natural dos volumes ✓ Leitura clara das formas arquitetônicas ✓ Boa separação entre planos ✓ Controle da exposição O que enfraquece essa linguagem luminosa? ✕ Sombras sem informação ✕ Áreas completamente estouradas ✕ Contraste exagerado sem propósito ✕ Falta de leitura dos materiais ✕ Excesso de preenchimento luminoso ✕ Pós-produção excessivamente agressiva Princípio Fundamental Luz Contrastada não significa imagem escura. Significa uma diferença clara e controlada entre luz e sombra.',
        3
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'luz_contrastada'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Separação clara entre luz e sombra', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sombras definidas', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Forte leitura de volume', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Profundidade visual acentuada', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Destaque para formas arquitetônicas', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Hierarquia visual gerada pelo contraste', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sombras bem definidas', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Preservação de detalhes nas áreas escuras', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Destaque natural dos volumes', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Leitura clara das formas arquitetônicas', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Boa separação entre planos', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Controle da exposição', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sombras excessivamente fechadas', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Perda de informação nas áreas escuras', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Highlights estourados', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Contraste artificial sem justificativa visual', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Grandes áreas sem leitura', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de pós-produção para criar contraste', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Diferença Principal A separação entre áreas iluminadas e áreas em sombra é um elemento dominante da leitura da imagem. Descrição A Luz Contrastada caracteriza-se pela presença de diferenças marcantes entre luz e sombra. Essa relação cria uma leitura mais intensa dos volumes, das formas arquitetônicas e da profundidade da cena, utilizando o contraste como ferramenta de valorização visual. Características ✓ Separação clara entre luz e sombra ✓ Sombras definidas ✓ Forte leitura de volume ✓ Profundidade visual acentuada ✓ Destaque para formas arquitetônicas ✓ Hierarquia visual gerada pelo contraste Evitar ✕ Sombras excessivamente fechadas ✕ Perda de informação nas áreas escuras ✕ Highlights estourados ✕ Contraste artificial sem justificativa visual ✕ Grandes áreas sem leitura ✕ Excesso de pós-produção para criar contraste Diretriz Completa Como essa luz se comporta? A Luz Contrastada cria uma diferença perceptível entre áreas iluminadas e áreas em sombra. Essa diferença deve reforçar a leitura dos volumes e da arquitetura sem comprometer a compreensão dos elementos da cena. O contraste deve ser utilizado como ferramenta de direcionamento visual e não como efeito estético isolado. O que o observador percebe primeiro? A relação entre luz e sombra. O olhar identifica imediatamente as áreas de destaque e as áreas de repouso visual criadas pelo contraste. O que reforça essa linguagem luminosa? ✓ Sombras bem definidas ✓ Preservação de detalhes nas áreas escuras ✓ Destaque natural dos volumes ✓ Leitura clara das formas arquitetônicas ✓ Boa separação entre planos ✓ Controle da exposição O que enfraquece essa linguagem luminosa? ✕ Sombras sem informação ✕ Áreas completamente estouradas ✕ Contraste exagerado sem propósito ✕ Falta de leitura dos materiais ✕ Excesso de preenchimento luminoso ✕ Pós-produção excessivamente agressiva Princípio Fundamental Luz Contrastada não significa imagem escura. Significa uma diferença clara e controlada entre luz e sombra.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'luz_linguagem'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'luz_filtrada',
        'Luz Filtrada',
        'A Luz Filtrada caracteriza-se pela presença de elementos que modificam, interrompem ou modelam a passagem da luz antes que ela alcance os espaços e superfícies da cena. Essa interação gera padrões luminosos, camadas de profundidade e uma leitura mais rica da relação entre arquitetura, ambiente e iluminação.',
        'A luz interage com elementos intermediários antes de atingir o ambiente.',
        'A Luz Filtrada caracteriza-se pela presença de elementos que modificam, interrompem ou modelam a passagem da luz antes que ela alcance os espaços e superfícies da cena. Essa interação gera padrões luminosos, camadas de profundidade e uma leitura mais rica da relação entre arquitetura, ambiente e iluminação.',
        'Luz Filtrada não significa sombra decorativa. Significa que a luz revela sua relação com os elementos que atravessa.',
        'Como essa luz se comporta? A iluminação não chega diretamente ao ambiente. Antes disso, ela interage com elementos como vegetação, brises, pérgolas, esquadrias, cortinas, cobogós ou outros filtros arquitetônicos. Essa interação cria desenhos de luz e sombra que enriquecem a leitura espacial e reforçam a conexão entre a iluminação e os elementos da cena. O que o observador percebe primeiro? A interação da luz com os elementos que a filtram. O olhar identifica como a iluminação atravessa, contorna ou é modificada pela arquitetura e pelos elementos do ambiente. O que reforça essa linguagem luminosa? ✓ Vegetação próxima às áreas iluminadas ✓ Brises e elementos vazados ✓ Cortinas e tecidos translúcidos ✓ Cobogós ✓ Esquadrias marcantes ✓ Pérgolas e elementos de sombreamento ✓ Boa leitura dos desenhos projetados pela luz O que enfraquece essa linguagem luminosa? ✕ Ausência de leitura dos elementos filtrantes ✕ Sombras excessivamente duras ou artificiais ✕ Padrões confusos ✕ Excesso de filtros competindo entre si ✕ Poluição visual luminosa ✕ Filtragem sem impacto perceptível na cena Princípio Fundamental Luz Filtrada não significa sombra decorativa. Significa que a luz revela sua relação com os elementos que atravessa.',
        4
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'luz_filtrada'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Passagem da luz através de elementos intermediários', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sombras projetadas com desenho perceptível', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Padrões luminosos naturais', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Profundidade visual ampliada', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Relação evidente entre luz e arquitetura', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de tridimensionalidade', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Vegetação próxima às áreas iluminadas', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Brises e elementos vazados', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Cortinas e tecidos translúcidos', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Cobogós', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Esquadrias marcantes', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Pérgolas e elementos de sombreamento', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Boa leitura dos desenhos projetados pela luz', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sombras projetadas artificiais', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Padrões excessivamente complexos', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ruído visual causado pela filtragem', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Perda de legibilidade da arquitetura', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Elementos intermediários sem função visual clara', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de informação luminosa', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Diferença Principal A luz interage com elementos intermediários antes de atingir o ambiente. Descrição A Luz Filtrada caracteriza-se pela presença de elementos que modificam, interrompem ou modelam a passagem da luz antes que ela alcance os espaços e superfícies da cena. Essa interação gera padrões luminosos, camadas de profundidade e uma leitura mais rica da relação entre arquitetura, ambiente e iluminação. Características ✓ Passagem da luz através de elementos intermediários ✓ Sombras projetadas com desenho perceptível ✓ Padrões luminosos naturais ✓ Profundidade visual ampliada ✓ Relação evidente entre luz e arquitetura ✓ Sensação de tridimensionalidade Evitar ✕ Sombras projetadas artificiais ✕ Padrões excessivamente complexos ✕ Ruído visual causado pela filtragem ✕ Perda de legibilidade da arquitetura ✕ Elementos intermediários sem função visual clara ✕ Excesso de informação luminosa Diretriz Completa Como essa luz se comporta? A iluminação não chega diretamente ao ambiente. Antes disso, ela interage com elementos como vegetação, brises, pérgolas, esquadrias, cortinas, cobogós ou outros filtros arquitetônicos. Essa interação cria desenhos de luz e sombra que enriquecem a leitura espacial e reforçam a conexão entre a iluminação e os elementos da cena. O que o observador percebe primeiro? A interação da luz com os elementos que a filtram. O olhar identifica como a iluminação atravessa, contorna ou é modificada pela arquitetura e pelos elementos do ambiente. O que reforça essa linguagem luminosa? ✓ Vegetação próxima às áreas iluminadas ✓ Brises e elementos vazados ✓ Cortinas e tecidos translúcidos ✓ Cobogós ✓ Esquadrias marcantes ✓ Pérgolas e elementos de sombreamento ✓ Boa leitura dos desenhos projetados pela luz O que enfraquece essa linguagem luminosa? ✕ Ausência de leitura dos elementos filtrantes ✕ Sombras excessivamente duras ou artificiais ✕ Padrões confusos ✕ Excesso de filtros competindo entre si ✕ Poluição visual luminosa ✕ Filtragem sem impacto perceptível na cena Princípio Fundamental Luz Filtrada não significa sombra decorativa. Significa que a luz revela sua relação com os elementos que atravessa.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'luz_linguagem'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'luz_uniforme',
        'Luz Uniforme',
        'A Luz Envolvente caracteriza-se pela capacidade de distribuir a iluminação por todo o espaço de forma equilibrada, criando uma sensação de unidade visual. Ao invés de destacar uma fonte específica ou uma relação forte de contraste, ela busca construir uma leitura confortável e contínua do ambiente.',
        'A iluminação distribui-se de forma equilibrada pelo ambiente, permitindo uma leitura ampla e consistente do espaço.',
        'A Luz Envolvente caracteriza-se pela capacidade de distribuir a iluminação por todo o espaço de forma equilibrada, criando uma sensação de unidade visual. Ao invés de destacar uma fonte específica ou uma relação forte de contraste, ela busca construir uma leitura confortável e contínua do ambiente.',
        'Luz Uniforme não significa iluminação plana. Significa uma distribuição equilibrada da luz ao longo do ambiente.',
        'Como essa luz se comporta? A iluminação se distribui de maneira abrangente pelo ambiente, permitindo que todos os elementos da cena sejam percebidos com clareza. O objetivo não é destacar um ponto específico, mas proporcionar uma leitura confortável e completa do espaço como um todo. O que o observador percebe primeiro? A sensação de que o ambiente está plenamente iluminado e visualmente integrado. A percepção inicial não está em uma fonte de luz ou em um contraste específico, mas na leitura global do espaço. O que reforça essa linguagem luminosa? ✓ Ambientes amplos ✓ Boa distribuição luminosa ✓ Integração entre áreas iluminadas ✓ Preservação da leitura espacial ✓ Continuidade visual ✓ Controle dos contrastes extremos O que enfraquece essa linguagem luminosa? ✕ Áreas isoladas de destaque excessivo ✕ Sombras dominantes ✕ Fragmentação visual do ambiente ✕ Hierarquia luminosa agressiva ✕ Contrastes muito localizados ✕ Perda de leitura de partes do espaço Princípio Fundamental Luz Uniforme não significa iluminação plana. Significa uma distribuição equilibrada da luz ao longo do ambiente.',
        5
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'luz_uniforme'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Preenchimento amplo do espaço', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Distribuição homogênea da iluminação', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Poucas rupturas visuais', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Leitura completa do ambiente', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Baixa presença de áreas dominantes', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de continuidade espacial', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Ambientes amplos', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Boa distribuição luminosa', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Integração entre áreas iluminadas', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Preservação da leitura espacial', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Continuidade visual', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Controle dos contrastes extremos', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Áreas excessivamente escuras', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Grandes contrastes localizados', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Fontes luminosas competindo por atenção', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Hierarquias visuais excessivamente agressivas', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Perda de leitura dos espaços secundários', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes fragmentados pela iluminação', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Diferença Principal A iluminação distribui-se de forma equilibrada pelo ambiente, permitindo uma leitura ampla e consistente do espaço. Descrição A Luz Envolvente caracteriza-se pela capacidade de distribuir a iluminação por todo o espaço de forma equilibrada, criando uma sensação de unidade visual. Ao invés de destacar uma fonte específica ou uma relação forte de contraste, ela busca construir uma leitura confortável e contínua do ambiente. Características ✓ Preenchimento amplo do espaço ✓ Distribuição homogênea da iluminação ✓ Poucas rupturas visuais ✓ Leitura completa do ambiente ✓ Baixa presença de áreas dominantes ✓ Sensação de continuidade espacial Evitar ✕ Áreas excessivamente escuras ✕ Grandes contrastes localizados ✕ Fontes luminosas competindo por atenção ✕ Hierarquias visuais excessivamente agressivas ✕ Perda de leitura dos espaços secundários ✕ Ambientes fragmentados pela iluminação Diretriz Completa Como essa luz se comporta? A iluminação se distribui de maneira abrangente pelo ambiente, permitindo que todos os elementos da cena sejam percebidos com clareza. O objetivo não é destacar um ponto específico, mas proporcionar uma leitura confortável e completa do espaço como um todo. O que o observador percebe primeiro? A sensação de que o ambiente está plenamente iluminado e visualmente integrado. A percepção inicial não está em uma fonte de luz ou em um contraste específico, mas na leitura global do espaço. O que reforça essa linguagem luminosa? ✓ Ambientes amplos ✓ Boa distribuição luminosa ✓ Integração entre áreas iluminadas ✓ Preservação da leitura espacial ✓ Continuidade visual ✓ Controle dos contrastes extremos O que enfraquece essa linguagem luminosa? ✕ Áreas isoladas de destaque excessivo ✕ Sombras dominantes ✕ Fragmentação visual do ambiente ✕ Hierarquia luminosa agressiva ✕ Contrastes muito localizados ✕ Perda de leitura de partes do espaço Princípio Fundamental Luz Uniforme não significa iluminação plana. Significa uma distribuição equilibrada da luz ao longo do ambiente.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'luz_linguagem'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'luz_narrativa',
        'Luz Narrativa',
        'A Luz Narrativa caracteriza-se pelo uso consciente da iluminação para destacar elementos específicos da cena, criar hierarquias visuais e conduzir a leitura da imagem. Mais do que iluminar o ambiente, ela participa ativamente da construção da história que a imagem pretende contar.',
        'A iluminação é utilizada para direcionar a atenção do observador e reforçar a intenção da imagem.',
        'A Luz Narrativa caracteriza-se pelo uso consciente da iluminação para destacar elementos específicos da cena, criar hierarquias visuais e conduzir a leitura da imagem. Mais do que iluminar o ambiente, ela participa ativamente da construção da história que a imagem pretende contar.',
        'Luz Narrativa não significa iluminação dramática. Significa que a luz foi utilizada para contar uma história e direcionar a leitura da imagem.',
        'Como essa luz se comporta? A iluminação é organizada para apoiar uma intenção específica da imagem. As áreas iluminadas e as áreas em sombra não surgem apenas por uma consequência física da cena, mas também por uma escolha consciente que ajuda a conduzir a leitura do observador. A luz atua como uma ferramenta de comunicação. O que o observador percebe primeiro? O elemento que a imagem deseja destacar. A iluminação conduz naturalmente o olhar para os pontos mais importantes da composição. O que reforça essa linguagem luminosa? ✓ Hierarquia visual bem definida ✓ Destaque claro para os elementos principais ✓ Uso intencional das áreas iluminadas ✓ Controle do percurso visual do observador ✓ Relação coerente entre iluminação e narrativa ✓ Apoio à atmosfera proposta O que enfraquece essa linguagem luminosa? ✕ Ausência de ponto focal ✕ Destaques concorrentes ✕ Iluminação distribuída sem intenção ✕ Falta de hierarquia visual ✕ Contrastes sem propósito narrativo ✕ Direcionamento inconsistente do olhar Princípio Fundamental Luz Narrativa não significa iluminação dramática. Significa que a luz foi utilizada para contar uma história e direcionar a leitura da imagem.',
        6
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'luz_narrativa'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Hierarquia luminosa clara', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Áreas de destaque intencionais', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Direcionamento do olhar', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Apoio à narrativa da imagem', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Ênfase nos elementos mais importantes da cena', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Relação direta entre iluminação e intenção visual', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Hierarquia visual bem definida', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Destaque claro para os elementos principais', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Uso intencional das áreas iluminadas', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Controle do percurso visual do observador', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Relação coerente entre iluminação e narrativa', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Apoio à atmosfera proposta', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Destaques sem propósito', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Múltiplos pontos competindo pela atenção', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Hierarquia visual confusa', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Iluminação uniforme quando o objetivo é direcionar', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Contrastes arbitrários', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Uso da luz apenas como efeito estético', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Diferença Principal A iluminação é utilizada para direcionar a atenção do observador e reforçar a intenção da imagem. Descrição A Luz Narrativa caracteriza-se pelo uso consciente da iluminação para destacar elementos específicos da cena, criar hierarquias visuais e conduzir a leitura da imagem. Mais do que iluminar o ambiente, ela participa ativamente da construção da história que a imagem pretende contar. Características ✓ Hierarquia luminosa clara ✓ Áreas de destaque intencionais ✓ Direcionamento do olhar ✓ Apoio à narrativa da imagem ✓ Ênfase nos elementos mais importantes da cena ✓ Relação direta entre iluminação e intenção visual Evitar ✕ Destaques sem propósito ✕ Múltiplos pontos competindo pela atenção ✕ Hierarquia visual confusa ✕ Iluminação uniforme quando o objetivo é direcionar ✕ Contrastes arbitrários ✕ Uso da luz apenas como efeito estético Diretriz Completa Como essa luz se comporta? A iluminação é organizada para apoiar uma intenção específica da imagem. As áreas iluminadas e as áreas em sombra não surgem apenas por uma consequência física da cena, mas também por uma escolha consciente que ajuda a conduzir a leitura do observador. A luz atua como uma ferramenta de comunicação. O que o observador percebe primeiro? O elemento que a imagem deseja destacar. A iluminação conduz naturalmente o olhar para os pontos mais importantes da composição. O que reforça essa linguagem luminosa? ✓ Hierarquia visual bem definida ✓ Destaque claro para os elementos principais ✓ Uso intencional das áreas iluminadas ✓ Controle do percurso visual do observador ✓ Relação coerente entre iluminação e narrativa ✓ Apoio à atmosfera proposta O que enfraquece essa linguagem luminosa? ✕ Ausência de ponto focal ✕ Destaques concorrentes ✕ Iluminação distribuída sem intenção ✕ Falta de hierarquia visual ✕ Contrastes sem propósito narrativo ✕ Direcionamento inconsistente do olhar Princípio Fundamental Luz Narrativa não significa iluminação dramática. Significa que a luz foi utilizada para contar uma história e direcionar a leitura da imagem.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'arquitetura'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'contemporaneo',
        'Contemporâneo',
        'Simplicidade formal, valorização dos volumes e equilíbrio entre funcionalidade e sofisticação.',
        NULL,
        NULL,
        'Arquitetura Contemporânea não significa arquitetura minimalista. Significa que a forma, os materiais e os espaços são os protagonistas da expressão arquitetônica.',
        'O que define esse estilo? A arquitetura contemporânea busca expressar seu tempo através da simplicidade, da funcionalidade e da clareza formal. Os elementos arquitetônicos não dependem de ornamentação para gerar interesse visual. A composição dos volumes, a proporção dos espaços e a escolha dos materiais tornam-se os principais protagonistas da linguagem arquitetônica. O que deve dominar a percepção arquitetônica? A leitura dos volumes e da composição arquitetônica. O observador deve perceber uma arquitetura clara, organizada e intencional, onde cada elemento possui um papel bem definido na construção do conjunto. Quais elementos reforçam essa linguagem? ✓ Volumes bem definidos ✓ Geometrias simples e elegantes ✓ Grandes planos de vidro ✓ Integração interior e exterior ✓ Materiais aplicados com protagonismo ✓ Espaços amplos e conectados ✓ Estruturas discretamente incorporadas à arquitetura Quais elementos enfraquecem essa linguagem? ✕ Ornamentação excessiva ✕ Excesso de informação visual ✕ Linguagem decorativa dominante ✕ Fragmentação excessiva dos volumes ✕ Elementos sem coerência formal ✕ Mistura de referências conflitantes Princípio Fundamental Arquitetura Contemporânea não significa arquitetura minimalista. Significa que a forma, os materiais e os espaços são os protagonistas da expressão arquitetônica.',
        1
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'contemporaneo'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Linhas limpas e bem definidas', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Geometria clara', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Pouca ornamentação', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Integração entre espaços', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Uso expressivo dos materiais', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Relação equilibrada entre cheios e vazios', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Grandes aberturas e transparências', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Volumes bem definidos', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Geometrias simples e elegantes', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Grandes planos de vidro', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Integração interior e exterior', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Materiais aplicados com protagonismo', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Espaços amplos e conectados', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Estruturas discretamente incorporadas à arquitetura', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de elementos decorativos', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Linguagem clássica ou historicista', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Acúmulo de informações visuais', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Mistura excessiva de estilos', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Elementos sem função clara', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ornamentação como protagonista', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Essência Arquitetônica Simplicidade formal, valorização dos volumes e equilíbrio entre funcionalidade e sofisticação. Características ✓ Linhas limpas e bem definidas ✓ Geometria clara ✓ Pouca ornamentação ✓ Integração entre espaços ✓ Uso expressivo dos materiais ✓ Relação equilibrada entre cheios e vazios ✓ Grandes aberturas e transparências Evitar ✕ Excesso de elementos decorativos ✕ Linguagem clássica ou historicista ✕ Acúmulo de informações visuais ✕ Mistura excessiva de estilos ✕ Elementos sem função clara ✕ Ornamentação como protagonista Diretriz Completa O que define esse estilo? A arquitetura contemporânea busca expressar seu tempo através da simplicidade, da funcionalidade e da clareza formal. Os elementos arquitetônicos não dependem de ornamentação para gerar interesse visual. A composição dos volumes, a proporção dos espaços e a escolha dos materiais tornam-se os principais protagonistas da linguagem arquitetônica. O que deve dominar a percepção arquitetônica? A leitura dos volumes e da composição arquitetônica. O observador deve perceber uma arquitetura clara, organizada e intencional, onde cada elemento possui um papel bem definido na construção do conjunto. Quais elementos reforçam essa linguagem? ✓ Volumes bem definidos ✓ Geometrias simples e elegantes ✓ Grandes planos de vidro ✓ Integração interior e exterior ✓ Materiais aplicados com protagonismo ✓ Espaços amplos e conectados ✓ Estruturas discretamente incorporadas à arquitetura Quais elementos enfraquecem essa linguagem? ✕ Ornamentação excessiva ✕ Excesso de informação visual ✕ Linguagem decorativa dominante ✕ Fragmentação excessiva dos volumes ✕ Elementos sem coerência formal ✕ Mistura de referências conflitantes Princípio Fundamental Arquitetura Contemporânea não significa arquitetura minimalista. Significa que a forma, os materiais e os espaços são os protagonistas da expressão arquitetônica.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'arquitetura'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'japandi',
        'Japandi',
        'Simplicidade refinada, conexão com a natureza e valorização da experiência sensorial dos espaços.',
        NULL,
        NULL,
        'Japandi não significa espaço vazio. Significa que cada elemento presente possui propósito, qualidade e equilíbrio.',
        'O que define esse estilo? O Japandi combina a simplicidade funcional da tradição escandinava com a serenidade e o refinamento da estética japonesa. A arquitetura busca reduzir excessos e valorizar a relação entre espaço, luz, materiais e bem-estar. Cada elemento deve possuir um propósito claro dentro da composição. O que deve dominar a percepção arquitetônica? A sensação de equilíbrio, simplicidade e naturalidade. O observador deve perceber uma arquitetura tranquila, organizada e cuidadosamente construída, onde a qualidade dos materiais e das proporções fala mais alto do que a quantidade de elementos. Quais elementos reforçam essa linguagem? ✓ Madeira natural ✓ Pedra natural ✓ Tecidos orgânicos ✓ Linhas horizontais suaves ✓ Grandes aberturas para iluminação natural ✓ Integração com vegetação ✓ Espaços desobstruídos ✓ Mobiliário de desenho simples e refinado Quais elementos enfraquecem essa linguagem? ✕ Excesso de informação visual ✕ Decoração excessiva ✕ Mistura de muitas materialidades ✕ Elementos chamativos sem função clara ✕ Contrastes visuais agressivos ✕ Luxo ostensivo ✕ Ambientes excessivamente carregados Princípio Fundamental Japandi não significa espaço vazio. Significa que cada elemento presente possui propósito, qualidade e equilíbrio.',
        2
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'japandi'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Linhas simples e bem definidas', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Ambientes visualmente leves', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Materiais naturais como protagonistas', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Poucos elementos com alta qualidade percebida', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Paleta de cores suave e equilibrada', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Integração entre interior e exterior', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Valorização do vazio e da respiração visual', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Madeira natural', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Pedra natural', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Tecidos orgânicos', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Linhas horizontais suaves', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Grandes aberturas para iluminação natural', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Integração com vegetação', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Espaços desobstruídos', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Mobiliário de desenho simples e refinado', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de decoração', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Acúmulo de objetos', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Contrastes agressivos', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ornamentação excessiva', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Paletas excessivamente vibrantes', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Materialidades artificiais ou ostensivas', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Essência Arquitetônica Simplicidade refinada, conexão com a natureza e valorização da experiência sensorial dos espaços. Características ✓ Linhas simples e bem definidas ✓ Ambientes visualmente leves ✓ Materiais naturais como protagonistas ✓ Poucos elementos com alta qualidade percebida ✓ Paleta de cores suave e equilibrada ✓ Integração entre interior e exterior ✓ Valorização do vazio e da respiração visual Evitar ✕ Excesso de decoração ✕ Acúmulo de objetos ✕ Contrastes agressivos ✕ Ornamentação excessiva ✕ Paletas excessivamente vibrantes ✕ Materialidades artificiais ou ostensivas Diretriz Completa O que define esse estilo? O Japandi combina a simplicidade funcional da tradição escandinava com a serenidade e o refinamento da estética japonesa. A arquitetura busca reduzir excessos e valorizar a relação entre espaço, luz, materiais e bem-estar. Cada elemento deve possuir um propósito claro dentro da composição. O que deve dominar a percepção arquitetônica? A sensação de equilíbrio, simplicidade e naturalidade. O observador deve perceber uma arquitetura tranquila, organizada e cuidadosamente construída, onde a qualidade dos materiais e das proporções fala mais alto do que a quantidade de elementos. Quais elementos reforçam essa linguagem? ✓ Madeira natural ✓ Pedra natural ✓ Tecidos orgânicos ✓ Linhas horizontais suaves ✓ Grandes aberturas para iluminação natural ✓ Integração com vegetação ✓ Espaços desobstruídos ✓ Mobiliário de desenho simples e refinado Quais elementos enfraquecem essa linguagem? ✕ Excesso de informação visual ✕ Decoração excessiva ✕ Mistura de muitas materialidades ✕ Elementos chamativos sem função clara ✕ Contrastes visuais agressivos ✕ Luxo ostensivo ✕ Ambientes excessivamente carregados Princípio Fundamental Japandi não significa espaço vazio. Significa que cada elemento presente possui propósito, qualidade e equilíbrio.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'arquitetura'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'tropical',
        'Tropical',
        'Integração entre arquitetura, clima e natureza, valorizando espaços abertos, ventilação e a vida ao ar livre.',
        NULL,
        NULL,
        'Arquitetura Tropical não significa apenas presença de vegetação. Significa uma arquitetura pensada para viver em diálogo com o clima, a paisagem e os espaços externos.',
        'O que define esse estilo? A arquitetura tropical nasce da adaptação ao clima e da valorização da relação entre as pessoas, a natureza e os espaços externos. A arquitetura não busca se isolar do ambiente, mas dialogar com ele, utilizando ventilação natural, proteção solar, integração visual e espaços de convivência ao ar livre como elementos fundamentais do projeto. O que deve dominar a percepção arquitetônica? A conexão entre arquitetura e ambiente. O observador deve perceber uma arquitetura aberta, acolhedora e integrada ao contexto natural, onde os limites entre interior e exterior se tornam mais fluidos. Quais elementos reforçam essa linguagem? ✓ Grandes panos de vidro ✓ Varandas generosas ✓ Beirais marcantes ✓ Brises e elementos de sombreamento ✓ Pátios internos ✓ Integração com paisagismo ✓ Ventilação cruzada ✓ Materiais naturais adaptados ao clima Quais elementos enfraquecem essa linguagem? ✕ Ambientes excessivamente compartimentados ✕ Fachadas fechadas ✕ Pouca relação com áreas externas ✕ Excesso de barreiras visuais ✕ Dependência exclusiva de climatização artificial ✕ Ausência de espaços de transição ✕ Desconexão com o contexto natural Princípio Fundamental Arquitetura Tropical não significa apenas presença de vegetação. Significa uma arquitetura pensada para viver em diálogo com o clima, a paisagem e os espaços externos.',
        3
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'tropical'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Forte relação entre interior e exterior', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Grandes aberturas', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Ambientes ventilados', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Varandas e espaços de transição', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Proteção solar integrada à arquitetura', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Presença marcante da vegetação', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Arquitetura adaptada ao clima', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de leveza e descontração', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Grandes panos de vidro', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Varandas generosas', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Beirais marcantes', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Brises e elementos de sombreamento', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Pátios internos', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Integração com paisagismo', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Ventilação cruzada', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Materiais naturais adaptados ao clima', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes excessivamente fechados', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Fachadas herméticas', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Separação rígida entre interior e exterior', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Arquitetura excessivamente introspectiva', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Pouca relação com o entorno natural', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Espaços sem conexão climática', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Essência Arquitetônica Integração entre arquitetura, clima e natureza, valorizando espaços abertos, ventilação e a vida ao ar livre. Características ✓ Forte relação entre interior e exterior ✓ Grandes aberturas ✓ Ambientes ventilados ✓ Varandas e espaços de transição ✓ Proteção solar integrada à arquitetura ✓ Presença marcante da vegetação ✓ Arquitetura adaptada ao clima ✓ Sensação de leveza e descontração Evitar ✕ Ambientes excessivamente fechados ✕ Fachadas herméticas ✕ Separação rígida entre interior e exterior ✕ Arquitetura excessivamente introspectiva ✕ Pouca relação com o entorno natural ✕ Espaços sem conexão climática Diretriz Completa O que define esse estilo? A arquitetura tropical nasce da adaptação ao clima e da valorização da relação entre as pessoas, a natureza e os espaços externos. A arquitetura não busca se isolar do ambiente, mas dialogar com ele, utilizando ventilação natural, proteção solar, integração visual e espaços de convivência ao ar livre como elementos fundamentais do projeto. O que deve dominar a percepção arquitetônica? A conexão entre arquitetura e ambiente. O observador deve perceber uma arquitetura aberta, acolhedora e integrada ao contexto natural, onde os limites entre interior e exterior se tornam mais fluidos. Quais elementos reforçam essa linguagem? ✓ Grandes panos de vidro ✓ Varandas generosas ✓ Beirais marcantes ✓ Brises e elementos de sombreamento ✓ Pátios internos ✓ Integração com paisagismo ✓ Ventilação cruzada ✓ Materiais naturais adaptados ao clima Quais elementos enfraquecem essa linguagem? ✕ Ambientes excessivamente compartimentados ✕ Fachadas fechadas ✕ Pouca relação com áreas externas ✕ Excesso de barreiras visuais ✕ Dependência exclusiva de climatização artificial ✕ Ausência de espaços de transição ✕ Desconexão com o contexto natural Princípio Fundamental Arquitetura Tropical não significa apenas presença de vegetação. Significa uma arquitetura pensada para viver em diálogo com o clima, a paisagem e os espaços externos.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'arquitetura'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'biofilico',
        'Biofílico',
        'Fortalecer a conexão humana com a natureza através da arquitetura, promovendo bem-estar, conforto e qualidade de vida.',
        NULL,
        NULL,
        'Arquitetura Biofílica não significa ter muitas plantas. Significa utilizar a arquitetura para fortalecer a conexão entre as pessoas e a natureza.',
        'O que define esse estilo? A arquitetura biofílica busca fortalecer a conexão entre as pessoas e a natureza. Ela utiliza elementos naturais, luz, vegetação, vistas, materiais e experiências sensoriais para criar ambientes mais saudáveis, confortáveis e emocionalmente positivos. A natureza não aparece apenas como cenário, mas como parte integrante da experiência do espaço. O que deve dominar a percepção arquitetônica? A sensação de proximidade com a natureza. O observador deve perceber que a arquitetura foi concebida para aproximar as pessoas dos elementos naturais e proporcionar uma experiência mais humana e equilibrada. Quais elementos reforçam essa linguagem? ✓ Vegetação integrada à arquitetura ✓ Vistas para áreas verdes ✓ Pátios internos ✓ Jardins e espaços naturais acessíveis ✓ Iluminação natural abundante ✓ Materiais naturais ✓ Ventilação natural ✓ Presença de água como elemento sensorial ✓ Formas inspiradas em padrões naturais Quais elementos enfraquecem essa linguagem? ✕ Natureza tratada apenas como ornamentação ✕ Ambientes excessivamente artificiais ✕ Espaços fechados e isolados ✕ Ausência de vistas ou conexões naturais ✕ Dependência exclusiva de experiências artificiais ✕ Materiais que afastem a percepção de naturalidade ✕ Paisagismo desconectado da arquitetura Princípio Fundamental Arquitetura Biofílica não significa ter muitas plantas. Significa utilizar a arquitetura para fortalecer a conexão entre as pessoas e a natureza.',
        4
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'biofilico'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Integração intencional com elementos naturais', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Presença significativa de vegetação', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Forte aproveitamento da luz natural', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Conexões visuais com paisagens e áreas verdes', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Materiais naturais ou inspirados na natureza', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Ambientes que estimulam conforto e bem-estar', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Relação harmoniosa entre construído e natural', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Vegetação integrada à arquitetura', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Vistas para áreas verdes', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Pátios internos', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Jardins e espaços naturais acessíveis', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Iluminação natural abundante', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Materiais naturais', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Ventilação natural', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Presença de água como elemento sensorial', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Formas inspiradas em padrões naturais', 9
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 9
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Natureza utilizada apenas como decoração', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes desconectados do exterior', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ausência de referências naturais', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de artificialidade', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Espaços que não promovam bem-estar', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Vegetação sem propósito na experiência espacial', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Essência Arquitetônica Fortalecer a conexão humana com a natureza através da arquitetura, promovendo bem-estar, conforto e qualidade de vida. Características ✓ Integração intencional com elementos naturais ✓ Presença significativa de vegetação ✓ Forte aproveitamento da luz natural ✓ Conexões visuais com paisagens e áreas verdes ✓ Materiais naturais ou inspirados na natureza ✓ Ambientes que estimulam conforto e bem-estar ✓ Relação harmoniosa entre construído e natural Evitar ✕ Natureza utilizada apenas como decoração ✕ Ambientes desconectados do exterior ✕ Ausência de referências naturais ✕ Excesso de artificialidade ✕ Espaços que não promovam bem-estar ✕ Vegetação sem propósito na experiência espacial Diretriz Completa O que define esse estilo? A arquitetura biofílica busca fortalecer a conexão entre as pessoas e a natureza. Ela utiliza elementos naturais, luz, vegetação, vistas, materiais e experiências sensoriais para criar ambientes mais saudáveis, confortáveis e emocionalmente positivos. A natureza não aparece apenas como cenário, mas como parte integrante da experiência do espaço. O que deve dominar a percepção arquitetônica? A sensação de proximidade com a natureza. O observador deve perceber que a arquitetura foi concebida para aproximar as pessoas dos elementos naturais e proporcionar uma experiência mais humana e equilibrada. Quais elementos reforçam essa linguagem? ✓ Vegetação integrada à arquitetura ✓ Vistas para áreas verdes ✓ Pátios internos ✓ Jardins e espaços naturais acessíveis ✓ Iluminação natural abundante ✓ Materiais naturais ✓ Ventilação natural ✓ Presença de água como elemento sensorial ✓ Formas inspiradas em padrões naturais Quais elementos enfraquecem essa linguagem? ✕ Natureza tratada apenas como ornamentação ✕ Ambientes excessivamente artificiais ✕ Espaços fechados e isolados ✕ Ausência de vistas ou conexões naturais ✕ Dependência exclusiva de experiências artificiais ✕ Materiais que afastem a percepção de naturalidade ✕ Paisagismo desconectado da arquitetura Princípio Fundamental Arquitetura Biofílica não significa ter muitas plantas. Significa utilizar a arquitetura para fortalecer a conexão entre as pessoas e a natureza.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'arquitetura'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'minimalista',
        'Minimalista',
        'Redução consciente ao essencial, eliminando excessos para valorizar espaço, proporção, luz e matéria.',
        NULL,
        NULL,
        'Arquitetura Minimalista não significa arquitetura vazia. Significa que apenas o essencial permanece.',
        'O que define esse estilo? A arquitetura minimalista busca eliminar tudo aquilo que não é essencial para a experiência do espaço. O objetivo não é criar ambientes vazios, mas construir uma arquitetura onde cada elemento tenha propósito, função e significado dentro da composição. A simplicidade não é consequência da ausência de elementos, mas da clareza das decisões arquitetônicas. O que deve dominar a percepção arquitetônica? A pureza da composição espacial. O observador deve perceber a arquitetura através das proporções, da luz, dos volumes e da relação entre cheios e vazios, e não através da quantidade de elementos presentes. Quais elementos reforçam essa linguagem? ✓ Volumes puros ✓ Linhas precisas ✓ Detalhamento discreto ✓ Poucos materiais bem aplicados ✓ Espaços organizados ✓ Grandes planos contínuos ✓ Integração entre arquitetura e luz ✓ Hierarquia visual clara Quais elementos enfraquecem essa linguagem? ✕ Acúmulo de elementos ✕ Excesso de decoração ✕ Mistura excessiva de materiais ✕ Complexidade desnecessária ✕ Ruído visual ✕ Fragmentação da composição ✕ Objetos sem função clara Princípio Fundamental Arquitetura Minimalista não significa arquitetura vazia. Significa que apenas o essencial permanece.',
        5
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'minimalista'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Poucos elementos arquitetônicos', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Geometrias simples e precisas', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Ambientes visualmente limpos', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Grande valorização do espaço vazio', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Detalhamento discreto', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Materialidade controlada', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Forte atenção às proporções', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Clareza visual', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Volumes puros', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Linhas precisas', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Detalhamento discreto', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Poucos materiais bem aplicados', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Espaços organizados', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Grandes planos contínuos', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Integração entre arquitetura e luz', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Hierarquia visual clara', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de objetos', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ornamentação desnecessária', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Acúmulo de materialidades', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Poluição visual', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Elementos sem função clara', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Complexidade formal gratuita', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de informação decorativa', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Essência Arquitetônica Redução consciente ao essencial, eliminando excessos para valorizar espaço, proporção, luz e matéria. Características ✓ Poucos elementos arquitetônicos ✓ Geometrias simples e precisas ✓ Ambientes visualmente limpos ✓ Grande valorização do espaço vazio ✓ Detalhamento discreto ✓ Materialidade controlada ✓ Forte atenção às proporções ✓ Clareza visual Evitar ✕ Excesso de objetos ✕ Ornamentação desnecessária ✕ Acúmulo de materialidades ✕ Poluição visual ✕ Elementos sem função clara ✕ Complexidade formal gratuita ✕ Excesso de informação decorativa Diretriz Completa O que define esse estilo? A arquitetura minimalista busca eliminar tudo aquilo que não é essencial para a experiência do espaço. O objetivo não é criar ambientes vazios, mas construir uma arquitetura onde cada elemento tenha propósito, função e significado dentro da composição. A simplicidade não é consequência da ausência de elementos, mas da clareza das decisões arquitetônicas. O que deve dominar a percepção arquitetônica? A pureza da composição espacial. O observador deve perceber a arquitetura através das proporções, da luz, dos volumes e da relação entre cheios e vazios, e não através da quantidade de elementos presentes. Quais elementos reforçam essa linguagem? ✓ Volumes puros ✓ Linhas precisas ✓ Detalhamento discreto ✓ Poucos materiais bem aplicados ✓ Espaços organizados ✓ Grandes planos contínuos ✓ Integração entre arquitetura e luz ✓ Hierarquia visual clara Quais elementos enfraquecem essa linguagem? ✕ Acúmulo de elementos ✕ Excesso de decoração ✕ Mistura excessiva de materiais ✕ Complexidade desnecessária ✕ Ruído visual ✕ Fragmentação da composição ✕ Objetos sem função clara Princípio Fundamental Arquitetura Minimalista não significa arquitetura vazia. Significa que apenas o essencial permanece.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'arquitetura'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'escandinavo',
        'Escandinavo',
        'Funcionalidade, conforto e bem-estar através de espaços simples, acolhedores e luminosos.',
        NULL,
        NULL,
        'Arquitetura Escandinava não significa arquitetura minimalista. Significa criar conforto, funcionalidade e bem-estar através da simplicidade.',
        'O que define esse estilo? A arquitetura escandinava nasce da busca por conforto, funcionalidade e qualidade de vida. Ela procura criar espaços que sejam simples, eficientes e agradáveis para o dia a dia, valorizando a luz natural, a escala humana e a sensação de acolhimento. A beleza surge da funcionalidade bem resolvida e não da ornamentação. O que deve dominar a percepção arquitetônica? A sensação de conforto e naturalidade. O observador deve perceber um espaço leve, convidativo e humano, onde a arquitetura serve às pessoas de forma intuitiva e acolhedora. Quais elementos reforçam essa linguagem? ✓ Ambientes amplamente iluminados ✓ Grandes aberturas para luz natural ✓ Madeira clara ✓ Materiais naturais ✓ Paletas neutras e suaves ✓ Espaços organizados e funcionais ✓ Mobiliário confortável e discreto ✓ Escala doméstica acolhedora ✓ Simplicidade visual sem rigidez Quais elementos enfraquecem essa linguagem? ✕ Formalidade excessiva ✕ Luxo ostensivo ✕ Ornamentação dominante ✕ Contrastes muito agressivos ✕ Ambientes frios e impessoais ✕ Complexidade visual excessiva ✕ Materiais excessivamente sofisticados como protagonistas ✕ Espaços que priorizam impacto em detrimento do conforto Princípio Fundamental Arquitetura Escandinava não significa arquitetura minimalista. Significa criar conforto, funcionalidade e bem-estar através da simplicidade.',
        6
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'escandinavo'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Ambientes claros e iluminados', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Forte valorização da luz natural', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Funcionalidade como prioridade', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Linhas simples e honestas', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Materiais naturais', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Escala humana', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de conforto e acolhimento', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Paleta suave e equilibrada', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Ambientes amplamente iluminados', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Grandes aberturas para luz natural', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Madeira clara', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Materiais naturais', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Paletas neutras e suaves', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Espaços organizados e funcionais', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Mobiliário confortável e discreto', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Escala doméstica acolhedora', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Simplicidade visual sem rigidez', 9
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 9
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ostentação', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de ornamentação', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes visualmente pesados', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Contrastes agressivos', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Complexidade desnecessária', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Espaços excessivamente formais', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Materialidades ostensivas', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Essência Arquitetônica Funcionalidade, conforto e bem-estar através de espaços simples, acolhedores e luminosos. Características ✓ Ambientes claros e iluminados ✓ Forte valorização da luz natural ✓ Funcionalidade como prioridade ✓ Linhas simples e honestas ✓ Materiais naturais ✓ Escala humana ✓ Sensação de conforto e acolhimento ✓ Paleta suave e equilibrada Evitar ✕ Ostentação ✕ Excesso de ornamentação ✕ Ambientes visualmente pesados ✕ Contrastes agressivos ✕ Complexidade desnecessária ✕ Espaços excessivamente formais ✕ Materialidades ostensivas Diretriz Completa O que define esse estilo? A arquitetura escandinava nasce da busca por conforto, funcionalidade e qualidade de vida. Ela procura criar espaços que sejam simples, eficientes e agradáveis para o dia a dia, valorizando a luz natural, a escala humana e a sensação de acolhimento. A beleza surge da funcionalidade bem resolvida e não da ornamentação. O que deve dominar a percepção arquitetônica? A sensação de conforto e naturalidade. O observador deve perceber um espaço leve, convidativo e humano, onde a arquitetura serve às pessoas de forma intuitiva e acolhedora. Quais elementos reforçam essa linguagem? ✓ Ambientes amplamente iluminados ✓ Grandes aberturas para luz natural ✓ Madeira clara ✓ Materiais naturais ✓ Paletas neutras e suaves ✓ Espaços organizados e funcionais ✓ Mobiliário confortável e discreto ✓ Escala doméstica acolhedora ✓ Simplicidade visual sem rigidez Quais elementos enfraquecem essa linguagem? ✕ Formalidade excessiva ✕ Luxo ostensivo ✕ Ornamentação dominante ✕ Contrastes muito agressivos ✕ Ambientes frios e impessoais ✕ Complexidade visual excessiva ✕ Materiais excessivamente sofisticados como protagonistas ✕ Espaços que priorizam impacto em detrimento do conforto Princípio Fundamental Arquitetura Escandinava não significa arquitetura minimalista. Significa criar conforto, funcionalidade e bem-estar através da simplicidade.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'arquitetura'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'neoclassico',
        'Neoclássico',
        'Elegância atemporal, proporção e refinamento através da releitura contemporânea dos princípios clássicos.',
        NULL,
        NULL,
        'Arquitetura Neoclássica não significa excesso de ornamentação. Significa expressar elegância, proporção e permanência através de uma linguagem arquitetônica refinada.',
        'O que define esse estilo? A arquitetura neoclássica busca reinterpretar os princípios da arquitetura clássica de forma mais contemporânea e equilibrada. Ela valoriza proporção, simetria, ordem e refinamento, criando espaços que comunicam elegância, tradição e permanência sem necessariamente reproduzir fielmente a arquitetura histórica. A sofisticação surge da composição e das proporções antes de surgir da decoração. O que deve dominar a percepção arquitetônica? A sensação de elegância e permanência. O observador deve perceber uma arquitetura sólida, bem resolvida e atemporal, capaz de transmitir valor e prestígio através da sua composição. Quais elementos reforçam essa linguagem? ✓ Simetria ✓ Eixos visuais bem definidos ✓ Proporções equilibradas ✓ Ritmo arquitetônico organizado ✓ Materiais nobres ✓ Hierarquia espacial clara ✓ Detalhamento refinado ✓ Composição formal ✓ Sensação de monumentalidade controlada Quais elementos enfraquecem essa linguagem? ✕ Ornamentação excessiva ✕ Excesso de informação visual ✕ Linguagens conflitantes ✕ Elementos decorativos sem propósito ✕ Assimetrias não intencionais ✕ Proporções desequilibradas ✕ Formalismo exagerado ✕ Ostentação sem refinamento Princípio Fundamental Arquitetura Neoclássica não significa excesso de ornamentação. Significa expressar elegância, proporção e permanência através de uma linguagem arquitetônica refinada.',
        7
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'neoclassico'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Simetria e equilíbrio compositivo', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Proporções cuidadosamente controladas', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Linguagem arquitetônica refinada', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Hierarquia espacial clara', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de permanência', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Detalhamento sofisticado', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Forte percepção de valor e prestígio', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Composição formal e ordenada', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Simetria', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Eixos visuais bem definidos', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Proporções equilibradas', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Ritmo arquitetônico organizado', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Materiais nobres', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Hierarquia espacial clara', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Detalhamento refinado', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Composição formal', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sensação de monumentalidade controlada', 9
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 9
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de ornamentação', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Elementos clássicos utilizados sem critério', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Mistura excessiva de linguagens arquitetônicas', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Exagero decorativo', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Formalidade artificial', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Complexidade visual desnecessária', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Perda da clareza compositiva', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Essência Arquitetônica Elegância atemporal, proporção e refinamento através da releitura contemporânea dos princípios clássicos. Características ✓ Simetria e equilíbrio compositivo ✓ Proporções cuidadosamente controladas ✓ Linguagem arquitetônica refinada ✓ Hierarquia espacial clara ✓ Sensação de permanência ✓ Detalhamento sofisticado ✓ Forte percepção de valor e prestígio ✓ Composição formal e ordenada Evitar ✕ Excesso de ornamentação ✕ Elementos clássicos utilizados sem critério ✕ Mistura excessiva de linguagens arquitetônicas ✕ Exagero decorativo ✕ Formalidade artificial ✕ Complexidade visual desnecessária ✕ Perda da clareza compositiva Diretriz Completa O que define esse estilo? A arquitetura neoclássica busca reinterpretar os princípios da arquitetura clássica de forma mais contemporânea e equilibrada. Ela valoriza proporção, simetria, ordem e refinamento, criando espaços que comunicam elegância, tradição e permanência sem necessariamente reproduzir fielmente a arquitetura histórica. A sofisticação surge da composição e das proporções antes de surgir da decoração. O que deve dominar a percepção arquitetônica? A sensação de elegância e permanência. O observador deve perceber uma arquitetura sólida, bem resolvida e atemporal, capaz de transmitir valor e prestígio através da sua composição. Quais elementos reforçam essa linguagem? ✓ Simetria ✓ Eixos visuais bem definidos ✓ Proporções equilibradas ✓ Ritmo arquitetônico organizado ✓ Materiais nobres ✓ Hierarquia espacial clara ✓ Detalhamento refinado ✓ Composição formal ✓ Sensação de monumentalidade controlada Quais elementos enfraquecem essa linguagem? ✕ Ornamentação excessiva ✕ Excesso de informação visual ✕ Linguagens conflitantes ✕ Elementos decorativos sem propósito ✕ Assimetrias não intencionais ✕ Proporções desequilibradas ✕ Formalismo exagerado ✕ Ostentação sem refinamento Princípio Fundamental Arquitetura Neoclássica não significa excesso de ornamentação. Significa expressar elegância, proporção e permanência através de uma linguagem arquitetônica refinada.',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'materialidade'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'contemporaneo',
        'Contemporâneo',
        'Equilíbrio entre sofisticação, autenticidade e atualidade.',
        NULL,
        NULL,
        'Materialidade Contemporânea não significa utilizar materiais da moda. Significa combinar materiais de forma equilibrada, atual e coerente com a arquitetura.',
        'O que define essa materialidade? A materialidade contemporânea busca equilíbrio. Os materiais devem transmitir qualidade, sofisticação e atualidade sem depender de excessos visuais. Cada material deve possuir função clara dentro da composição e contribuir para uma leitura arquitetônica limpa e coerente. O que deve dominar a percepção dos materiais? A sensação de refinamento e autenticidade. O observador deve perceber materiais bem escolhidos, bem aplicados e compatíveis com a arquitetura, sem que nenhum deles domine excessivamente a cena. O que reforça essa materialidade? ✓ Materiais naturais ou tecnologicamente sofisticados ✓ Acabamentos bem executados ✓ Contrastes equilibrados ✓ Texturas controladas ✓ Combinações coerentes entre materiais ✓ Sensação de atualidade ✓ Qualidade percebida O que enfraquece essa materialidade? ✕ Excesso de protagonismo dos materiais ✕ Acúmulo de texturas concorrentes ✕ Contrastes agressivos ✕ Ornamentação excessiva ✕ Sensação de artificialidade ✕ Linguagens materiais conflitantes Princípio Fundamental Materialidade Contemporânea não significa utilizar materiais da moda. Significa combinar materiais de forma equilibrada, atual e coerente com a arquitetura.',
        1
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'contemporaneo'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Materiais honestos e bem resolvidos', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Contrastes controlados', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Acabamentos refinados', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Texturas perceptíveis sem excessos', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Equilíbrio entre natural e tecnológico', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de contemporaneidade', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Materiais naturais ou tecnologicamente sofisticados', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Acabamentos bem executados', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Contrastes equilibrados', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Texturas controladas', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Combinações coerentes entre materiais', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sensação de atualidade', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Qualidade percebida', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excessos decorativos', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Materiais excessivamente ornamentados', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Contrastes exagerados', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Artificialidade excessiva', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Acabamentos datados', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Competição visual entre materiais', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Madeiras Priorizar ✓ Claras ou médias ✓ Veios controlados ✓ Aparência natural Evitar ✕ Veios excessivamente marcados ✕ Aspecto rústico exagerado Pedras Priorizar ✓ Naturais ✓ Quartzitos ✓ Mármores equilibrados ✓ Concretos arquitetônicos Evitar ✕ Excesso de ornamentação ✕ Movimentações excessivamente dramáticas Metais Priorizar ✓ Preto fosco ✓ Inox escovado ✓ Champagne discreto Evitar ✕ Acabamentos excessivamente brilhantes Tecidos Priorizar ✓ Naturais ✓ Texturas suaves ✓ Aspecto sofisticado Evitar ✕ Padronagens dominantes Vidros Priorizar ✓ Transparentes ✓ Extra clear ✓ Controle solar discreto Evitar ✕ Reflexividade excessiva Superfícies e Pinturas Priorizar ✓ Foscas ✓ Acetinadas ✓ Microtexturas discretas Evitar ✕ Brilho excessivo Elementos Naturais Priorizar ✓ Integração equilibrada ✓ Vegetação complementar Evitar ✕ Uso meramente decorativo',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Essência Material Equilíbrio entre sofisticação, autenticidade e atualidade. Características ✓ Materiais honestos e bem resolvidos ✓ Contrastes controlados ✓ Acabamentos refinados ✓ Texturas perceptíveis sem excessos ✓ Equilíbrio entre natural e tecnológico ✓ Sensação de contemporaneidade Evitar ✕ Excessos decorativos ✕ Materiais excessivamente ornamentados ✕ Contrastes exagerados ✕ Artificialidade excessiva ✕ Acabamentos datados ✕ Competição visual entre materiais Diretriz Completa O que define essa materialidade? A materialidade contemporânea busca equilíbrio. Os materiais devem transmitir qualidade, sofisticação e atualidade sem depender de excessos visuais. Cada material deve possuir função clara dentro da composição e contribuir para uma leitura arquitetônica limpa e coerente. O que deve dominar a percepção dos materiais? A sensação de refinamento e autenticidade. O observador deve perceber materiais bem escolhidos, bem aplicados e compatíveis com a arquitetura, sem que nenhum deles domine excessivamente a cena. O que reforça essa materialidade? ✓ Materiais naturais ou tecnologicamente sofisticados ✓ Acabamentos bem executados ✓ Contrastes equilibrados ✓ Texturas controladas ✓ Combinações coerentes entre materiais ✓ Sensação de atualidade ✓ Qualidade percebida O que enfraquece essa materialidade? ✕ Excesso de protagonismo dos materiais ✕ Acúmulo de texturas concorrentes ✕ Contrastes agressivos ✕ Ornamentação excessiva ✕ Sensação de artificialidade ✕ Linguagens materiais conflitantes Princípio Fundamental Materialidade Contemporânea não significa utilizar materiais da moda. Significa combinar materiais de forma equilibrada, atual e coerente com a arquitetura. Estrutura Técnica (Interna) Madeiras Priorizar ✓ Claras ou médias ✓ Veios controlados ✓ Aparência natural Evitar ✕ Veios excessivamente marcados ✕ Aspecto rústico exagerado Pedras Priorizar ✓ Naturais ✓ Quartzitos ✓ Mármores equilibrados ✓ Concretos arquitetônicos Evitar ✕ Excesso de ornamentação ✕ Movimentações excessivamente dramáticas Metais Priorizar ✓ Preto fosco ✓ Inox escovado ✓ Champagne discreto Evitar ✕ Acabamentos excessivamente brilhantes Tecidos Priorizar ✓ Naturais ✓ Texturas suaves ✓ Aspecto sofisticado Evitar ✕ Padronagens dominantes Vidros Priorizar ✓ Transparentes ✓ Extra clear ✓ Controle solar discreto Evitar ✕ Reflexividade excessiva Superfícies e Pinturas Priorizar ✓ Foscas ✓ Acetinadas ✓ Microtexturas discretas Evitar ✕ Brilho excessivo Elementos Naturais Priorizar ✓ Integração equilibrada ✓ Vegetação complementar Evitar ✕ Uso meramente decorativo',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'materialidade'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'japandi',
        'Japandi',
        'Naturalidade, serenidade e autenticidade através de materiais honestos e sensoriais.',
        NULL,
        NULL,
        'Materialidade Japandi não significa utilizar apenas materiais naturais. Significa que qualquer material utilizado deve transmitir autenticidade, equilíbrio e conforto sensorial.',
        'O que define essa materialidade? A materialidade Japandi busca transmitir tranquilidade através da autenticidade dos materiais. Os acabamentos devem parecer naturais, honestos e agradáveis ao toque, valorizando imperfeições sutis, texturas orgânicas e uma leitura visual calma. Os materiais não devem chamar atenção individualmente, mas trabalhar em conjunto para construir uma sensação de equilíbrio. O que deve dominar a percepção dos materiais? A sensação de naturalidade e serenidade. O observador deve perceber materiais que parecem envelhecer bem, possuir textura real e contribuir para uma atmosfera tranquila e acolhedora. O que reforça essa materialidade? ✓ Materiais naturais ✓ Acabamentos foscos ✓ Texturas orgânicas ✓ Paleta equilibrada ✓ Pouco contraste ✓ Aspecto artesanal controlado ✓ Sensação tátil ✓ Elegância discreta O que enfraquece essa materialidade? ✕ Superfícies excessivamente polidas ✕ Metais protagonistas ✕ Contrastes visuais agressivos ✕ Excesso de brilho ✕ Ornamentação material ✕ Sensação de artificialidade ✕ Luxo ostensivo Princípio Fundamental Materialidade Japandi não significa utilizar apenas materiais naturais. Significa que qualquer material utilizado deve transmitir autenticidade, equilíbrio e conforto sensorial.',
        2
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'japandi'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Materiais naturais como protagonistas', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Baixo contraste entre acabamentos', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Texturas suaves e autênticas', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Acabamentos predominantemente foscos', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação tátil', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Elegância discreta', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Pouca interferência visual', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Materiais naturais', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Acabamentos foscos', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Texturas orgânicas', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Paleta equilibrada', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Pouco contraste', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Aspecto artesanal controlado', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sensação tátil', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Elegância discreta', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Brilho excessivo', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ornamentação material', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Contrastes agressivos', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Materiais ostensivos', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Artificialidade', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de acabamentos concorrendo entre si', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Madeiras Priorizar ✓ Claras ✓ Veios suaves ✓ Aspecto natural ✓ Acabamento fosco Evitar ✕ Vernizes brilhantes ✕ Tons excessivamente escuros Pedras Priorizar ✓ Limestone ✓ Travertinos suaves ✓ Pedras com pouca movimentação ✓ Aspecto natural Evitar ✕ Mármores muito ornamentados ✕ Veios dramáticos Metais Priorizar ✓ Preto fosco ✓ Champagne discreto ✓ Escovados suaves Evitar ✕ Polidos espelhados ✕ Acabamentos chamativos Tecidos Priorizar ✓ Linho ✓ Algodão ✓ Bouclé discreto ✓ Fibras naturais Evitar ✕ Brilho excessivo ✕ Padronagens marcantes Vidros Priorizar ✓ Transparentes ✓ Extra clear ✓ Leitura leve Evitar ✕ Vidros excessivamente refletivos Superfícies e Pinturas Priorizar ✓ Foscas ✓ Minerais ✓ Microtexturas suaves Evitar ✕ Alto brilho ✕ Acabamentos plásticos Elementos Naturais Priorizar ✓ Presença constante ✓ Integração orgânica ✓ Aspecto natural Evitar ✕ Uso meramente decorativo',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Essência Material Naturalidade, serenidade e autenticidade através de materiais honestos e sensoriais. Características ✓ Materiais naturais como protagonistas ✓ Baixo contraste entre acabamentos ✓ Texturas suaves e autênticas ✓ Acabamentos predominantemente foscos ✓ Sensação tátil ✓ Elegância discreta ✓ Pouca interferência visual Evitar ✕ Brilho excessivo ✕ Ornamentação material ✕ Contrastes agressivos ✕ Materiais ostensivos ✕ Artificialidade ✕ Excesso de acabamentos concorrendo entre si Diretriz Completa O que define essa materialidade? A materialidade Japandi busca transmitir tranquilidade através da autenticidade dos materiais. Os acabamentos devem parecer naturais, honestos e agradáveis ao toque, valorizando imperfeições sutis, texturas orgânicas e uma leitura visual calma. Os materiais não devem chamar atenção individualmente, mas trabalhar em conjunto para construir uma sensação de equilíbrio. O que deve dominar a percepção dos materiais? A sensação de naturalidade e serenidade. O observador deve perceber materiais que parecem envelhecer bem, possuir textura real e contribuir para uma atmosfera tranquila e acolhedora. O que reforça essa materialidade? ✓ Materiais naturais ✓ Acabamentos foscos ✓ Texturas orgânicas ✓ Paleta equilibrada ✓ Pouco contraste ✓ Aspecto artesanal controlado ✓ Sensação tátil ✓ Elegância discreta O que enfraquece essa materialidade? ✕ Superfícies excessivamente polidas ✕ Metais protagonistas ✕ Contrastes visuais agressivos ✕ Excesso de brilho ✕ Ornamentação material ✕ Sensação de artificialidade ✕ Luxo ostensivo Princípio Fundamental Materialidade Japandi não significa utilizar apenas materiais naturais. Significa que qualquer material utilizado deve transmitir autenticidade, equilíbrio e conforto sensorial. Estrutura Técnica (Interna) Madeiras Priorizar ✓ Claras ✓ Veios suaves ✓ Aspecto natural ✓ Acabamento fosco Evitar ✕ Vernizes brilhantes ✕ Tons excessivamente escuros Pedras Priorizar ✓ Limestone ✓ Travertinos suaves ✓ Pedras com pouca movimentação ✓ Aspecto natural Evitar ✕ Mármores muito ornamentados ✕ Veios dramáticos Metais Priorizar ✓ Preto fosco ✓ Champagne discreto ✓ Escovados suaves Evitar ✕ Polidos espelhados ✕ Acabamentos chamativos Tecidos Priorizar ✓ Linho ✓ Algodão ✓ Bouclé discreto ✓ Fibras naturais Evitar ✕ Brilho excessivo ✕ Padronagens marcantes Vidros Priorizar ✓ Transparentes ✓ Extra clear ✓ Leitura leve Evitar ✕ Vidros excessivamente refletivos Superfícies e Pinturas Priorizar ✓ Foscas ✓ Minerais ✓ Microtexturas suaves Evitar ✕ Alto brilho ✕ Acabamentos plásticos Elementos Naturais Priorizar ✓ Presença constante ✓ Integração orgânica ✓ Aspecto natural Evitar ✕ Uso meramente decorativo',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'materialidade'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'tropical',
        'Tropical',
        'Naturalidade vibrante, conectada ao clima, à vegetação e à riqueza sensorial dos ambientes tropicais.',
        NULL,
        NULL,
        'Materialidade Tropical não significa utilizar muitos materiais naturais. Significa utilizar materiais que reforcem a sensação de vida, clima, frescor e conexão com o ambiente ao redor.',
        'O que define essa materialidade? A materialidade tropical valoriza a riqueza natural dos materiais e sua capacidade de conectar o usuário ao clima, à vegetação e à paisagem. Os materiais devem transmitir frescor, autenticidade e conforto, criando uma sensação de proximidade com a natureza sem perder refinamento. Diferentemente do Japandi, aqui existe espaço para maior diversidade de materiais e texturas, desde que trabalhem em harmonia. O que deve dominar a percepção dos materiais? A sensação de vida, frescor e naturalidade. O observador deve perceber materiais que parecem pertencer ao lugar, dialogando com a luz, a vegetação e o clima de forma natural. O que reforça essa materialidade? ✓ Madeiras naturais ✓ Pedras expressivas ✓ Texturas perceptíveis ✓ Materiais artesanais ✓ Integração com vegetação ✓ Elementos naturais aparentes ✓ Transição fluida entre interior e exterior ✓ Sensação de leveza climática O que enfraquece essa materialidade? ✕ Ambientes excessivamente tecnológicos ✕ Acabamentos muito artificiais ✕ Excesso de superfícies reflexivas ✕ Materiais sem conexão com o contexto ✕ Frieza visual excessiva ✕ Linguagem excessivamente urbana Princípio Fundamental Materialidade Tropical não significa utilizar muitos materiais naturais. Significa utilizar materiais que reforcem a sensação de vida, clima, frescor e conexão com o ambiente ao redor.',
        3
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'tropical'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Materiais naturais expressivos', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Texturas perceptíveis', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Integração com elementos orgânicos', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de frescor', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Variedade material controlada', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Conexão entre interior e exterior', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Leitura acolhedora e viva', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Madeiras naturais', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Pedras expressivas', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Texturas perceptíveis', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Materiais artesanais', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Integração com vegetação', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Elementos naturais aparentes', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Transição fluida entre interior e exterior', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sensação de leveza climática', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Artificialidade', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Materiais excessivamente frios', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes excessivamente estéreis', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Contrastes agressivos', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de acabamento industrial', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ornamentação sem propósito', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Madeiras Priorizar ✓ Tons médios e quentes ✓ Aspecto natural ✓ Veios perceptíveis ✓ Acabamentos foscos ou acetinados Evitar ✕ Aspecto excessivamente industrial ✕ Acabamentos plásticos Pedras Priorizar ✓ Pedras naturais expressivas ✓ Quartzitos ✓ Travertinos ✓ Rochas com textura perceptível Evitar ✕ Pedras excessivamente artificiais ✕ Acabamentos excessivamente polidos Metais Priorizar ✓ Pretos foscos ✓ Bronze ✓ Champagne suave ✓ Metais discretos Evitar ✕ Acabamentos espelhados dominantes Tecidos Priorizar ✓ Linho ✓ Algodão ✓ Fibras naturais ✓ Tecidos leves Evitar ✕ Tecidos excessivamente formais ✕ Brilho excessivo Vidros Priorizar ✓ Grandes planos transparentes ✓ Integração interior-exterior ✓ Leitura leve Evitar ✕ Reflexividade excessiva Superfícies e Pinturas Priorizar ✓ Acabamentos minerais ✓ Tons naturais ✓ Microtexturas ✓ Aspecto artesanal controlado Evitar ✕ Acabamentos excessivamente industriais Elementos Naturais Priorizar ✓ Forte presença ✓ Integração arquitetônica ✓ Vegetação como parte da composição ✓ Materiais naturais aparentes Evitar ✕ Vegetação decorativa sem função espacial',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Essência Material Naturalidade vibrante, conectada ao clima, à vegetação e à riqueza sensorial dos ambientes tropicais. Características ✓ Materiais naturais expressivos ✓ Texturas perceptíveis ✓ Integração com elementos orgânicos ✓ Sensação de frescor ✓ Variedade material controlada ✓ Conexão entre interior e exterior ✓ Leitura acolhedora e viva Evitar ✕ Artificialidade ✕ Materiais excessivamente frios ✕ Ambientes excessivamente estéreis ✕ Contrastes agressivos ✕ Excesso de acabamento industrial ✕ Ornamentação sem propósito Diretriz Completa O que define essa materialidade? A materialidade tropical valoriza a riqueza natural dos materiais e sua capacidade de conectar o usuário ao clima, à vegetação e à paisagem. Os materiais devem transmitir frescor, autenticidade e conforto, criando uma sensação de proximidade com a natureza sem perder refinamento. Diferentemente do Japandi, aqui existe espaço para maior diversidade de materiais e texturas, desde que trabalhem em harmonia. O que deve dominar a percepção dos materiais? A sensação de vida, frescor e naturalidade. O observador deve perceber materiais que parecem pertencer ao lugar, dialogando com a luz, a vegetação e o clima de forma natural. O que reforça essa materialidade? ✓ Madeiras naturais ✓ Pedras expressivas ✓ Texturas perceptíveis ✓ Materiais artesanais ✓ Integração com vegetação ✓ Elementos naturais aparentes ✓ Transição fluida entre interior e exterior ✓ Sensação de leveza climática O que enfraquece essa materialidade? ✕ Ambientes excessivamente tecnológicos ✕ Acabamentos muito artificiais ✕ Excesso de superfícies reflexivas ✕ Materiais sem conexão com o contexto ✕ Frieza visual excessiva ✕ Linguagem excessivamente urbana Princípio Fundamental Materialidade Tropical não significa utilizar muitos materiais naturais. Significa utilizar materiais que reforcem a sensação de vida, clima, frescor e conexão com o ambiente ao redor. Estrutura Técnica (Interna) Madeiras Priorizar ✓ Tons médios e quentes ✓ Aspecto natural ✓ Veios perceptíveis ✓ Acabamentos foscos ou acetinados Evitar ✕ Aspecto excessivamente industrial ✕ Acabamentos plásticos Pedras Priorizar ✓ Pedras naturais expressivas ✓ Quartzitos ✓ Travertinos ✓ Rochas com textura perceptível Evitar ✕ Pedras excessivamente artificiais ✕ Acabamentos excessivamente polidos Metais Priorizar ✓ Pretos foscos ✓ Bronze ✓ Champagne suave ✓ Metais discretos Evitar ✕ Acabamentos espelhados dominantes Tecidos Priorizar ✓ Linho ✓ Algodão ✓ Fibras naturais ✓ Tecidos leves Evitar ✕ Tecidos excessivamente formais ✕ Brilho excessivo Vidros Priorizar ✓ Grandes planos transparentes ✓ Integração interior-exterior ✓ Leitura leve Evitar ✕ Reflexividade excessiva Superfícies e Pinturas Priorizar ✓ Acabamentos minerais ✓ Tons naturais ✓ Microtexturas ✓ Aspecto artesanal controlado Evitar ✕ Acabamentos excessivamente industriais Elementos Naturais Priorizar ✓ Forte presença ✓ Integração arquitetônica ✓ Vegetação como parte da composição ✓ Materiais naturais aparentes Evitar ✕ Vegetação decorativa sem função espacial',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'materialidade'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'biofilico',
        'Biofílico',
        'Materiais que fortalecem a conexão entre o ser humano e a natureza, criando uma experiência sensorial autêntica e integrada ao ambiente.',
        NULL,
        NULL,
        'Materialidade Biofílica não significa adicionar vegetação ao projeto. Significa criar uma relação genuína entre pessoas, materiais e natureza através da experiência sensorial do espaço.',
        'O que define essa materialidade? A materialidade biofílica busca aproximar as pessoas dos processos naturais através dos materiais. Mais importante do que utilizar materiais naturais é fazer com que os acabamentos transmitam autenticidade, textura, imperfeição controlada e conexão com o mundo natural. Os materiais devem ajudar a criar uma experiência sensorial rica e confortável. O que deve dominar a percepção dos materiais? A sensação de pertencimento à natureza. O observador deve sentir que os materiais fazem parte do ambiente natural e não apenas foram aplicados sobre ele. O que reforça essa materialidade? ✓ Materiais naturais aparentes ✓ Texturas orgânicas ✓ Imperfeições naturais controladas ✓ Integração com vegetação ✓ Presença de elementos vivos ✓ Materiais que transmitem conforto ✓ Sensação tátil ✓ Transição fluida entre interior e exterior O que enfraquece essa materialidade? ✕ Acabamentos excessivamente perfeitos ✕ Superfícies artificiais dominantes ✕ Frieza visual ✕ Excesso de industrialização ✕ Natureza utilizada apenas como elemento decorativo ✕ Contrastes agressivos ✕ Sensação de ambiente estéril Princípio Fundamental Materialidade Biofílica não significa adicionar vegetação ao projeto. Significa criar uma relação genuína entre pessoas, materiais e natureza através da experiência sensorial do espaço.',
        4
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'biofilico'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Materiais naturais protagonistas', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Forte presença de texturas orgânicas', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de autenticidade', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Integração entre arquitetura e natureza', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Conforto sensorial', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Materiais que envelhecem bem', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Conexão com processos naturais', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Materiais naturais aparentes', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Texturas orgânicas', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Imperfeições naturais controladas', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Integração com vegetação', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Presença de elementos vivos', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Materiais que transmitem conforto', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sensação tátil', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Transição fluida entre interior e exterior', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Artificialidade', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Materiais excessivamente sintéticos', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Acabamentos excessivamente industriais', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Superfícies frias e impessoais', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Desconexão entre interior e exterior', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Natureza utilizada apenas como decoração', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Madeiras Priorizar ✓ Aspecto natural ✓ Veios evidentes ✓ Textura perceptível ✓ Acabamentos foscos ✓ Madeira com aparência autêntica Evitar ✕ Vernizes excessivamente brilhantes ✕ Aparência plastificada Pedras Priorizar ✓ Pedras naturais ✓ Texturas orgânicas ✓ Aspecto pouco processado ✓ Leitura natural Evitar ✕ Acabamentos excessivamente artificiais ✕ Polimento excessivo Metais Priorizar ✓ Uso complementar ✓ Acabamentos discretos ✓ Aspecto envelhecido controlado Evitar ✕ Metais protagonistas ✕ Acabamentos espelhados Tecidos Priorizar ✓ Linho ✓ Algodão ✓ Lã ✓ Fibras naturais ✓ Texturas perceptíveis Evitar ✕ Tecidos sintéticos dominantes ✕ Brilho excessivo Vidros Priorizar ✓ Transparência ✓ Conexão visual com exterior ✓ Integração ambiental Evitar ✕ Barreiras visuais desnecessárias Superfícies e Pinturas Priorizar ✓ Acabamentos minerais ✓ Texturas naturais ✓ Aspecto artesanal controlado Evitar ✕ Acabamentos excessivamente industriais ✕ Superfícies impessoais Elementos Naturais Priorizar ✓ Integração estrutural com a arquitetura ✓ Vegetação funcional ✓ Água, luz natural e ventilação como parte da experiência ✓ Natureza como componente do espaço Evitar ✕ Vegetação decorativa sem propósito ✕ Natureza desconectada da arquitetura',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Essência Material Materiais que fortalecem a conexão entre o ser humano e a natureza, criando uma experiência sensorial autêntica e integrada ao ambiente. Características ✓ Materiais naturais protagonistas ✓ Forte presença de texturas orgânicas ✓ Sensação de autenticidade ✓ Integração entre arquitetura e natureza ✓ Conforto sensorial ✓ Materiais que envelhecem bem ✓ Conexão com processos naturais Evitar ✕ Artificialidade ✕ Materiais excessivamente sintéticos ✕ Acabamentos excessivamente industriais ✕ Superfícies frias e impessoais ✕ Desconexão entre interior e exterior ✕ Natureza utilizada apenas como decoração Diretriz Completa O que define essa materialidade? A materialidade biofílica busca aproximar as pessoas dos processos naturais através dos materiais. Mais importante do que utilizar materiais naturais é fazer com que os acabamentos transmitam autenticidade, textura, imperfeição controlada e conexão com o mundo natural. Os materiais devem ajudar a criar uma experiência sensorial rica e confortável. O que deve dominar a percepção dos materiais? A sensação de pertencimento à natureza. O observador deve sentir que os materiais fazem parte do ambiente natural e não apenas foram aplicados sobre ele. O que reforça essa materialidade? ✓ Materiais naturais aparentes ✓ Texturas orgânicas ✓ Imperfeições naturais controladas ✓ Integração com vegetação ✓ Presença de elementos vivos ✓ Materiais que transmitem conforto ✓ Sensação tátil ✓ Transição fluida entre interior e exterior O que enfraquece essa materialidade? ✕ Acabamentos excessivamente perfeitos ✕ Superfícies artificiais dominantes ✕ Frieza visual ✕ Excesso de industrialização ✕ Natureza utilizada apenas como elemento decorativo ✕ Contrastes agressivos ✕ Sensação de ambiente estéril Princípio Fundamental Materialidade Biofílica não significa adicionar vegetação ao projeto. Significa criar uma relação genuína entre pessoas, materiais e natureza através da experiência sensorial do espaço. Estrutura Técnica (Interna) Madeiras Priorizar ✓ Aspecto natural ✓ Veios evidentes ✓ Textura perceptível ✓ Acabamentos foscos ✓ Madeira com aparência autêntica Evitar ✕ Vernizes excessivamente brilhantes ✕ Aparência plastificada Pedras Priorizar ✓ Pedras naturais ✓ Texturas orgânicas ✓ Aspecto pouco processado ✓ Leitura natural Evitar ✕ Acabamentos excessivamente artificiais ✕ Polimento excessivo Metais Priorizar ✓ Uso complementar ✓ Acabamentos discretos ✓ Aspecto envelhecido controlado Evitar ✕ Metais protagonistas ✕ Acabamentos espelhados Tecidos Priorizar ✓ Linho ✓ Algodão ✓ Lã ✓ Fibras naturais ✓ Texturas perceptíveis Evitar ✕ Tecidos sintéticos dominantes ✕ Brilho excessivo Vidros Priorizar ✓ Transparência ✓ Conexão visual com exterior ✓ Integração ambiental Evitar ✕ Barreiras visuais desnecessárias Superfícies e Pinturas Priorizar ✓ Acabamentos minerais ✓ Texturas naturais ✓ Aspecto artesanal controlado Evitar ✕ Acabamentos excessivamente industriais ✕ Superfícies impessoais Elementos Naturais Priorizar ✓ Integração estrutural com a arquitetura ✓ Vegetação funcional ✓ Água, luz natural e ventilação como parte da experiência ✓ Natureza como componente do espaço Evitar ✕ Vegetação decorativa sem propósito ✕ Natureza desconectada da arquitetura',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'materialidade'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'minimalista',
        'Minimalista',
        'Redução consciente da complexidade material para valorizar forma, espaço, luz e proporção.',
        NULL,
        NULL,
        'Materialidade Minimalista não significa ausência de materiais. Significa utilizar apenas os materiais necessários para reforçar a clareza e a intenção da arquitetura.',
        'O que define essa materialidade? A materialidade minimalista busca reduzir distrações para permitir que arquitetura, luz, proporção e espaço sejam percebidos com clareza. Os materiais não existem para chamar atenção individualmente. Eles existem para construir uma base visual silenciosa que valoriza a composição como um todo. O que deve dominar a percepção dos materiais? A sensação de ordem, clareza e controle. O observador deve perceber uma linguagem material consistente, precisa e intencional, onde nada parece excessivo ou desnecessário. O que reforça essa materialidade? ✓ Pouca variedade material ✓ Continuidade visual ✓ Acabamentos precisos ✓ Baixo contraste ✓ Superfícies amplas ✓ Leitura limpa ✓ Texturas discretas ✓ Hierarquia visual clara O que enfraquece essa materialidade? ✕ Acúmulo de materiais ✕ Excesso de informação visual ✕ Texturas muito dominantes ✕ Ornamentação ✕ Contrastes excessivos ✕ Linguagens conflitantes ✕ Sensação de excesso Princípio Fundamental Materialidade Minimalista não significa ausência de materiais. Significa utilizar apenas os materiais necessários para reforçar a clareza e a intenção da arquitetura.',
        5
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'minimalista'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Poucos materiais', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Linguagem material controlada', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Baixo ruído visual', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Continuidade entre superfícies', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Leitura limpa', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Precisão nos acabamentos', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Protagonismo da arquitetura', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Pouca variedade material', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Continuidade visual', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Acabamentos precisos', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Baixo contraste', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Superfícies amplas', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Leitura limpa', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Texturas discretas', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Hierarquia visual clara', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de materiais', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Trocas constantes de acabamento', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Contrastes desnecessários', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ornamentação material', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Texturas excessivamente marcantes', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Complexidade visual gratuita', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Madeiras Priorizar ✓ Veios suaves ✓ Linguagem uniforme ✓ Aspecto limpo ✓ Pouca variação tonal Evitar ✕ Veios excessivamente marcados ✕ Grande diversidade de madeiras Pedras Priorizar ✓ Pouca movimentação ✓ Leitura homogênea ✓ Continuidade visual ✓ Aspecto monolítico Evitar ✕ Veios dramáticos ✕ Forte protagonismo visual Metais Priorizar ✓ Discretos ✓ Foscos ✓ Escovados ✓ Uso pontual Evitar ✕ Acabamentos chamativos ✕ Metais decorativos Tecidos Priorizar ✓ Texturas suaves ✓ Tons neutros ✓ Pouca informação visual Evitar ✕ Padronagens ✕ Texturas excessivamente expressivas Vidros Priorizar ✓ Transparência ✓ Leitura leve ✓ Continuidade espacial Evitar ✕ Reflexos protagonistas Superfícies e Pinturas Priorizar ✓ Foscas ✓ Uniformes ✓ Monocromáticas ou de baixa variação ✓ Continuidade entre planos Evitar ✕ Efeitos decorativos ✕ Texturas excessivamente marcadas Elementos Naturais Priorizar ✓ Presença pontual ✓ Integração controlada ✓ Função clara dentro da composição Evitar ✕ Uso excessivo ✕ Competição com a arquitetura',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Essência Material Redução consciente da complexidade material para valorizar forma, espaço, luz e proporção. Características ✓ Poucos materiais ✓ Linguagem material controlada ✓ Baixo ruído visual ✓ Continuidade entre superfícies ✓ Leitura limpa ✓ Precisão nos acabamentos ✓ Protagonismo da arquitetura Evitar ✕ Excesso de materiais ✕ Trocas constantes de acabamento ✕ Contrastes desnecessários ✕ Ornamentação material ✕ Texturas excessivamente marcantes ✕ Complexidade visual gratuita Diretriz Completa O que define essa materialidade? A materialidade minimalista busca reduzir distrações para permitir que arquitetura, luz, proporção e espaço sejam percebidos com clareza. Os materiais não existem para chamar atenção individualmente. Eles existem para construir uma base visual silenciosa que valoriza a composição como um todo. O que deve dominar a percepção dos materiais? A sensação de ordem, clareza e controle. O observador deve perceber uma linguagem material consistente, precisa e intencional, onde nada parece excessivo ou desnecessário. O que reforça essa materialidade? ✓ Pouca variedade material ✓ Continuidade visual ✓ Acabamentos precisos ✓ Baixo contraste ✓ Superfícies amplas ✓ Leitura limpa ✓ Texturas discretas ✓ Hierarquia visual clara O que enfraquece essa materialidade? ✕ Acúmulo de materiais ✕ Excesso de informação visual ✕ Texturas muito dominantes ✕ Ornamentação ✕ Contrastes excessivos ✕ Linguagens conflitantes ✕ Sensação de excesso Princípio Fundamental Materialidade Minimalista não significa ausência de materiais. Significa utilizar apenas os materiais necessários para reforçar a clareza e a intenção da arquitetura. Estrutura Técnica (Interna) Madeiras Priorizar ✓ Veios suaves ✓ Linguagem uniforme ✓ Aspecto limpo ✓ Pouca variação tonal Evitar ✕ Veios excessivamente marcados ✕ Grande diversidade de madeiras Pedras Priorizar ✓ Pouca movimentação ✓ Leitura homogênea ✓ Continuidade visual ✓ Aspecto monolítico Evitar ✕ Veios dramáticos ✕ Forte protagonismo visual Metais Priorizar ✓ Discretos ✓ Foscos ✓ Escovados ✓ Uso pontual Evitar ✕ Acabamentos chamativos ✕ Metais decorativos Tecidos Priorizar ✓ Texturas suaves ✓ Tons neutros ✓ Pouca informação visual Evitar ✕ Padronagens ✕ Texturas excessivamente expressivas Vidros Priorizar ✓ Transparência ✓ Leitura leve ✓ Continuidade espacial Evitar ✕ Reflexos protagonistas Superfícies e Pinturas Priorizar ✓ Foscas ✓ Uniformes ✓ Monocromáticas ou de baixa variação ✓ Continuidade entre planos Evitar ✕ Efeitos decorativos ✕ Texturas excessivamente marcadas Elementos Naturais Priorizar ✓ Presença pontual ✓ Integração controlada ✓ Função clara dentro da composição Evitar ✕ Uso excessivo ✕ Competição com a arquitetura',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'materialidade'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'escandinavo',
        'Escandinavo',
        'Leveza, conforto e luminosidade através de materiais acolhedores e visualmente suaves.',
        NULL,
        NULL,
        'Materialidade Escandinava não significa utilizar apenas materiais claros. Significa utilizar materiais que reforcem conforto, luminosidade e bem-estar cotidiano.',
        'O que define essa materialidade? A materialidade escandinava busca criar ambientes acolhedores através da simplicidade, da luz e do conforto. Os materiais devem transmitir proximidade, bem-estar e naturalidade, mantendo uma leitura clara e leve. Mais do que impressionar, os materiais devem convidar o usuário a permanecer no espaço. O que deve dominar a percepção dos materiais? A sensação de conforto e luminosidade. O observador deve perceber materiais suaves, claros e agradáveis, que contribuam para uma atmosfera leve e acolhedora. O que reforça essa materialidade? ✓ Madeiras claras ✓ Tecidos aconchegantes ✓ Tons suaves ✓ Acabamentos foscos ✓ Pouco contraste ✓ Sensação de luz natural ✓ Simplicidade visual ✓ Conforto tátil O que enfraquece essa materialidade? ✕ Materiais pesados ✕ Escuridão excessiva ✕ Contrastes agressivos ✕ Excesso de materiais nobres e ostensivos ✕ Sensação de formalidade excessiva ✕ Ambientes visualmente carregados Princípio Fundamental Materialidade Escandinava não significa utilizar apenas materiais claros. Significa utilizar materiais que reforcem conforto, luminosidade e bem-estar cotidiano.',
        6
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'escandinavo'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Materiais claros', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de luminosidade', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Texturas acolhedoras', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Linguagem simples', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Conforto visual', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Naturalidade controlada', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Ambientes leves e convidativos', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Madeiras claras', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Tecidos aconchegantes', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Tons suaves', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Acabamentos foscos', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Pouco contraste', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sensação de luz natural', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Simplicidade visual', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Conforto tátil', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Peso visual excessivo', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Materiais muito escuros', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Contrastes agressivos', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ornamentação desnecessária', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de sofisticação formal', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes visualmente densos', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Madeiras Priorizar ✓ Muito claras ✓ Veios suaves ✓ Aspecto natural ✓ Acabamentos foscos Evitar ✕ Madeiras escuras dominantes ✕ Alto contraste Pedras Priorizar ✓ Claras ✓ Pouca movimentação ✓ Aspecto leve Evitar ✕ Pedras muito dramáticas ✕ Peso visual excessivo Metais Priorizar ✓ Preto fosco discreto ✓ Branco ✓ Escovados suaves Evitar ✕ Dourados protagonistas ✕ Acabamentos ostensivos Tecidos Priorizar ✓ Linho ✓ Algodão ✓ Lã ✓ Bouclé ✓ Texturas acolhedoras Evitar ✕ Brilho excessivo ✕ Formalidade excessiva Vidros Priorizar ✓ Máxima transparência ✓ Entrada de luz natural ✓ Leitura leve Evitar ✕ Reflexividade dominante Superfícies e Pinturas Priorizar ✓ Claras ✓ Foscas ✓ Uniformes ✓ Baixa complexidade visual Evitar ✕ Excesso de textura ✕ Contrastes fortes Elementos Naturais Priorizar ✓ Presença equilibrada ✓ Sensação de vida ✓ Complemento ao conforto do ambiente Evitar ✕ Excesso de protagonismo',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Essência Material Leveza, conforto e luminosidade através de materiais acolhedores e visualmente suaves. Características ✓ Materiais claros ✓ Sensação de luminosidade ✓ Texturas acolhedoras ✓ Linguagem simples ✓ Conforto visual ✓ Naturalidade controlada ✓ Ambientes leves e convidativos Evitar ✕ Peso visual excessivo ✕ Materiais muito escuros ✕ Contrastes agressivos ✕ Ornamentação desnecessária ✕ Excesso de sofisticação formal ✕ Ambientes visualmente densos Diretriz Completa O que define essa materialidade? A materialidade escandinava busca criar ambientes acolhedores através da simplicidade, da luz e do conforto. Os materiais devem transmitir proximidade, bem-estar e naturalidade, mantendo uma leitura clara e leve. Mais do que impressionar, os materiais devem convidar o usuário a permanecer no espaço. O que deve dominar a percepção dos materiais? A sensação de conforto e luminosidade. O observador deve perceber materiais suaves, claros e agradáveis, que contribuam para uma atmosfera leve e acolhedora. O que reforça essa materialidade? ✓ Madeiras claras ✓ Tecidos aconchegantes ✓ Tons suaves ✓ Acabamentos foscos ✓ Pouco contraste ✓ Sensação de luz natural ✓ Simplicidade visual ✓ Conforto tátil O que enfraquece essa materialidade? ✕ Materiais pesados ✕ Escuridão excessiva ✕ Contrastes agressivos ✕ Excesso de materiais nobres e ostensivos ✕ Sensação de formalidade excessiva ✕ Ambientes visualmente carregados Princípio Fundamental Materialidade Escandinava não significa utilizar apenas materiais claros. Significa utilizar materiais que reforcem conforto, luminosidade e bem-estar cotidiano. Estrutura Técnica (Interna) Madeiras Priorizar ✓ Muito claras ✓ Veios suaves ✓ Aspecto natural ✓ Acabamentos foscos Evitar ✕ Madeiras escuras dominantes ✕ Alto contraste Pedras Priorizar ✓ Claras ✓ Pouca movimentação ✓ Aspecto leve Evitar ✕ Pedras muito dramáticas ✕ Peso visual excessivo Metais Priorizar ✓ Preto fosco discreto ✓ Branco ✓ Escovados suaves Evitar ✕ Dourados protagonistas ✕ Acabamentos ostensivos Tecidos Priorizar ✓ Linho ✓ Algodão ✓ Lã ✓ Bouclé ✓ Texturas acolhedoras Evitar ✕ Brilho excessivo ✕ Formalidade excessiva Vidros Priorizar ✓ Máxima transparência ✓ Entrada de luz natural ✓ Leitura leve Evitar ✕ Reflexividade dominante Superfícies e Pinturas Priorizar ✓ Claras ✓ Foscas ✓ Uniformes ✓ Baixa complexidade visual Evitar ✕ Excesso de textura ✕ Contrastes fortes Elementos Naturais Priorizar ✓ Presença equilibrada ✓ Sensação de vida ✓ Complemento ao conforto do ambiente Evitar ✕ Excesso de protagonismo',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'materialidade'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'neoclassico',
        'Neoclássico',
        'Nobreza, refinamento e permanência através de materiais sofisticados e cuidadosamente proporcionados.',
        NULL,
        NULL,
        'Materialidade Neoclássica não significa utilizar os materiais mais caros. Significa utilizar materiais capazes de transmitir elegância, refinamento e permanência através da sua composição e qualidade percebida.',
        'O que define essa materialidade? A materialidade neoclássica busca transmitir valor, elegância e permanência. Os materiais devem comunicar refinamento através da qualidade percebida, das proporções e da composição, não através do excesso. Cada acabamento deve contribuir para uma sensação de sofisticação atemporal e equilíbrio visual. O que deve dominar a percepção dos materiais? A sensação de elegância e solidez. O observador deve perceber materiais cuidadosamente selecionados, capazes de transmitir valor e refinamento sem parecerem excessivos ou artificiais. O que reforça essa materialidade? ✓ Mármores refinados ✓ Madeiras nobres ✓ Metais sofisticados ✓ Tecidos de alta qualidade ✓ Acabamentos precisos ✓ Hierarquia material bem definida ✓ Equilíbrio entre riqueza e contenção ✓ Sensação de permanência O que enfraquece essa materialidade? ✕ Excesso de informação visual ✕ Ostentação ✕ Ornamentação gratuita ✕ Mistura excessiva de materiais ✕ Contrastes sem critério ✕ Linguagens conflitantes ✕ Sofisticação artificial Princípio Fundamental Materialidade Neoclássica não significa utilizar os materiais mais caros. Significa utilizar materiais capazes de transmitir elegância, refinamento e permanência através da sua composição e qualidade percebida.',
        7
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'neoclassico'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Materiais nobres', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Refinamento perceptível', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Acabamentos sofisticados', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Elegância atemporal', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de permanência', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Detalhamento controlado', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Valor percebido elevado', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Hierarquia material clara', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Mármores refinados', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Madeiras nobres', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Metais sofisticados', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Tecidos de alta qualidade', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Acabamentos precisos', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Hierarquia material bem definida', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Equilíbrio entre riqueza e contenção', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sensação de permanência', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ostentação', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de ornamentação', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Mistura excessiva de materiais', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Brilho exagerado', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Linguagem decorativa sem propósito', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sofisticação artificial', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de protagonismo dos acabamentos', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Madeiras Priorizar ✓ Nobres ✓ Tons médios e escuros ✓ Veios elegantes ✓ Acabamentos refinados Evitar ✕ Aspecto excessivamente rústico ✕ Leitura informal Pedras Priorizar ✓ Mármores ✓ Quartzitos sofisticados ✓ Pedras de alta qualidade percebida ✓ Leitura elegante Evitar ✕ Aspecto excessivamente bruto ✕ Texturas agressivas Metais Priorizar ✓ Bronze ✓ Champagne ✓ Dourados controlados ✓ Acabamentos sofisticados Evitar ✕ Excesso de brilho ✕ Protagonismo excessivo Tecidos Priorizar ✓ Linhos refinados ✓ Veludos discretos ✓ Tecidos sofisticados ✓ Alta qualidade percebida Evitar ✕ Aspecto excessivamente casual ✕ Tecidos muito informais Vidros Priorizar ✓ Transparência elegante ✓ Integração discreta ✓ Complementação da composição Evitar ✕ Reflexividade excessiva ✕ Aspecto tecnológico dominante Superfícies e Pinturas Priorizar ✓ Acabamentos refinados ✓ Uniformidade ✓ Elegância visual ✓ Detalhamento controlado Evitar ✕ Excessos decorativos ✕ Linguagens conflitantes Elementos Naturais Priorizar ✓ Presença controlada ✓ Complementação da sofisticação do ambiente ✓ Integração elegante Evitar ✕ Aspecto excessivamente selvagem ✕ Desorganização visual',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Essência Material Nobreza, refinamento e permanência através de materiais sofisticados e cuidadosamente proporcionados. Características ✓ Materiais nobres ✓ Refinamento perceptível ✓ Acabamentos sofisticados ✓ Elegância atemporal ✓ Sensação de permanência ✓ Detalhamento controlado ✓ Valor percebido elevado ✓ Hierarquia material clara Evitar ✕ Ostentação ✕ Excesso de ornamentação ✕ Mistura excessiva de materiais ✕ Brilho exagerado ✕ Linguagem decorativa sem propósito ✕ Sofisticação artificial ✕ Excesso de protagonismo dos acabamentos Diretriz Completa O que define essa materialidade? A materialidade neoclássica busca transmitir valor, elegância e permanência. Os materiais devem comunicar refinamento através da qualidade percebida, das proporções e da composição, não através do excesso. Cada acabamento deve contribuir para uma sensação de sofisticação atemporal e equilíbrio visual. O que deve dominar a percepção dos materiais? A sensação de elegância e solidez. O observador deve perceber materiais cuidadosamente selecionados, capazes de transmitir valor e refinamento sem parecerem excessivos ou artificiais. O que reforça essa materialidade? ✓ Mármores refinados ✓ Madeiras nobres ✓ Metais sofisticados ✓ Tecidos de alta qualidade ✓ Acabamentos precisos ✓ Hierarquia material bem definida ✓ Equilíbrio entre riqueza e contenção ✓ Sensação de permanência O que enfraquece essa materialidade? ✕ Excesso de informação visual ✕ Ostentação ✕ Ornamentação gratuita ✕ Mistura excessiva de materiais ✕ Contrastes sem critério ✕ Linguagens conflitantes ✕ Sofisticação artificial Princípio Fundamental Materialidade Neoclássica não significa utilizar os materiais mais caros. Significa utilizar materiais capazes de transmitir elegância, refinamento e permanência através da sua composição e qualidade percebida. Estrutura Técnica (Interna) Madeiras Priorizar ✓ Nobres ✓ Tons médios e escuros ✓ Veios elegantes ✓ Acabamentos refinados Evitar ✕ Aspecto excessivamente rústico ✕ Leitura informal Pedras Priorizar ✓ Mármores ✓ Quartzitos sofisticados ✓ Pedras de alta qualidade percebida ✓ Leitura elegante Evitar ✕ Aspecto excessivamente bruto ✕ Texturas agressivas Metais Priorizar ✓ Bronze ✓ Champagne ✓ Dourados controlados ✓ Acabamentos sofisticados Evitar ✕ Excesso de brilho ✕ Protagonismo excessivo Tecidos Priorizar ✓ Linhos refinados ✓ Veludos discretos ✓ Tecidos sofisticados ✓ Alta qualidade percebida Evitar ✕ Aspecto excessivamente casual ✕ Tecidos muito informais Vidros Priorizar ✓ Transparência elegante ✓ Integração discreta ✓ Complementação da composição Evitar ✕ Reflexividade excessiva ✕ Aspecto tecnológico dominante Superfícies e Pinturas Priorizar ✓ Acabamentos refinados ✓ Uniformidade ✓ Elegância visual ✓ Detalhamento controlado Evitar ✕ Excessos decorativos ✕ Linguagens conflitantes Elementos Naturais Priorizar ✓ Presença controlada ✓ Complementação da sofisticação do ambiente ✓ Integração elegante Evitar ✕ Aspecto excessivamente selvagem ✕ Desorganização visual',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'lifestyle'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'ritual',
        'Ritual',
        'Momentos individuais de pausa, presença e conexão com o instante.',
        'O foco não está na atividade em si, mas na relação íntima entre a pessoa e o espaço.',
        NULL,
        'Ritual não significa ausência de pessoas. Significa que a experiência está centrada em um momento pessoal, íntimo e desacelerado.',
        'Que tipo de experiência humana estamos retratando? O Ritual representa momentos em que a pessoa desacelera e cria uma relação mais profunda com o espaço. São situações simples, cotidianas e pessoais, onde pequenas ações ganham importância. O ambiente deixa de ser apenas um cenário e passa a participar da experiência. Como queremos que a vida seja percebida neste espaço? Como uma experiência íntima e contemplativa. O observador deve sentir que aquele espaço acolhe momentos de pausa, reflexão e presença. A sensação não é de atividade, mas de permanência. O que reforça essa experiência? ✓ Livro aberto ✓ Café ou chá ✓ Jornal ou revista ✓ Música ambiente sugerida ✓ Pessoa observando a paisagem ✓ Escrita ou desenho ✓ Manta ou almofada utilizada ✓ Objetos pessoais discretos ✓ Luz suave ✓ Pequenos gestos cotidianos O que enfraquece essa experiência? ✕ Muitas pessoas ✕ Conversas em grupo ✕ Eventos sociais ✕ Movimento intenso ✕ Objetos excessivamente decorativos ✕ Sensação de pressa ✕ Fluxo constante de circulação ✕ Atividades competitivas pela atenção Quais elementos de cena normalmente ajudam a comunicar essa experiência? ● Livros ● Cadernos ● Xícaras ● Chávenas ● Óculos ● Mantas ● Flores discretas ● Velas ● Bandejas simples ● Objetos de uso pessoal Princípio Fundamental Ritual não significa ausência de pessoas. Significa que a experiência está centrada em um momento pessoal, íntimo e desacelerado.',
        1
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'ritual'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Pouco movimento', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Presença humana discreta', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Tempo desacelerado', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Objetos pessoais', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de silêncio', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Atenção aos pequenos momentos', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Conexão emocional com o ambiente', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Livro aberto', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Café ou chá', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Jornal ou revista', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Música ambiente sugerida', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Pessoa observando a paisagem', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Escrita ou desenho', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Manta ou almofada utilizada', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Objetos pessoais discretos', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Luz suave', 9
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 9
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Pequenos gestos cotidianos', 10
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 10
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Aglomerações', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Atividades coletivas', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Movimento intenso', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sensação de urgência', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de informação visual', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Narrativas muito complexas', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Presença Humana Baixa Quantidade de Pessoas 0 a 2 pessoas Tipos de Ação ✓ Ler ✓ Observar ✓ Descansar ✓ Tomar café ✓ Escrever ✓ Meditar ✓ Contemplar a vista ✓ Ouvir música Intensidade da Cena Baixa Nível de Movimento Baixo',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Experiência Principal Momentos individuais de pausa, presença e conexão com o instante. Diferença Principal O foco não está na atividade em si, mas na relação íntima entre a pessoa e o espaço. Características ✓ Pouco movimento ✓ Presença humana discreta ✓ Tempo desacelerado ✓ Objetos pessoais ✓ Sensação de silêncio ✓ Atenção aos pequenos momentos ✓ Conexão emocional com o ambiente Evitar ✕ Aglomerações ✕ Atividades coletivas ✕ Movimento intenso ✕ Sensação de urgência ✕ Excesso de informação visual ✕ Narrativas muito complexas Diretriz Completa Que tipo de experiência humana estamos retratando? O Ritual representa momentos em que a pessoa desacelera e cria uma relação mais profunda com o espaço. São situações simples, cotidianas e pessoais, onde pequenas ações ganham importância. O ambiente deixa de ser apenas um cenário e passa a participar da experiência. Como queremos que a vida seja percebida neste espaço? Como uma experiência íntima e contemplativa. O observador deve sentir que aquele espaço acolhe momentos de pausa, reflexão e presença. A sensação não é de atividade, mas de permanência. O que reforça essa experiência? ✓ Livro aberto ✓ Café ou chá ✓ Jornal ou revista ✓ Música ambiente sugerida ✓ Pessoa observando a paisagem ✓ Escrita ou desenho ✓ Manta ou almofada utilizada ✓ Objetos pessoais discretos ✓ Luz suave ✓ Pequenos gestos cotidianos O que enfraquece essa experiência? ✕ Muitas pessoas ✕ Conversas em grupo ✕ Eventos sociais ✕ Movimento intenso ✕ Objetos excessivamente decorativos ✕ Sensação de pressa ✕ Fluxo constante de circulação ✕ Atividades competitivas pela atenção Quais elementos de cena normalmente ajudam a comunicar essa experiência? ● Livros ● Cadernos ● Xícaras ● Chávenas ● Óculos ● Mantas ● Flores discretas ● Velas ● Bandejas simples ● Objetos de uso pessoal Princípio Fundamental Ritual não significa ausência de pessoas. Significa que a experiência está centrada em um momento pessoal, íntimo e desacelerado. Camada Operacional Presença Humana Baixa Quantidade de Pessoas 0 a 2 pessoas Tipos de Ação ✓ Ler ✓ Observar ✓ Descansar ✓ Tomar café ✓ Escrever ✓ Meditar ✓ Contemplar a vista ✓ Ouvir música Intensidade da Cena Baixa Nível de Movimento Baixo',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'lifestyle'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'convivencia',
        'Convivência',
        'Momentos de encontro, troca e conexão entre pessoas.',
        'O foco está nas relações humanas e na forma como o espaço aproxima as pessoas.',
        NULL,
        'Convivência não significa quantidade de pessoas. Significa que o espaço transmite relações humanas e experiências compartilhadas.',
        'Que tipo de experiência humana estamos retratando? A Convivência representa momentos onde o espaço atua como facilitador das relações humanas. Mais importante do que a atividade realizada é a sensação de proximidade, troca e compartilhamento entre as pessoas. O ambiente deve parecer vivo, acolhedor e naturalmente ocupado. Como queremos que a vida seja percebida neste espaço? Como uma experiência compartilhada. O observador deve sentir que aquele ambiente favorece encontros, conversas e momentos significativos entre pessoas. A arquitetura deixa de ser apenas um cenário e passa a ser um catalisador das relações humanas. O que reforça essa experiência? ✓ Conversas ✓ Refeições compartilhadas ✓ Família reunida ✓ Amigos interagindo ✓ Crianças utilizando o espaço ✓ Pequenos grupos ✓ Atividades colaborativas ✓ Linguagem corporal natural ✓ Objetos em uso coletivo O que enfraquece essa experiência? ✕ Pessoas isoladas ✕ Espaços excessivamente vazios ✕ Distanciamento exagerado ✕ Interações artificiais ✕ Excesso de formalidade ✕ Sensação de ambiente cenográfico ✕ Ações sem conexão entre si Quais elementos de cena normalmente ajudam a comunicar essa experiência? ● Mesa posta ● Louças em uso ● Jogos ● Flores ● Livros compartilhados ● Bandejas ● Alimentos e bebidas ● Brinquedos ● Mantas ● Objetos de uso coletivo Princípio Fundamental Convivência não significa quantidade de pessoas. Significa que o espaço transmite relações humanas e experiências compartilhadas.',
        2
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'convivencia'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Interação social', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de acolhimento', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Compartilhamento de experiências', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Presença humana perceptível', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Ambiente convidativo', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Uso coletivo do espaço', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de pertencimento', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Conversas', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Refeições compartilhadas', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Família reunida', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Amigos interagindo', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Crianças utilizando o espaço', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Pequenos grupos', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Atividades colaborativas', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Linguagem corporal natural', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Objetos em uso coletivo', 9
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 9
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Isolamento', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Distanciamento entre pessoas', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes excessivamente vazios', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Narrativas excessivamente individuais', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sensação de solidão', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Rigidez excessiva', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Presença Humana Média Quantidade de Pessoas 2 a 8 pessoas Tipos de Ação ✓ Conversar ✓ Compartilhar refeições ✓ Brincar ✓ Trabalhar em conjunto ✓ Receber convidados ✓ Descansar em grupo ✓ Celebrar pequenos momentos Intensidade da Cena Média Nível de Movimento Baixo a médio',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Experiência Principal Momentos de encontro, troca e conexão entre pessoas. Diferença Principal O foco está nas relações humanas e na forma como o espaço aproxima as pessoas. Características ✓ Interação social ✓ Sensação de acolhimento ✓ Compartilhamento de experiências ✓ Presença humana perceptível ✓ Ambiente convidativo ✓ Uso coletivo do espaço ✓ Sensação de pertencimento Evitar ✕ Isolamento ✕ Distanciamento entre pessoas ✕ Ambientes excessivamente vazios ✕ Narrativas excessivamente individuais ✕ Sensação de solidão ✕ Rigidez excessiva Diretriz Completa Que tipo de experiência humana estamos retratando? A Convivência representa momentos onde o espaço atua como facilitador das relações humanas. Mais importante do que a atividade realizada é a sensação de proximidade, troca e compartilhamento entre as pessoas. O ambiente deve parecer vivo, acolhedor e naturalmente ocupado. Como queremos que a vida seja percebida neste espaço? Como uma experiência compartilhada. O observador deve sentir que aquele ambiente favorece encontros, conversas e momentos significativos entre pessoas. A arquitetura deixa de ser apenas um cenário e passa a ser um catalisador das relações humanas. O que reforça essa experiência? ✓ Conversas ✓ Refeições compartilhadas ✓ Família reunida ✓ Amigos interagindo ✓ Crianças utilizando o espaço ✓ Pequenos grupos ✓ Atividades colaborativas ✓ Linguagem corporal natural ✓ Objetos em uso coletivo O que enfraquece essa experiência? ✕ Pessoas isoladas ✕ Espaços excessivamente vazios ✕ Distanciamento exagerado ✕ Interações artificiais ✕ Excesso de formalidade ✕ Sensação de ambiente cenográfico ✕ Ações sem conexão entre si Quais elementos de cena normalmente ajudam a comunicar essa experiência? ● Mesa posta ● Louças em uso ● Jogos ● Flores ● Livros compartilhados ● Bandejas ● Alimentos e bebidas ● Brinquedos ● Mantas ● Objetos de uso coletivo Princípio Fundamental Convivência não significa quantidade de pessoas. Significa que o espaço transmite relações humanas e experiências compartilhadas. Camada Operacional Presença Humana Média Quantidade de Pessoas 2 a 8 pessoas Tipos de Ação ✓ Conversar ✓ Compartilhar refeições ✓ Brincar ✓ Trabalhar em conjunto ✓ Receber convidados ✓ Descansar em grupo ✓ Celebrar pequenos momentos Intensidade da Cena Média Nível de Movimento Baixo a médio',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'lifestyle'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'bem_estar',
        'Bem-Estar',
        'Momentos de equilíbrio físico, mental e emocional que promovem qualidade de vida.',
        'O foco está na sensação de saúde, conforto, revitalização e conexão consigo mesmo ou com o ambiente.',
        NULL,
        'Bem-Estar não significa apenas descanso. Significa que o espaço contribui ativamente para uma vida mais saudável, equilibrada e prazerosa.',
        'Que tipo de experiência humana estamos retratando? O Bem-Estar representa momentos em que o espaço contribui para a saúde, o conforto e o equilíbrio das pessoas. A arquitetura deixa de ser apenas um abrigo e passa a participar ativamente da qualidade de vida dos usuários. Como queremos que a vida seja percebida neste espaço? Como uma experiência saudável, equilibrada e restauradora. O observador deve sentir que aquele ambiente favorece descanso, vitalidade, conforto e conexão com aquilo que faz bem. O que reforça essa experiência? ✓ Contato com a natureza ✓ Luz natural ✓ Ventilação percebida ✓ Atividades físicas leves ✓ Descanso ✓ Relaxamento ✓ Espaços de contemplação ✓ Hábitos saudáveis ✓ Sensação de liberdade ✓ Conforto emocional O que enfraquece essa experiência? ✕ Ambientes visualmente pesados ✕ Sensação de confinamento ✕ Excesso de estímulos ✕ Movimento caótico ✕ Atividades associadas a estresse ✕ Aglomerações excessivas ✕ Sensação de desgaste físico ou emocional Quais elementos de cena normalmente ajudam a comunicar essa experiência? ● Tapete de yoga ● Garrafa de água ● Chá ● Frutas ● Bicicleta ● Equipamentos esportivos discretos ● Toalhas ● Vegetação ● Almofadas ● Itens ligados a autocuidado Princípio Fundamental Bem-Estar não significa apenas descanso. Significa que o espaço contribui ativamente para uma vida mais saudável, equilibrada e prazerosa.',
        3
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'bem_estar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Qualidade de vida', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Conexão com a natureza', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de leveza', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Hábitos saudáveis', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Conforto físico e emocional', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Ambiente restaurador', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Equilíbrio entre corpo e mente', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Contato com a natureza', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Luz natural', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Ventilação percebida', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Atividades físicas leves', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Descanso', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Relaxamento', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Espaços de contemplação', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Hábitos saudáveis', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sensação de liberdade', 9
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 9
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Conforto emocional', 10
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 10
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Estresse', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de estímulos', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sensação de urgência', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes congestionados', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Atividades excessivamente competitivas', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Desconexão com o entorno', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Presença Humana Baixa a Média Quantidade de Pessoas 1 a 5 pessoas Tipos de Ação ✓ Caminhar ✓ Exercitar-se ✓ Alongar-se ✓ Relaxar ✓ Meditar ✓ Descansar ✓ Contemplar ✓ Cuidar de si ✓ Aproveitar o ambiente natural Intensidade da Cena Baixa a Média Nível de Movimento Baixo a Médio',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Experiência Principal Momentos de equilíbrio físico, mental e emocional que promovem qualidade de vida. Diferença Principal O foco está na sensação de saúde, conforto, revitalização e conexão consigo mesmo ou com o ambiente. Características ✓ Qualidade de vida ✓ Conexão com a natureza ✓ Sensação de leveza ✓ Hábitos saudáveis ✓ Conforto físico e emocional ✓ Ambiente restaurador ✓ Equilíbrio entre corpo e mente Evitar ✕ Estresse ✕ Excesso de estímulos ✕ Sensação de urgência ✕ Ambientes congestionados ✕ Atividades excessivamente competitivas ✕ Desconexão com o entorno Diretriz Completa Que tipo de experiência humana estamos retratando? O Bem-Estar representa momentos em que o espaço contribui para a saúde, o conforto e o equilíbrio das pessoas. A arquitetura deixa de ser apenas um abrigo e passa a participar ativamente da qualidade de vida dos usuários. Como queremos que a vida seja percebida neste espaço? Como uma experiência saudável, equilibrada e restauradora. O observador deve sentir que aquele ambiente favorece descanso, vitalidade, conforto e conexão com aquilo que faz bem. O que reforça essa experiência? ✓ Contato com a natureza ✓ Luz natural ✓ Ventilação percebida ✓ Atividades físicas leves ✓ Descanso ✓ Relaxamento ✓ Espaços de contemplação ✓ Hábitos saudáveis ✓ Sensação de liberdade ✓ Conforto emocional O que enfraquece essa experiência? ✕ Ambientes visualmente pesados ✕ Sensação de confinamento ✕ Excesso de estímulos ✕ Movimento caótico ✕ Atividades associadas a estresse ✕ Aglomerações excessivas ✕ Sensação de desgaste físico ou emocional Quais elementos de cena normalmente ajudam a comunicar essa experiência? ● Tapete de yoga ● Garrafa de água ● Chá ● Frutas ● Bicicleta ● Equipamentos esportivos discretos ● Toalhas ● Vegetação ● Almofadas ● Itens ligados a autocuidado Princípio Fundamental Bem-Estar não significa apenas descanso. Significa que o espaço contribui ativamente para uma vida mais saudável, equilibrada e prazerosa. Camada Operacional Presença Humana Baixa a Média Quantidade de Pessoas 1 a 5 pessoas Tipos de Ação ✓ Caminhar ✓ Exercitar-se ✓ Alongar-se ✓ Relaxar ✓ Meditar ✓ Descansar ✓ Contemplar ✓ Cuidar de si ✓ Aproveitar o ambiente natural Intensidade da Cena Baixa a Média Nível de Movimento Baixo a Médio',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'lifestyle'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'movimento',
        'Movimento',
        'Momentos de atividade, deslocamento e energia que tornam o espaço vivo e dinâmico.',
        'O foco está na ação e na sensação de fluxo constante dentro do ambiente.',
        NULL,
        'Movimento não significa aglomeração. Significa que o espaço transmite atividade, fluxo e energia através da forma como é ocupado.',
        'Que tipo de experiência humana estamos retratando? O Movimento representa ambientes onde a atividade humana é parte fundamental da experiência. O espaço transmite energia, circulação e vitalidade, sugerindo que algo está constantemente acontecendo. A arquitetura não é apenas observada. Ela é utilizada. Como queremos que a vida seja percebida neste espaço? Como uma experiência ativa e dinâmica. O observador deve sentir que aquele ambiente possui ritmo, uso constante e participação humana. Existe a percepção de que a vida está acontecendo naquele exato momento. O que reforça essa experiência? ✓ Pessoas caminhando ✓ Fluxos de circulação ✓ Bicicletas ✓ Corrida leve ✓ Deslocamento urbano ✓ Atividades esportivas ✓ Uso simultâneo dos espaços ✓ Interações rápidas ✓ Movimento sugerido ✓ Ocupação natural O que enfraquece essa experiência? ✕ Ambientes vazios ✕ Pessoas estáticas sem propósito ✕ Sensação de espera ✕ Pouca utilização dos espaços ✕ Narrativas excessivamente contemplativas ✕ Falta de fluxo ✕ Composição excessivamente estática Quais elementos de cena normalmente ajudam a comunicar essa experiência? ● Bicicletas ● Patinetes ● Mochilas ● Garrafas de água ● Equipamentos esportivos ● Veículos em movimento ● Pessoas caminhando ● Pessoas correndo ● Objetos em uso ● Elementos urbanos Princípio Fundamental Movimento não significa aglomeração. Significa que o espaço transmite atividade, fluxo e energia através da forma como é ocupado.',
        4
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'movimento'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Energia', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Atividade', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Fluxo de pessoas', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Dinamismo', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de cidade viva', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Uso ativo dos espaços', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Ritmo acelerado', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Interação contínua com o ambiente', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Pessoas caminhando', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Fluxos de circulação', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Bicicletas', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Corrida leve', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Deslocamento urbano', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Atividades esportivas', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Uso simultâneo dos espaços', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Interações rápidas', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Movimento sugerido', 9
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 9
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Ocupação natural', 10
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 10
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Imobilidade excessiva', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sensação de vazio', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes contemplativos', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Narrativas excessivamente introspectivas', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ausência de atividade', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Cenários estáticos', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Presença Humana Média a Alta Quantidade de Pessoas 3 a 20+ pessoas Tipos de Ação ✓ Caminhar ✓ Correr ✓ Pedalar ✓ Circular ✓ Exercitar-se ✓ Deslocar-se ✓ Utilizar áreas comuns ✓ Interagir rapidamente Intensidade da Cena Média a Alta Nível de Movimento Alto',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Baixo a Médio Movimento Versão Resumida Experiência Principal Momentos de atividade, deslocamento e energia que tornam o espaço vivo e dinâmico. Diferença Principal O foco está na ação e na sensação de fluxo constante dentro do ambiente. Características ✓ Energia ✓ Atividade ✓ Fluxo de pessoas ✓ Dinamismo ✓ Sensação de cidade viva ✓ Uso ativo dos espaços ✓ Ritmo acelerado ✓ Interação contínua com o ambiente Evitar ✕ Imobilidade excessiva ✕ Sensação de vazio ✕ Ambientes contemplativos ✕ Narrativas excessivamente introspectivas ✕ Ausência de atividade ✕ Cenários estáticos Diretriz Completa Que tipo de experiência humana estamos retratando? O Movimento representa ambientes onde a atividade humana é parte fundamental da experiência. O espaço transmite energia, circulação e vitalidade, sugerindo que algo está constantemente acontecendo. A arquitetura não é apenas observada. Ela é utilizada. Como queremos que a vida seja percebida neste espaço? Como uma experiência ativa e dinâmica. O observador deve sentir que aquele ambiente possui ritmo, uso constante e participação humana. Existe a percepção de que a vida está acontecendo naquele exato momento. O que reforça essa experiência? ✓ Pessoas caminhando ✓ Fluxos de circulação ✓ Bicicletas ✓ Corrida leve ✓ Deslocamento urbano ✓ Atividades esportivas ✓ Uso simultâneo dos espaços ✓ Interações rápidas ✓ Movimento sugerido ✓ Ocupação natural O que enfraquece essa experiência? ✕ Ambientes vazios ✕ Pessoas estáticas sem propósito ✕ Sensação de espera ✕ Pouca utilização dos espaços ✕ Narrativas excessivamente contemplativas ✕ Falta de fluxo ✕ Composição excessivamente estática Quais elementos de cena normalmente ajudam a comunicar essa experiência? ● Bicicletas ● Patinetes ● Mochilas ● Garrafas de água ● Equipamentos esportivos ● Veículos em movimento ● Pessoas caminhando ● Pessoas correndo ● Objetos em uso ● Elementos urbanos Princípio Fundamental Movimento não significa aglomeração. Significa que o espaço transmite atividade, fluxo e energia através da forma como é ocupado. Camada Operacional Presença Humana Média a Alta Quantidade de Pessoas 3 a 20+ pessoas Tipos de Ação ✓ Caminhar ✓ Correr ✓ Pedalar ✓ Circular ✓ Exercitar-se ✓ Deslocar-se ✓ Utilizar áreas comuns ✓ Interagir rapidamente Intensidade da Cena Média a Alta Nível de Movimento Alto',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'lifestyle'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'celebracao',
        'Celebração',
        'Momentos especiais compartilhados que transformam o espaço em palco para experiências memoráveis.',
        'O foco está em ocasiões que fogem da rotina e criam sensação de exclusividade, encontro e celebração.',
        NULL,
        'Celebração não significa festa. Significa que o espaço está sendo utilizado para criar experiências especiais e memoráveis.',
        'Que tipo de experiência humana estamos retratando? A Celebração representa momentos que possuem significado especial para as pessoas. São situações em que o espaço deixa de ser apenas um local de permanência e passa a ser palco para encontros, conquistas, experiências e memórias. A arquitetura contribui para tornar aquele momento mais marcante. Como queremos que a vida seja percebida neste espaço? Como uma experiência especial e memorável. O observador deve imaginar pessoas aproveitando momentos únicos, compartilhando experiências e criando lembranças positivas naquele ambiente. O que reforça essa experiência? ✓ Reuniões sociais ✓ Jantares ✓ Brindes ✓ Encontros especiais ✓ Recepção de convidados ✓ Uso coletivo qualificado ✓ Linguagem corporal positiva ✓ Sensação de ocasião ✓ Experiência premium ✓ Pequenos momentos de celebração O que enfraquece essa experiência? ✕ Solidão excessiva ✕ Espaços vazios ✕ Falta de interação ✕ Uso excessivamente cotidiano ✕ Formalidade rígida ✕ Aglomerações desorganizadas ✕ Sensação de ambiente sem propósito Quais elementos de cena normalmente ajudam a comunicar essa experiência? ● Taças ● Vinhos ● Drinks ● Mesa posta ● Flores ● Velas ● Aperitivos ● Bandejas ● Decoração de recepção ● Objetos ligados à hospitalidade Princípio Fundamental Celebração não significa festa. Significa que o espaço está sendo utilizado para criar experiências especiais e memoráveis.',
        5
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'celebracao'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Experiência especial', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sofisticação', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Compartilhamento', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de ocasião', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Presença humana intencional', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Memórias sendo construídas', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Ambiente preparado para receber', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Energia positiva', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Reuniões sociais', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Jantares', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Brindes', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Encontros especiais', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Recepção de convidados', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Uso coletivo qualificado', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Linguagem corporal positiva', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sensação de ocasião', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Experiência premium', 9
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 9
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Pequenos momentos de celebração', 10
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 10
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Cotidiano excessivamente comum', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes vazios', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sensação de rotina', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Formalidade excessiva', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Aglomerações caóticas', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de informação visual', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Interações artificiais', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Presença Humana Média Quantidade de Pessoas 2 a 15 pessoas Tipos de Ação ✓ Brindar ✓ Conversar ✓ Receber convidados ✓ Compartilhar refeições ✓ Comemorar ✓ Socializar ✓ Aproveitar o espaço ✓ Participar de experiências coletivas Intensidade da Cena Média Nível de Movimento Médio',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Experiência Principal Momentos especiais compartilhados que transformam o espaço em palco para experiências memoráveis. Diferença Principal O foco está em ocasiões que fogem da rotina e criam sensação de exclusividade, encontro e celebração. Características ✓ Experiência especial ✓ Sofisticação ✓ Compartilhamento ✓ Sensação de ocasião ✓ Presença humana intencional ✓ Memórias sendo construídas ✓ Ambiente preparado para receber ✓ Energia positiva Evitar ✕ Cotidiano excessivamente comum ✕ Ambientes vazios ✕ Sensação de rotina ✕ Formalidade excessiva ✕ Aglomerações caóticas ✕ Excesso de informação visual ✕ Interações artificiais Diretriz Completa Que tipo de experiência humana estamos retratando? A Celebração representa momentos que possuem significado especial para as pessoas. São situações em que o espaço deixa de ser apenas um local de permanência e passa a ser palco para encontros, conquistas, experiências e memórias. A arquitetura contribui para tornar aquele momento mais marcante. Como queremos que a vida seja percebida neste espaço? Como uma experiência especial e memorável. O observador deve imaginar pessoas aproveitando momentos únicos, compartilhando experiências e criando lembranças positivas naquele ambiente. O que reforça essa experiência? ✓ Reuniões sociais ✓ Jantares ✓ Brindes ✓ Encontros especiais ✓ Recepção de convidados ✓ Uso coletivo qualificado ✓ Linguagem corporal positiva ✓ Sensação de ocasião ✓ Experiência premium ✓ Pequenos momentos de celebração O que enfraquece essa experiência? ✕ Solidão excessiva ✕ Espaços vazios ✕ Falta de interação ✕ Uso excessivamente cotidiano ✕ Formalidade rígida ✕ Aglomerações desorganizadas ✕ Sensação de ambiente sem propósito Quais elementos de cena normalmente ajudam a comunicar essa experiência? ● Taças ● Vinhos ● Drinks ● Mesa posta ● Flores ● Velas ● Aperitivos ● Bandejas ● Decoração de recepção ● Objetos ligados à hospitalidade Princípio Fundamental Celebração não significa festa. Significa que o espaço está sendo utilizado para criar experiências especiais e memoráveis. Camada Operacional Presença Humana Média Quantidade de Pessoas 2 a 15 pessoas Tipos de Ação ✓ Brindar ✓ Conversar ✓ Receber convidados ✓ Compartilhar refeições ✓ Comemorar ✓ Socializar ✓ Aproveitar o espaço ✓ Participar de experiências coletivas Intensidade da Cena Média Nível de Movimento Médio',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'lifestyle'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'descoberta',
        'Descoberta',
        'Momentos de exploração, curiosidade e conexão com o espaço.',
        'O foco está na experiência de conhecer, percorrer e revelar o ambiente, despertando interesse e encantamento.',
        NULL,
        'Descoberta não significa movimento. Significa que o espaço desperta curiosidade e convida à exploração.',
        'Que tipo de experiência humana estamos retratando? A Descoberta representa momentos em que o usuário explora e se conecta com o ambiente. A experiência não está em permanecer em um único ponto, mas em percorrer, observar e revelar diferentes aspectos do espaço. O ambiente desperta curiosidade. Como queremos que a vida seja percebida neste espaço? Como uma experiência de exploração e encantamento. O observador deve sentir vontade de percorrer o ambiente, descobrir novos enquadramentos, novas vistas e novas experiências. A arquitetura se transforma em parte ativa da narrativa. O que reforça essa experiência? ✓ Pessoas caminhando pelo espaço ✓ Observação de vistas ✓ Percursos arquitetônicos ✓ Conexão entre ambientes ✓ Áreas de contemplação ✓ Pontos de interesse visual ✓ Mudanças de perspectiva ✓ Ambientes que convidam à exploração O que enfraquece essa experiência? ✕ Espaços excessivamente estáticos ✕ Falta de hierarquia visual ✕ Ambientes monótonos ✕ Ausência de percurso ✕ Falta de interação com a arquitetura ✕ Sensação de ambiente já totalmente revelado Quais elementos de cena normalmente ajudam a comunicar essa experiência? ● Pessoas caminhando ● Pessoas observando vistas ● Binóculos ● Mapas ● Bicicletas em percurso ● Passarelas ● Escadarias ● Mirantes ● Percursos paisagísticos ● Elementos de orientação Princípio Fundamental Descoberta não significa movimento. Significa que o espaço desperta curiosidade e convida à exploração.',
        6
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'descoberta'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Curiosidade', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Exploração', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de descoberta', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Interação com o espaço', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Percurso', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Encantamento', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Observação', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Experiência arquitetônica', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Convite à permanência', 9
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 9
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Pessoas caminhando pelo espaço', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Observação de vistas', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Percursos arquitetônicos', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Conexão entre ambientes', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Áreas de contemplação', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Pontos de interesse visual', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Mudanças de perspectiva', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Ambientes que convidam à exploração', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes excessivamente previsíveis', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Falta de pontos de interesse', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sensação de monotonia', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Espaços sem profundidade narrativa', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Experiências passivas', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ausência de interação com o ambiente', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Presença Humana Baixa a Média Quantidade de Pessoas 1 a 6 pessoas Tipos de Ação ✓ Caminhar ✓ Explorar ✓ Observar ✓ Descobrir ✓ Percorrer ✓ Contemplar vistas ✓ Interagir com a arquitetura Intensidade da Cena Baixa a Média Nível de Movimento Baixo a Médio',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Experiência Principal Momentos de exploração, curiosidade e conexão com o espaço. Diferença Principal O foco está na experiência de conhecer, percorrer e revelar o ambiente, despertando interesse e encantamento. Características ✓ Curiosidade ✓ Exploração ✓ Sensação de descoberta ✓ Interação com o espaço ✓ Percurso ✓ Encantamento ✓ Observação ✓ Experiência arquitetônica ✓ Convite à permanência Evitar ✕ Ambientes excessivamente previsíveis ✕ Falta de pontos de interesse ✕ Sensação de monotonia ✕ Espaços sem profundidade narrativa ✕ Experiências passivas ✕ Ausência de interação com o ambiente Diretriz Completa Que tipo de experiência humana estamos retratando? A Descoberta representa momentos em que o usuário explora e se conecta com o ambiente. A experiência não está em permanecer em um único ponto, mas em percorrer, observar e revelar diferentes aspectos do espaço. O ambiente desperta curiosidade. Como queremos que a vida seja percebida neste espaço? Como uma experiência de exploração e encantamento. O observador deve sentir vontade de percorrer o ambiente, descobrir novos enquadramentos, novas vistas e novas experiências. A arquitetura se transforma em parte ativa da narrativa. O que reforça essa experiência? ✓ Pessoas caminhando pelo espaço ✓ Observação de vistas ✓ Percursos arquitetônicos ✓ Conexão entre ambientes ✓ Áreas de contemplação ✓ Pontos de interesse visual ✓ Mudanças de perspectiva ✓ Ambientes que convidam à exploração O que enfraquece essa experiência? ✕ Espaços excessivamente estáticos ✕ Falta de hierarquia visual ✕ Ambientes monótonos ✕ Ausência de percurso ✕ Falta de interação com a arquitetura ✕ Sensação de ambiente já totalmente revelado Quais elementos de cena normalmente ajudam a comunicar essa experiência? ● Pessoas caminhando ● Pessoas observando vistas ● Binóculos ● Mapas ● Bicicletas em percurso ● Passarelas ● Escadarias ● Mirantes ● Percursos paisagísticos ● Elementos de orientação Princípio Fundamental Descoberta não significa movimento. Significa que o espaço desperta curiosidade e convida à exploração. Camada Operacional Presença Humana Baixa a Média Quantidade de Pessoas 1 a 6 pessoas Tipos de Ação ✓ Caminhar ✓ Explorar ✓ Observar ✓ Descobrir ✓ Percorrer ✓ Contemplar vistas ✓ Interagir com a arquitetura Intensidade da Cena Baixa a Média Nível de Movimento Baixo a Médio',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'composicao'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'focada',
        'Focada',
        'Um único elemento domina a leitura da imagem.',
        'Toda a composição trabalha para conduzir rapidamente o olhar até um protagonista claramente definido.',
        NULL,
        'Se o observador não consegue identificar rapidamente o protagonista da imagem, a composição não está cumprindo seu papel.',
        'Como queremos que o observador percorra a imagem? O observador deve identificar rapidamente o elemento principal da cena. Após reconhecer esse protagonista, pode explorar os elementos secundários que complementam a narrativa. A leitura acontece de forma direta e controlada. O que deve dominar a percepção da imagem? Um único elemento. Pode ser: ● uma varanda; ● uma piscina; ● uma fachada; ● uma vista; ● um ambiente específico; ● um detalhe arquitetônico. O importante é que não exista dúvida sobre qual é o protagonista. O que reforça essa composição? ✓ Contraste visual ✓ Direcionamento da luz ✓ Enquadramento estratégico ✓ Linhas de condução ✓ Profundidade controlada ✓ Espaço negativo ✓ Simplificação dos elementos secundários ✓ Separação clara entre protagonista e apoio O que enfraquece essa composição? ✕ Muitos pontos de interesse ✕ Excesso de decoração ✕ Corte inadequado do protagonista ✕ Elementos chamando mais atenção que o foco principal ✕ Lifestyle excessivamente dominante ✕ Hierarquia visual confusa ✕ Falta de contraste entre protagonista e contexto Princípio Fundamental Se o observador não consegue identificar rapidamente o protagonista da imagem, a composição não está cumprindo seu papel.',
        1
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'focada'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Protagonista evidente', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Leitura rápida', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Hierarquia visual clara', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Poucos elementos competindo pela atenção', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Forte direcionamento do olhar', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Comunicação objetiva', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Clareza narrativa', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Contraste visual', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Direcionamento da luz', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Enquadramento estratégico', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Linhas de condução', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Profundidade controlada', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Espaço negativo', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Simplificação dos elementos secundários', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Separação clara entre protagonista e apoio', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Múltiplos protagonistas', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de informação visual', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Elementos competindo pela atenção', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Falta de hierarquia', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ruído visual', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambiguidade sobre o foco da imagem', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Protagonismo Alto Número de Pontos de Interesse 1 principal até 2 secundários Velocidade de Leitura Rápida Relação Arquitetura × Lifestyle Arquitetura dominante ou',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Hierarquia Principal Um único elemento domina a leitura da imagem. Diferença Principal Toda a composição trabalha para conduzir rapidamente o olhar até um protagonista claramente definido. Características ✓ Protagonista evidente ✓ Leitura rápida ✓ Hierarquia visual clara ✓ Poucos elementos competindo pela atenção ✓ Forte direcionamento do olhar ✓ Comunicação objetiva ✓ Clareza narrativa Evitar ✕ Múltiplos protagonistas ✕ Excesso de informação visual ✕ Elementos competindo pela atenção ✕ Falta de hierarquia ✕ Ruído visual ✕ Ambiguidade sobre o foco da imagem Diretriz Completa Como queremos que o observador percorra a imagem? O observador deve identificar rapidamente o elemento principal da cena. Após reconhecer esse protagonista, pode explorar os elementos secundários que complementam a narrativa. A leitura acontece de forma direta e controlada. O que deve dominar a percepção da imagem? Um único elemento. Pode ser: ● uma varanda; ● uma piscina; ● uma fachada; ● uma vista; ● um ambiente específico; ● um detalhe arquitetônico. O importante é que não exista dúvida sobre qual é o protagonista. O que reforça essa composição? ✓ Contraste visual ✓ Direcionamento da luz ✓ Enquadramento estratégico ✓ Linhas de condução ✓ Profundidade controlada ✓ Espaço negativo ✓ Simplificação dos elementos secundários ✓ Separação clara entre protagonista e apoio O que enfraquece essa composição? ✕ Muitos pontos de interesse ✕ Excesso de decoração ✕ Corte inadequado do protagonista ✕ Elementos chamando mais atenção que o foco principal ✕ Lifestyle excessivamente dominante ✕ Hierarquia visual confusa ✕ Falta de contraste entre protagonista e contexto Princípio Fundamental Se o observador não consegue identificar rapidamente o protagonista da imagem, a composição não está cumprindo seu papel. Camada Operacional Protagonismo Alto Número de Pontos de Interesse 1 principal até 2 secundários Velocidade de Leitura Rápida Relação Arquitetura × Lifestyle Arquitetura dominante ou',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'composicao'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'equilibrada',
        'Equilibrada',
        'A atenção é distribuída de forma harmoniosa entre os principais elementos da cena.',
        'Não existe um único protagonista absoluto. A força da imagem está no equilíbrio entre os elementos que compõem o espaço.',
        NULL,
        'O objetivo não é destacar um elemento específico. O objetivo é valorizar a experiência completa do espaço.',
        'Como queremos que o observador percorra a imagem? O olhar deve explorar a cena de forma natural e confortável. Não existe urgência para identificar um protagonista específico. A percepção acontece através do conjunto. O observador compreende primeiro o espaço como um todo e, depois, seus elementos individuais. O que deve dominar a percepção da imagem? O ambiente. A força da composição não está em um único objeto, mas na relação equilibrada entre arquitetura, materiais, iluminação e lifestyle. O que reforça essa composição? ✓ Distribuição uniforme dos pesos visuais ✓ Organização clara dos elementos ✓ Simetria parcial ou total ✓ Proporções equilibradas ✓ Controle do contraste ✓ Harmonia entre luz e materiais ✓ Ocupação coerente do espaço ✓ Hierarquia suave O que enfraquece essa composição? ✕ Elementos excessivamente dominantes ✕ Contrastes muito agressivos ✕ Acúmulo visual localizado ✕ Excesso de objetos ✕ Vazios sem intenção ✕ Composição fragmentada ✕ Rupturas visuais inesperadas Princípio Fundamental O objetivo não é destacar um elemento específico. O objetivo é valorizar a experiência completa do espaço.',
        2
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'equilibrada'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Leitura estável', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de harmonia', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Distribuição equilibrada do peso visual', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Arquitetura percebida como conjunto', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Pouca competição visual', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Organização clara', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Compreensão global do ambiente', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Distribuição uniforme dos pesos visuais', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Organização clara dos elementos', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Simetria parcial ou total', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Proporções equilibradas', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Controle do contraste', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Harmonia entre luz e materiais', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Ocupação coerente do espaço', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Hierarquia suave', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Protagonistas excessivamente dominantes', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Desequilíbrio visual', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Concentração excessiva de informação em uma área', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Espaços vazios sem função compositiva', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Disputa de atenção entre elementos', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ruído visual', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Protagonismo Baixo a Médio Número de Pontos de Interesse 3 a 5 pontos de interesse distribuídos Velocidade de Leitura Média Relação Arquitetura × Lifestyle Equilibrada Recursos Compositivos Comuns ● Simetria ● Equilíbrio de massas ● Distribuição uniforme de informação ● Ritmo visual ● Repetição de elementos ● Proporções harmoniosas ● Controle de contraste ● Profundidade moderada Aplicação na IMPROOV Quando utilizar ✓ Ambientes internos amplos ✓ Áreas comuns ✓ Living rooms ✓ Salões de festas ✓ Espaços gourmet ✓ Ambientes onde o conjunto é mais importante que um detalhe específico ✓ Apresentação de materialidade e arquitetura simultaneamente Quando evitar ✕ Destaque de um diferencial único ✕ Imagens comerciais que dependem de um protagonista forte ✕ Espaços com narrativa muito direcionada',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Hierarquia Principal A atenção é distribuída de forma harmoniosa entre os principais elementos da cena. Diferença Principal Não existe um único protagonista absoluto. A força da imagem está no equilíbrio entre os elementos que compõem o espaço. Características ✓ Leitura estável ✓ Sensação de harmonia ✓ Distribuição equilibrada do peso visual ✓ Arquitetura percebida como conjunto ✓ Pouca competição visual ✓ Organização clara ✓ Compreensão global do ambiente Evitar ✕ Protagonistas excessivamente dominantes ✕ Desequilíbrio visual ✕ Concentração excessiva de informação em uma área ✕ Espaços vazios sem função compositiva ✕ Disputa de atenção entre elementos ✕ Ruído visual Diretriz Completa Como queremos que o observador percorra a imagem? O olhar deve explorar a cena de forma natural e confortável. Não existe urgência para identificar um protagonista específico. A percepção acontece através do conjunto. O observador compreende primeiro o espaço como um todo e, depois, seus elementos individuais. O que deve dominar a percepção da imagem? O ambiente. A força da composição não está em um único objeto, mas na relação equilibrada entre arquitetura, materiais, iluminação e lifestyle. O que reforça essa composição? ✓ Distribuição uniforme dos pesos visuais ✓ Organização clara dos elementos ✓ Simetria parcial ou total ✓ Proporções equilibradas ✓ Controle do contraste ✓ Harmonia entre luz e materiais ✓ Ocupação coerente do espaço ✓ Hierarquia suave O que enfraquece essa composição? ✕ Elementos excessivamente dominantes ✕ Contrastes muito agressivos ✕ Acúmulo visual localizado ✕ Excesso de objetos ✕ Vazios sem intenção ✕ Composição fragmentada ✕ Rupturas visuais inesperadas Princípio Fundamental O objetivo não é destacar um elemento específico. O objetivo é valorizar a experiência completa do espaço. Camada Operacional Protagonismo Baixo a Médio Número de Pontos de Interesse 3 a 5 pontos de interesse distribuídos Velocidade de Leitura Média Relação Arquitetura × Lifestyle Equilibrada Recursos Compositivos Comuns ● Simetria ● Equilíbrio de massas ● Distribuição uniforme de informação ● Ritmo visual ● Repetição de elementos ● Proporções harmoniosas ● Controle de contraste ● Profundidade moderada Aplicação na IMPROOV Quando utilizar ✓ Ambientes internos amplos ✓ Áreas comuns ✓ Living rooms ✓ Salões de festas ✓ Espaços gourmet ✓ Ambientes onde o conjunto é mais importante que um detalhe específico ✓ Apresentação de materialidade e arquitetura simultaneamente Quando evitar ✕ Destaque de um diferencial único ✕ Imagens comerciais que dependem de um protagonista forte ✕ Espaços com narrativa muito direcionada',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'composicao'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'narrativa',
        'Narrativa',
        'A atenção percorre diferentes camadas da imagem, revelando informações gradualmente.',
        'O valor da imagem não está apenas no primeiro olhar, mas na descoberta progressiva de elementos e relações visuais.',
        NULL,
        'Composição Narrativa não significa contar uma história. Significa criar uma sequência de descobertas visuais.',
        'Como queremos que o observador percorra a imagem? O olhar não deve encerrar a leitura nos primeiros segundos. A composição deve convidar o observador a explorar diferentes áreas da cena, descobrindo novas informações ao longo do percurso. Existe uma sequência de leitura. O que deve dominar a percepção da imagem? A jornada visual. O observador não é atraído apenas por um protagonista. Ele é conduzido através de múltiplos pontos de interesse que trabalham juntos para construir uma percepção mais rica do ambiente. O que reforça essa composição? ✓ Sobreposição de planos ✓ Profundidade ✓ Conexões entre ambientes ✓ Camadas visuais ✓ Elementos parcialmente revelados ✓ Percursos arquitetônicos ✓ Relações entre primeiro plano, meio plano e fundo ✓ Uso estratégico de enquadramentos internos O que enfraquece essa composição? ✕ Excesso de protagonistas ✕ Ausência de hierarquia ✕ Elementos desconectados ✕ Ambientes excessivamente simplificados ✕ Falta de profundidade ✕ Leitura imediata demais ✕ Poluição visual Princípio Fundamental Composição Narrativa não significa contar uma história. Significa criar uma sequência de descobertas visuais.',
        3
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'narrativa'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Leitura progressiva', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Múltiplas camadas de informação', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de descoberta', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Profundidade visual', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Percurso do olhar bem definido', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Elementos conectados entre si', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Recompensa visual ao explorar a imagem', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Sobreposição de planos', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Profundidade', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Conexões entre ambientes', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Camadas visuais', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Elementos parcialmente revelados', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Percursos arquitetônicos', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Relações entre primeiro plano, meio plano e fundo', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Uso estratégico de enquadramentos internos', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de informação desconectada', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Leitura confusa', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Muitos elementos competindo simultaneamente', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ausência de hierarquia', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Ambientes visualmente caóticos', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Falta de percurso visual', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Protagonismo Médio Número de Pontos de Interesse 3 a 7 pontos conectados Velocidade de Leitura Progressiva Relação Arquitetura × Lifestyle Equilibrada Recursos Compositivos Comuns ● Sobreposição de planos ● Profundidade ● Portais visuais ● Conexão entre ambientes ● Linhas de condução ● Camadas de iluminação ● Molduras naturais ● Ritmo visual Aplicação na IMPROOV Quando utilizar ✓ Ambientes integrados ✓ Living + varanda ✓ Áreas comuns complexas ✓ Rooftops ✓ Club houses ✓ Espaços com múltiplas experiências ✓ Projetos que valorizam percurso e descoberta Quando evitar ✕ Imagens comerciais de impacto imediato ✕ Destaque de um único diferencial ✕ Ambientes excessivamente compactos ✕ Cenas onde a leitura precisa ser instantânea',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Hierarquia Principal A atenção percorre diferentes camadas da imagem, revelando informações gradualmente. Diferença Principal O valor da imagem não está apenas no primeiro olhar, mas na descoberta progressiva de elementos e relações visuais. Características ✓ Leitura progressiva ✓ Múltiplas camadas de informação ✓ Sensação de descoberta ✓ Profundidade visual ✓ Percurso do olhar bem definido ✓ Elementos conectados entre si ✓ Recompensa visual ao explorar a imagem Evitar ✕ Excesso de informação desconectada ✕ Leitura confusa ✕ Muitos elementos competindo simultaneamente ✕ Ausência de hierarquia ✕ Ambientes visualmente caóticos ✕ Falta de percurso visual Diretriz Completa Como queremos que o observador percorra a imagem? O olhar não deve encerrar a leitura nos primeiros segundos. A composição deve convidar o observador a explorar diferentes áreas da cena, descobrindo novas informações ao longo do percurso. Existe uma sequência de leitura. O que deve dominar a percepção da imagem? A jornada visual. O observador não é atraído apenas por um protagonista. Ele é conduzido através de múltiplos pontos de interesse que trabalham juntos para construir uma percepção mais rica do ambiente. O que reforça essa composição? ✓ Sobreposição de planos ✓ Profundidade ✓ Conexões entre ambientes ✓ Camadas visuais ✓ Elementos parcialmente revelados ✓ Percursos arquitetônicos ✓ Relações entre primeiro plano, meio plano e fundo ✓ Uso estratégico de enquadramentos internos O que enfraquece essa composição? ✕ Excesso de protagonistas ✕ Ausência de hierarquia ✕ Elementos desconectados ✕ Ambientes excessivamente simplificados ✕ Falta de profundidade ✕ Leitura imediata demais ✕ Poluição visual Princípio Fundamental Composição Narrativa não significa contar uma história. Significa criar uma sequência de descobertas visuais. Camada Operacional Protagonismo Médio Número de Pontos de Interesse 3 a 7 pontos conectados Velocidade de Leitura Progressiva Relação Arquitetura × Lifestyle Equilibrada Recursos Compositivos Comuns ● Sobreposição de planos ● Profundidade ● Portais visuais ● Conexão entre ambientes ● Linhas de condução ● Camadas de iluminação ● Molduras naturais ● Ritmo visual Aplicação na IMPROOV Quando utilizar ✓ Ambientes integrados ✓ Living + varanda ✓ Áreas comuns complexas ✓ Rooftops ✓ Club houses ✓ Espaços com múltiplas experiências ✓ Projetos que valorizam percurso e descoberta Quando evitar ✕ Imagens comerciais de impacto imediato ✕ Destaque de um único diferencial ✕ Ambientes excessivamente compactos ✕ Cenas onde a leitura precisa ser instantânea',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'composicao'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'monumental',
        'Monumental',
        'A arquitetura domina a percepção da imagem.',
        'A composição enfatiza escala, presença e impacto arquitetônico, fazendo com que o observador perceba a força do espaço antes de qualquer outro elemento.',
        NULL,
        'A arquitetura não participa da composição. Ela é a composição.',
        'Como queremos que o observador percorra a imagem? O olhar deve ser imediatamente capturado pela arquitetura. Os demais elementos existem para reforçar sua presença e não para disputar protagonismo. A leitura começa pela forma arquitetônica e depois se expande para os detalhes. O que deve dominar a percepção da imagem? A arquitetura. Volume. Escala. Proporção. Forma. Presença. O observador deve sentir que está diante de algo relevante antes mesmo de analisar os detalhes. O que reforça essa composição? ✓ Grandes planos arquitetônicos ✓ Linhas fortes ✓ Simetria ✓ Perspectivas amplas ✓ Céu e contexto controlados ✓ Escala humana estratégica ✓ Poucos elementos concorrentes ✓ Contraste entre arquitetura e entorno ✓ Hierarquia extremamente clara O que enfraquece essa composição? ✕ Pessoas dominando a cena ✕ Elementos decorativos chamando mais atenção que a arquitetura ✕ Excesso de mobiliário ✕ Enquadramentos apertados ✕ Cortes que escondem a volumetria ✕ Contexto excessivamente carregado ✕ Múltiplos protagonistas Princípio Fundamental A arquitetura não participa da composição. Ela é a composição.',
        4
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'monumental'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Arquitetura protagonista', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de grandiosidade', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Escala evidente', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Impacto visual imediato', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Presença arquitetônica dominante', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Leitura contemplativa', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Forte identidade formal', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de admiração', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Grandes planos arquitetônicos', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Linhas fortes', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Simetria', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Perspectivas amplas', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Céu e contexto controlados', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Escala humana estratégica', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Poucos elementos concorrentes', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Contraste entre arquitetura e entorno', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Hierarquia extremamente clara', 9
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 9
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Lifestyle excessivamente dominante', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Excesso de elementos decorativos', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Poluição visual', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Fragmentação da arquitetura', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Escalas mal definidas', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Elementos competindo com o edifício', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Protagonismo Muito Alto Número de Pontos de Interesse 1 principal até 2 complementares Velocidade de Leitura Rápida no impacto inicial Progressiva nos detalhes Relação Arquitetura × Lifestyle Arquitetura dominante Lifestyle secundário Recursos Compositivos Comuns ● Simetria ● Eixos fortes ● Escala humana controlada ● Perspectivas amplas ● Contraste arquitetônico ● Enquadramentos limpos ● Profundidade moderada ● Hierarquia visual extrema Aplicação na IMPROOV Quando utilizar ✓ Fachadas ✓ Torres residenciais ✓ Empreendimentos de luxo ✓ Lobbies monumentais ✓ Áreas icônicas do projeto ✓ Imagens institucionais ✓ Imagens de capa ✓ Diferenciais arquitetônicos marcantes Quando evitar ✕ Ambientes cuja experiência humana é mais importante que a arquitetura ✕ Espaços intimistas ✕ Narrativas centradas em lifestyle ✕ Ambientes pequenos ou muito compartimentados',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Hierarquia Principal A arquitetura domina a percepção da imagem. Diferença Principal A composição enfatiza escala, presença e impacto arquitetônico, fazendo com que o observador perceba a força do espaço antes de qualquer outro elemento. Características ✓ Arquitetura protagonista ✓ Sensação de grandiosidade ✓ Escala evidente ✓ Impacto visual imediato ✓ Presença arquitetônica dominante ✓ Leitura contemplativa ✓ Forte identidade formal ✓ Sensação de admiração Evitar ✕ Lifestyle excessivamente dominante ✕ Excesso de elementos decorativos ✕ Poluição visual ✕ Fragmentação da arquitetura ✕ Escalas mal definidas ✕ Elementos competindo com o edifício Diretriz Completa Como queremos que o observador percorra a imagem? O olhar deve ser imediatamente capturado pela arquitetura. Os demais elementos existem para reforçar sua presença e não para disputar protagonismo. A leitura começa pela forma arquitetônica e depois se expande para os detalhes. O que deve dominar a percepção da imagem? A arquitetura. Volume. Escala. Proporção. Forma. Presença. O observador deve sentir que está diante de algo relevante antes mesmo de analisar os detalhes. O que reforça essa composição? ✓ Grandes planos arquitetônicos ✓ Linhas fortes ✓ Simetria ✓ Perspectivas amplas ✓ Céu e contexto controlados ✓ Escala humana estratégica ✓ Poucos elementos concorrentes ✓ Contraste entre arquitetura e entorno ✓ Hierarquia extremamente clara O que enfraquece essa composição? ✕ Pessoas dominando a cena ✕ Elementos decorativos chamando mais atenção que a arquitetura ✕ Excesso de mobiliário ✕ Enquadramentos apertados ✕ Cortes que escondem a volumetria ✕ Contexto excessivamente carregado ✕ Múltiplos protagonistas Princípio Fundamental A arquitetura não participa da composição. Ela é a composição. Camada Operacional Protagonismo Muito Alto Número de Pontos de Interesse 1 principal até 2 complementares Velocidade de Leitura Rápida no impacto inicial Progressiva nos detalhes Relação Arquitetura × Lifestyle Arquitetura dominante Lifestyle secundário Recursos Compositivos Comuns ● Simetria ● Eixos fortes ● Escala humana controlada ● Perspectivas amplas ● Contraste arquitetônico ● Enquadramentos limpos ● Profundidade moderada ● Hierarquia visual extrema Aplicação na IMPROOV Quando utilizar ✓ Fachadas ✓ Torres residenciais ✓ Empreendimentos de luxo ✓ Lobbies monumentais ✓ Áreas icônicas do projeto ✓ Imagens institucionais ✓ Imagens de capa ✓ Diferenciais arquitetônicos marcantes Quando evitar ✕ Ambientes cuja experiência humana é mais importante que a arquitetura ✕ Espaços intimistas ✕ Narrativas centradas em lifestyle ✕ Ambientes pequenos ou muito compartimentados',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

SET
    @alma_dim = (
        SELECT id
        FROM alma_biblioteca_dimensao
        WHERE
            versao_id = @alma_v1
            AND codigo = 'composicao'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item (
        dimensao_id,
        codigo,
        titulo,
        resumo,
        diferenca_principal,
        descricao,
        principio_fundamental,
        diretriz_completa,
        ordem
    )
VALUES (
        @alma_dim,
        'imersiva',
        'Imersiva',
        'O observador deve sentir que está presente dentro da cena.',
        'A composição prioriza a sensação de presença e conexão com o ambiente em vez da contemplação externa do espaço.',
        NULL,
        'O objetivo não é mostrar o espaço. O objetivo é fazer o observador sentir que está nele.',
        'Como queremos que o observador percorra a imagem? O observador deve sentir que ocupa um lugar dentro do espaço. A composição não deve criar a sensação de estar olhando para um ambiente. Ela deve criar a sensação de estar dentro dele. A leitura acontece através da experiência espacial. O que deve dominar a percepção da imagem? A vivência do ambiente. Mais importante do que admirar a arquitetura é sentir como seria estar naquele local. O espaço deve parecer acessível, acolhedor e utilizável. O que reforça essa composição? ✓ Escala humana ✓ Primeiro plano presente ✓ Profundidade evidente ✓ Camadas espaciais ✓ Elementos próximos ao observador ✓ Conexão entre interior e exterior ✓ Percursos visuais naturais ✓ Altura de câmera compatível com o olhar humano ✓ Enquadramentos convidativos O que enfraquece essa composição? ✕ Câmeras excessivamente distantes ✕ Perspectivas muito abertas ✕ Escala monumental dominante ✕ Ambientes excessivamente vazios ✕ Falta de profundidade ✕ Barreiras visuais que afastam o observador ✕ Sensação de cena meramente expositiva Princípio Fundamental O objetivo não é mostrar o espaço. O objetivo é fazer o observador sentir que está nele.',
        5
    );

SET
    @alma_item = (
        SELECT id
        FROM alma_biblioteca_item
        WHERE
            dimensao_id = @alma_dim
            AND codigo = 'imersiva'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'caracteristicas',
        'Características',
        NULL,
        1
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'caracteristicas'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Sensação de pertencimento', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Proximidade com o ambiente', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Escala humana', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Conexão emocional', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Experiência espacial', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Participação do observador', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Profundidade perceptível', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'CARACTERISTICA', 'Convite à permanência', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'reforca',
        'Reforça',
        NULL,
        2
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'reforca'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Escala humana', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Primeiro plano presente', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Profundidade evidente', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Camadas espaciais', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Elementos próximos ao observador', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Conexão entre interior e exterior', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Percursos visuais naturais', 7
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 7
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Altura de câmera compatível com o olhar humano', 8
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 8
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'REFORCA', 'Enquadramentos convidativos', 9
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 9
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'evitar',
        'Evitar / enfraquece',
        NULL,
        3
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'evitar'
        LIMIT 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Distanciamento excessivo', 1
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 1
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Sensação de observação externa', 2
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 2
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Escalas excessivamente monumentais', 3
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 3
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Enquadramentos frios', 4
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 4
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Barreiras visuais desnecessárias', 5
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 5
    );

INSERT INTO
    alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem)
SELECT @alma_secao, 'EVITAR', 'Falta de profundidade', 6
WHERE
    NOT EXISTS (
        SELECT 1
        FROM alma_biblioteca_secao_entrada
        WHERE
            secao_id = @alma_secao
            AND ordem = 6
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'complementar',
        'Conteúdo complementar',
        'Protagonismo Médio Número de Pontos de Interesse 2 a 5 pontos conectados Velocidade de Leitura Natural e progressiva Relação Arquitetura × Lifestyle Equilibrada Experiência dominante Recursos Compositivos Comuns ● Primeiro plano ativo ● Profundidade de camadas ● Escala humana ● Enquadramentos envolventes ● Conexão entre planos ● Molduras naturais ● Perspectiva ao nível dos olhos ● Continuidade espacial Aplicação na IMPROOV Quando utilizar ✓ Living rooms ✓ Varandas ✓ Espaços gourmet ✓ Quartos ✓ Áreas de lazer ✓ Piscinas ✓ Club houses ✓ Ambientes cuja experiência de uso é o principal diferencial Quando evitar ✕ Fachadas institucionais ✕ Imagens de impacto arquitetônico ✕ Situações onde a escala é mais importante que a experiência ✕ Apresentações excessivamente técnicas',
        4
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'complementar'
        LIMIT 1
    );

INSERT IGNORE INTO
    alma_biblioteca_item_secao (
        item_id,
        codigo,
        titulo,
        conteudo,
        ordem
    )
VALUES (
        @alma_item,
        'fonte_oficial',
        'Conteúdo oficial integral',
        'Versão Resumida Hierarquia Principal O observador deve sentir que está presente dentro da cena. Diferença Principal A composição prioriza a sensação de presença e conexão com o ambiente em vez da contemplação externa do espaço. Características ✓ Sensação de pertencimento ✓ Proximidade com o ambiente ✓ Escala humana ✓ Conexão emocional ✓ Experiência espacial ✓ Participação do observador ✓ Profundidade perceptível ✓ Convite à permanência Evitar ✕ Distanciamento excessivo ✕ Sensação de observação externa ✕ Escalas excessivamente monumentais ✕ Enquadramentos frios ✕ Barreiras visuais desnecessárias ✕ Falta de profundidade Diretriz Completa Como queremos que o observador percorra a imagem? O observador deve sentir que ocupa um lugar dentro do espaço. A composição não deve criar a sensação de estar olhando para um ambiente. Ela deve criar a sensação de estar dentro dele. A leitura acontece através da experiência espacial. O que deve dominar a percepção da imagem? A vivência do ambiente. Mais importante do que admirar a arquitetura é sentir como seria estar naquele local. O espaço deve parecer acessível, acolhedor e utilizável. O que reforça essa composição? ✓ Escala humana ✓ Primeiro plano presente ✓ Profundidade evidente ✓ Camadas espaciais ✓ Elementos próximos ao observador ✓ Conexão entre interior e exterior ✓ Percursos visuais naturais ✓ Altura de câmera compatível com o olhar humano ✓ Enquadramentos convidativos O que enfraquece essa composição? ✕ Câmeras excessivamente distantes ✕ Perspectivas muito abertas ✕ Escala monumental dominante ✕ Ambientes excessivamente vazios ✕ Falta de profundidade ✕ Barreiras visuais que afastam o observador ✕ Sensação de cena meramente expositiva Princípio Fundamental O objetivo não é mostrar o espaço. O objetivo é fazer o observador sentir que está nele. Camada Operacional Protagonismo Médio Número de Pontos de Interesse 2 a 5 pontos conectados Velocidade de Leitura Natural e progressiva Relação Arquitetura × Lifestyle Equilibrada Experiência dominante Recursos Compositivos Comuns ● Primeiro plano ativo ● Profundidade de camadas ● Escala humana ● Enquadramentos envolventes ● Conexão entre planos ● Molduras naturais ● Perspectiva ao nível dos olhos ● Continuidade espacial Aplicação na IMPROOV Quando utilizar ✓ Living rooms ✓ Varandas ✓ Espaços gourmet ✓ Quartos ✓ Áreas de lazer ✓ Piscinas ✓ Club houses ✓ Ambientes cuja experiência de uso é o principal diferencial Quando evitar ✕ Fachadas institucionais ✕ Imagens de impacto arquitetônico ✕ Situações onde a escala é mais importante que a experiência ✕ Apresentações excessivamente técnicas',
        5
    );

SET
    @alma_secao = (
        SELECT id
        FROM alma_biblioteca_item_secao
        WHERE
            item_id = @alma_item
            AND codigo = 'fonte_oficial'
        LIMIT 1
    );

COMMIT;