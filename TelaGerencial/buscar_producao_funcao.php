<?php
include '../conexao.php'; // Inclui o arquivo de conexão com mysqli

// Verifica os parâmetros recebidos
$mes = $_GET['mes'] ?? null;
$data = $_GET['data'] ?? null;
$inicio = $_GET['inicio'] ?? null;
$fim = $_GET['fim'] ?? null;

if ($mes) {
  // Filtro por mês
  $sql = "SELECT 
    COUNT(*) AS quantidade,
    SUM(CASE WHEN fi.pagamento = 1 THEN 1 ELSE 0 END) AS pagas,
    SUM(CASE WHEN fi.pagamento <> 1 OR fi.pagamento IS NULL THEN 1 ELSE 0 END) AS nao_pagas,
    CASE 
            WHEN fi.funcao_id = 4 THEN 
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                            FROM funcao_imagem fi_sub
                            JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                            WHERE fi_sub.imagem_id = fi.imagem_id 
                            AND f_sub.nome_funcao = 'Pré-Finalização'
                        ) OR ico.status_id = 1
                        THEN 'Finalização Parcial'
                        ELSE 'Finalização Completa'
                    END 
            ELSE f.nome_funcao 
        END AS nome_funcao
FROM funcao_imagem fi
JOIN funcao f ON f.idfuncao = fi.funcao_id
JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
WHERE MONTH(fi.prazo) = ? 
  AND YEAR(fi.prazo) = YEAR(CURDATE())
  AND (fi.status <> 'Não iniciado' OR fi.status IS NULL)
GROUP BY 
  CASE 
    WHEN fi.funcao_id = 4 THEN 
      CASE 
        WHEN EXISTS (
            SELECT 1 
            FROM funcao_imagem fi_sub
            JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
            WHERE fi_sub.imagem_id = fi.imagem_id 
              AND f_sub.nome_funcao = 'Pré-Finalização'
        ) OR ico.status_id = 1
        THEN 'Finalização Parcial'
        ELSE 'Finalização Completa'
      END
    ELSE f.nome_funcao 
  END

ORDER BY FIELD(fi.funcao_id, 1, 8, 2, 3, 9, 4, 5, 6, 7)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $mes);
} elseif ($data) {
  // Filtro por dia específico
  $sql = "SELECT 
                f.nome_funcao, 
                COUNT(*) AS quantidade
            FROM funcao_imagem fi 
            JOIN funcao f ON f.idfuncao = fi.funcao_id
            WHERE DATE(fi.prazo) = ? 
                AND (status <> 'Não iniciado' OR status IS NULL)
            GROUP BY f.nome_funcao,
           ORDER BY FIELD(funcao_id, 1, 8, 2, 3, 9, 4, 5, 6, 7)";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $data);
} elseif ($inicio && $fim) {
  // Filtro por intervalo de semana
  $sql = "SELECT 
                f.nome_funcao, 
                COUNT(*) AS quantidade
            FROM funcao_imagem fi 
            JOIN funcao f ON f.idfuncao = fi.funcao_id
            WHERE DATE(fi.prazo) BETWEEN ? AND ? 
                AND (status <> 'Não iniciado' OR status IS NULL)
            GROUP BY f.nome_funcao,
            ORDER BY FIELD(funcao_id, 1, 8, 2, 3, 9, 4, 5, 6, 7)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ss", $inicio, $fim);
} else {
  // Caso nenhum parâmetro válido seja enviado
  echo json_encode(["error" => "Parâmetros inválidos"]);
  exit;
}

// Executa a consulta
$stmt->execute();
$result = $stmt->get_result();
$dados = $result->fetch_all(MYSQLI_ASSOC);

// Retorna os dados em formato JSON
echo json_encode($dados);
