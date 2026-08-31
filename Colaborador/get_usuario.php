<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../conexao.php'; // Certifique-se de incluir a conexão com o banco

$idusuario = $_GET['idusuario'] ?? 0;

// Consulta para pegar as informações do usuário
$sql_usuario = "SELECT 
                    u.*,
                    c.nome_colaborador,
                    c.elegivel_capacidade,
                    CONCAT(UPPER(LEFT(SUBSTRING_INDEX(u.nome_usuario, ' ', 1), 1)), LOWER(SUBSTRING(SUBSTRING_INDEX(u.nome_usuario, ' ', 1), 2))) AS primeiro_nome_formatado
                FROM 
                    usuario u
                LEFT JOIN 
                    colaborador c ON u.idcolaborador = c.idcolaborador
                WHERE 
                    u.idusuario = ? 
                GROUP BY
                    u.idusuario";

$stmt_usuario = $conn->prepare($sql_usuario);
$stmt_usuario->bind_param("i", $idusuario);
$stmt_usuario->execute();
$result_usuario = $stmt_usuario->get_result();
$usuario = $result_usuario->fetch_assoc();

// Consulta para pegar os cargos do usuário
$sql_cargos = "SELECT c.id AS cargo_id, c.nome AS cargo_nome 
               FROM cargo c
               JOIN usuario_cargo uc ON c.id = uc.cargo_id
               WHERE uc.usuario_id = ?";

$stmt_cargos = $conn->prepare($sql_cargos);
$stmt_cargos->bind_param("i", $idusuario);
$stmt_cargos->execute();
$result_cargos = $stmt_cargos->get_result();

// Cria um array para armazenar os cargos
$cargos = [];
while ($row = $result_cargos->fetch_assoc()) {
    $cargos[] = $row['cargo_id']; // Armazena o ID do cargo
}

$sql_funcoes = "SELECT fc.funcao_id, fc.nivel_finalizacao, fc.tipo_atuacao
                FROM funcao_colaborador fc
                WHERE fc.colaborador_id = ?";

$stmt_funcoes = $conn->prepare($sql_funcoes);
$stmt_funcoes->bind_param("i", $usuario['idcolaborador']);
$stmt_funcoes->execute();
$result_funcoes = $stmt_funcoes->get_result();

$funcoes = [];
$funcoes_atuacao = [];
$nivel_finalizacao = null;
while ($row = $result_funcoes->fetch_assoc()) {
    $funcaoId = (int) $row['funcao_id'];
    $funcoes[] = $funcaoId;
    $funcoes_atuacao[(string) $funcaoId] = strtoupper((string) ($row['tipo_atuacao'] ?? 'SECUNDARIA')) === 'PRINCIPAL'
        ? 'PRINCIPAL'
        : 'SECUNDARIA';
    if ($row['nivel_finalizacao'] !== null) {
        $nivel_finalizacao = (int) $row['nivel_finalizacao'];
    }
}

$response = [
    'usuario' => $usuario,
    'cargos' => $cargos,
    'funcoes' => $funcoes,
    'funcoes_atuacao' => $funcoes_atuacao,
    'nivel_finalizacao' => $nivel_finalizacao
];

echo json_encode($response);
