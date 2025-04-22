<?php
// Arquivo: revisao.php
session_start();
include '../conexao.php'; // Conexão com o banco de dados

// Verifique se o usuário está autenticado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
  header("Location: ../index.html");
  exit();
}

$idusuario = $_SESSION['idusuario'];
$idcolaborador = $_SESSION['idcolaborador'];

// Buscar as tarefas de revisão do banco de dados
if ($idusuario == 1 || $idusuario == 2) {
  //Pedro e André
  $sql = "SELECT 
    f.idfuncao_imagem,
    f.funcao_id, 
    fun.nome_funcao, 
    f.check_funcao, 
    f.imagem_id, 
    i.imagem_nome, 
    f.colaborador_id, 
    c.nome_colaborador, 
    c.telefone,
    u.id_slack,
    o.nome_obra,
    o.nomenclatura,
    
    -- Data da última aprovação registrada
    h.data_aprovacao,
    
    -- Último status registrado no histórico
    h.status_novo AS status_novo,
    
    -- Última imagem enviada no histórico
    (
        SELECT hi.imagem
        FROM historico_aprovacoes_imagens hi 
        WHERE hi.funcao_imagem_id = f.idfuncao_imagem
        ORDER BY hi.data_envio DESC 
        LIMIT 1
    ) AS imagem

FROM funcao_imagem f

LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
LEFT JOIN usuario u ON u.idcolaborador = c.idcolaborador
LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
LEFT JOIN obra o ON i.obra_id = o.idobra

-- Join com subquery que pega o último status do histórico por funcao_imagem
LEFT JOIN (
    SELECT h1.funcao_imagem_id, h1.status_novo, h1.data_aprovacao
    FROM historico_aprovacoes h1
    INNER JOIN (
        SELECT funcao_imagem_id, MAX(data_aprovacao) AS max_data
        FROM historico_aprovacoes
        GROUP BY funcao_imagem_id
    ) h2 ON h2.funcao_imagem_id = h1.funcao_imagem_id AND h1.data_aprovacao = h2.max_data
) h ON h.funcao_imagem_id = f.idfuncao_imagem

-- Filtro baseado apenas no último status do histórico
WHERE f.funcao_id IN (1, 2, 3, 4, 5, 6, 7, 8, 9)
  AND h.status_novo IN ('Em aprovação', 'Ajuste', 'Aprovado com ajustes')
  AND f.status <> 'Finalizado'

ORDER BY h.data_aprovacao DESC";
} elseif ($idusuario == 9 || $idusuario == 20 || $idusuario == 3) {
  //Nicolle
  $sql = "SELECT 
    f.idfuncao_imagem,
    f.funcao_id, 
    fun.nome_funcao, 
    f.status, 
    f.check_funcao, 
    f.imagem_id, 
    i.imagem_nome, 
    f.colaborador_id, 
    c.nome_colaborador, 
    c.telefone,
    u.id_slack,
	  o.nome_obra,
    o.nomenclatura,
    (SELECT MAX(h.data_aprovacao)
     FROM historico_aprovacoes h
     WHERE h.funcao_imagem_id = f.idfuncao_imagem) AS data_aprovacao,
    (SELECT h.status_novo
     FROM historico_aprovacoes h
     WHERE h.funcao_imagem_id = f.idfuncao_imagem
     ORDER BY h.data_aprovacao DESC 
     LIMIT 1) AS status_novo,
	(SELECT hi.imagem
     FROM historico_aprovacoes_imagens hi 
     WHERE hi.funcao_imagem_id = f.idfuncao_imagem
     ORDER BY hi.data_envio DESC 
     LIMIT 1) AS imagem
FROM funcao_imagem f
LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
LEFT JOIN usuario u ON u.idcolaborador = c.idcolaborador
LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
LEFT JOIN obra o ON i.obra_id = o.idobra
WHERE f.funcao_id IN (1, 2, 3, 4, 5, 6, 7, 8, 9) 
  AND h.status_novo IN ('Em aprovação', 'Ajuste', 'Aprovado com ajustes')
ORDER BY data_aprovacao DESC";
} else {
  $sql = "SELECT 
    f.idfuncao_imagem,
    f.funcao_id, 
    fun.nome_funcao, 
    f.status, 
    f.check_funcao, 
    f.imagem_id, 
    i.imagem_nome, 
    f.colaborador_id, 
    c.nome_colaborador, 
    c.telefone,
    u.id_slack,
    o.nome_obra,
    o.nomenclatura,
    (SELECT MAX(h.data_aprovacao)
     FROM historico_aprovacoes h
     WHERE h.funcao_imagem_id = f.idfuncao_imagem) AS data_aprovacao,
    (SELECT h.status_novo
     FROM historico_aprovacoes h
     WHERE h.funcao_imagem_id = f.idfuncao_imagem
     ORDER BY h.data_aprovacao DESC 
     LIMIT 1) AS status_novo,
    (SELECT hi.imagem
     FROM historico_aprovacoes_imagens hi 
     WHERE hi.funcao_imagem_id = f.idfuncao_imagem
     ORDER BY hi.data_envio DESC 
     LIMIT 1) AS imagem
FROM funcao_imagem f
LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
LEFT JOIN usuario u ON u.idcolaborador = c.idcolaborador
LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
LEFT JOIN obra o ON i.obra_id = o.idobra
WHERE f.funcao_id IN (1, 2, 3, 4, 5, 6, 7, 8, 9) 
  AND h.status_novo IN ('Em aprovação', 'Ajuste', 'Aprovado com ajustes')
  AND c.idcolaborador = ?
ORDER BY data_aprovacao DESC";
}

if ($idusuario == 1 || $idusuario == 2) {
  $stmt = $conn->prepare($sql);
} elseif ($idusuario == 9 || $idusuario == 20 || $idusuario == 3) {
  $stmt = $conn->prepare($sql);
} else {
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $idcolaborador);
}

$stmt->execute();
$result = $stmt->get_result();

$tarefas = [];
if ($result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $tarefas[] = $row;
  }
}

// Retorna as tarefas no formato JSON
echo json_encode($tarefas);

$stmt->close();
$conn->close();
