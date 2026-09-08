-- Correcao documentada do primeiro carregamento da Biblioteca ALMA v1.0.
-- Dois delimitadores de extracao ocultaram campos que existem no PDF oficial.
-- A correcao so e aplicada enquanto nenhuma direcao referencia a versao 1.0.

START TRANSACTION;

SET
    @alma_v1 = (
        SELECT id
        FROM alma_biblioteca_versao
        WHERE
            codigo = '1.0'
        LIMIT 1
    );

UPDATE alma_biblioteca_item i
JOIN alma_biblioteca_dimensao d ON d.id = i.dimensao_id
SET
    i.principio_fundamental = 'Bem-Estar não significa apenas descanso. Significa que o espaço contribui ativamente para uma vida mais saudável, equilibrada e prazerosa.'
WHERE
    d.versao_id = @alma_v1
    AND d.codigo = 'lifestyle'
    AND i.codigo = 'bem_estar'
    AND NOT EXISTS (
        SELECT 1
        FROM alma_direcao_revisao r
        WHERE
            r.biblioteca_versao_id = @alma_v1
    );

UPDATE alma_biblioteca_item i
JOIN alma_biblioteca_dimensao d ON d.id = i.dimensao_id
SET
    i.resumo = 'Momentos de exploração, curiosidade e conexão com o espaço.'
WHERE
    d.versao_id = @alma_v1
    AND d.codigo = 'lifestyle'
    AND i.codigo = 'descoberta'
    AND NOT EXISTS (
        SELECT 1
        FROM alma_direcao_revisao r
        WHERE
            r.biblioteca_versao_id = @alma_v1
    );

COMMIT;