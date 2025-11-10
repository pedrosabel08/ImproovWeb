<?php
session_start();
include '../conexao.php'; // Conexão com o banco de dados

// Verifique se o usuário está autenticado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
  header("Location: ../index.html");
  exit();
}

$idusuario = $_SESSION['idusuario'];
$idcolaborador = $_SESSION['idcolaborador'];

try {
  // Construção da query com base no usuário
  if ($idusuario == 1 || $idusuario == 2) {
    $sql = "SELECT 
            f.idfuncao_imagem,
            f.funcao_id, 
            fun.nome_funcao, 
            f.status, 
            f.imagem_id, 
            i.imagem_nome, 
            f.colaborador_id, 
            c.nome_colaborador, 
            c.telefone,
            u.nome_slack,
            o.nome_obra,
            o.nomenclatura,
            o.idobra,
            s.nome_status,
            (SELECT MAX(hi.data_envio)
             FROM historico_aprovacoes_imagens hi
             WHERE hi.funcao_imagem_id = f.idfuncao_imagem) AS data_aprovacao,
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
        LEFT JOIN status_imagem s ON i.status_id = s.idstatus
        LEFT JOIN obra o ON i.obra_id = o.idobra
        WHERE f.funcao_id IN (1, 2, 3, 4, 5, 6, 7, 8, 9) 
          AND f.status IN ('Em aprovação', 'Ajuste', 'Aprovado com ajustes', 'Em andamento') AND o.status_obra = 0
        ORDER BY data_aprovacao DESC";
  } elseif ($idusuario == 9 || $idusuario == 20 || $idusuario == 3) {
    $sql = "SELECT 
            f.idfuncao_imagem,
            f.funcao_id, 
            fun.nome_funcao, 
            f.status, 
            f.imagem_id, 
            i.imagem_nome, 
            f.colaborador_id, 
            c.nome_colaborador, 
            c.telefone,
            u.nome_slack,
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
          AND f.status IN ('Em aprovação', 'Ajuste', 'Aprovado com ajustes', 'Em andamento')
        ORDER BY data_aprovacao DESC";
  } else {
    $sql = "SELECT 
            f.idfuncao_imagem,
            f.funcao_id, 
            fun.nome_funcao, 
            f.status, 
            f.imagem_id, 
            i.imagem_nome, 
            f.colaborador_id, 
            c.nome_colaborador, 
            c.telefone,
            u.nome_slack,
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
          AND f.status IN ('Em aprovação', 'Ajuste', 'Aprovado com ajustes', 'Em andamento')
          AND o.idobra IN (
              SELECT i2.obra_id
              FROM imagens_cliente_obra i2
              JOIN funcao_imagem f2 ON f2.imagem_id = i2.idimagens_cliente_obra
              WHERE f2.colaborador_id = ?
          )
        ORDER BY data_aprovacao DESC";
  }

  // Preparar e executar a query
  $stmt = $conn->prepare($sql);
  if (!($idusuario == 1 || $idusuario == 2 || $idusuario == 9 || $idusuario == 20 || $idusuario == 3)) {
    $stmt->bind_param("i", $idcolaborador);
  }
  $stmt->execute();
  $result = $stmt->get_result();

  // Processar os resultados
  $tarefas = [];
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $tarefas[] = $row;
    }
  }

  // Retornar os resultados no formato JSON
  echo json_encode($tarefas);

  $stmt->close();
  $conn->close();
} catch (Exception $e) {
  // Retornar erro em caso de falha
  echo json_encode(['erro' => 'Erro ao executar a consulta', 'mensagem' => $e->getMessage()]);
}
