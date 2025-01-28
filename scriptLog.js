const mysql = require('mysql2');

// Configuração da conexão com o banco de dados
const connection = mysql.createConnection({
    host: 'mysql.improov.com.br',
    user: 'improov',
    password: 'Impr00v',
    database: 'improov'
});

// Função para verificar uma obra específica e registrar logs
const verificarObra = async (obraId) => {
    const consulta = `
        SELECT 
            i.tipo_imagem,
            fun.nome_funcao,
            COUNT(fi.idfuncao_imagem) AS total_funcoes,
            COUNT(CASE WHEN fi.status = 'Finalizado' THEN 1 END) AS funcoes_finalizadas,
            MAX(fi.prazo) AS ultimo_prazo
        FROM 
            funcao fun
        JOIN 
            funcao_imagem fi 
            ON fun.idfuncao = fi.funcao_id
        JOIN 
            imagens_cliente_obra i 
            ON fi.imagem_id = i.idimagens_cliente_obra
        WHERE 
            i.obra_id = ?
        GROUP BY 
            i.tipo_imagem, fun.nome_funcao;
    `;

    connection.query(consulta, [obraId], (err, results) => {
        if (err) {
            console.error(`Erro ao verificar funções para a obra ${obraId}:`, err);
            return;
        }

        results.forEach((row) => {
            const { tipo_imagem, nome_funcao, total_funcoes, funcoes_finalizadas, ultimo_prazo } = row;

            // Verifica se todas as funções estão finalizadas
            if (total_funcoes === funcoes_finalizadas) {
                const mensagem = `${nome_funcao} do tipo de imagem ${tipo_imagem} finalizada em ${new Date(ultimo_prazo).toLocaleString()}`;

                const logInsert = `
                    INSERT INTO logs_automaticos (obra_id, tipo_imagem, funcao_nome, mensagem, data_criacao)
                    VALUES (?, ?, ?, ?, ?);
                `;
                
                connection.query(
                    logInsert,
                    [obraId, tipo_imagem, nome_funcao, mensagem, new Date(ultimo_prazo)],
                    (err) => {
                        if (err) {
                            console.error(`Erro ao inserir log para a obra ${obraId}:`, err);
                            return;
                        }
                        console.log(`Log inserido para obra ${obraId}: ${mensagem}`);
                    }
                );
            }
        });
    });
};

// Teste com uma obra específica
const obraIdTeste = 16; // Substitua pelo ID da obra que deseja testar
verificarObra(obraIdTeste);

// Fechar conexão após 10 segundos (ou ajustar conforme necessidade)
setTimeout(() => {
    connection.end(() => {
        console.log('Conexão com o banco de dados encerrada.');
    });
}, 10000);
