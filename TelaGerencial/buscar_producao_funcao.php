<?php
include '../conexao.php'; // Inclui o arquivo de conexão com mysqli

// Verifica os parâmetros recebidos
$mes = $_GET['mes'] ?? null;
$data = $_GET['data'] ?? null;
$inicio = $_GET['inicio'] ?? null;
$fim = $_GET['fim'] ?? null;

if ($mes) {
  // Filtro por mês
  // Usamos uma subquery para calcular "nome_funcao" por linha (inclui a verificação de Pré-Finalização)
  // e então agregamos por esse nome no nível externo. Isso evita problemas com ONLY_FULL_GROUP_BY
  $sql = "SELECT 
    COUNT(*) AS quantidade,
    SUM(CASE WHEN t.pagamento = 1 THEN 1 ELSE 0 END) AS pagas,
    SUM(CASE WHEN t.pagamento <> 1 OR t.pagamento IS NULL THEN 1 ELSE 0 END) AS nao_pagas,
    t.nome_funcao,
    MIN(t.funcao_id) AS funcao_order
  FROM (
    SELECT fi.idfuncao_imagem, fi.funcao_id, fi.pagamento, fi.imagem_id, f.nome_funcao AS original_funcao, ico.status_id,
      CASE
        WHEN fi.funcao_id = 4 AND LOWER(ico.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
        WHEN fi.funcao_id = 4 AND (
          EXISTS (
            SELECT 1 FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
            WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%'
          ) OR ico.status_id = 1
        ) THEN 'Finalização Parcial'
        WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
        ELSE f.nome_funcao
      END AS nome_funcao

    FROM funcao_imagem fi
    JOIN funcao f ON f.idfuncao = fi.funcao_id
    JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    WHERE MONTH(fi.prazo) = ? AND YEAR(fi.prazo) = YEAR(CURDATE()) AND (fi.status <> 'Não iniciado' OR fi.status IS NULL)
  ) AS t
  GROUP BY t.nome_funcao
  ORDER BY
      FIELD(t.nome_funcao, 'Caderno', 'Filtro de assets', 'Modelagem', 'Composição', 'Pré-finalização', 'Finalização Parcial','Finalização Completa','Finalização de Planta Humanizada', 'Pós-produção', 'Alteração'),
    funcao_order";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $mes);
} elseif ($data) {
  // Filtro por dia específico - calcular nome_funcao por linha e agregar externamente
  $sql = "SELECT COUNT(*) AS quantidade, t.nome_funcao, MIN(t.funcao_id) AS funcao_order
    FROM (
      SELECT fi.funcao_id, fi.imagem_id, fi.pagamento, f.nome_funcao,
        CASE
          WHEN fi.funcao_id = 4 AND LOWER(ico.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
          WHEN fi.funcao_id = 4 AND (
            EXISTS (SELECT 1 FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%')
            OR ico.status_id = 1
          ) THEN 'Finalização Parcial'
          WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
          ELSE f.nome_funcao
        END AS nome_funcao
      FROM funcao_imagem fi
      JOIN funcao f ON f.idfuncao = fi.funcao_id
      JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
      WHERE DATE(fi.prazo) = ? AND (fi.status <> 'Não iniciado' OR fi.status IS NULL)
    ) AS t
    GROUP BY t.nome_funcao
    ORDER BY
      FIELD(t.nome_funcao, 'Caderno', 'Filtro de assets', 'Modelagem', 'Composição', 'Pré-finalização', 'Finalização Parcial','Finalização Completa','Finalização de Planta Humanizada', 'Pós-produção', 'Alteração'),
      funcao_order";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $data);
} elseif ($inicio && $fim) {
  // Filtro por intervalo de semana - calcular nome_funcao por linha e agregar externamente
  $sql = "SELECT COUNT(*) AS quantidade, t.nome_funcao, MIN(t.funcao_id) AS funcao_order
    FROM (
      SELECT fi.funcao_id, fi.imagem_id, fi.pagamento, f.nome_funcao,
        CASE
          WHEN fi.funcao_id = 4 AND LOWER(ico.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
          WHEN fi.funcao_id = 4 AND (
            EXISTS (SELECT 1 FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%')
            OR ico.status_id = 1
          ) THEN 'Finalização Parcial'
          WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
          ELSE f.nome_funcao
        END AS nome_funcao
      FROM funcao_imagem fi
      JOIN funcao f ON f.idfuncao = fi.funcao_id
      JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
      WHERE DATE(fi.prazo) BETWEEN ? AND ? AND (fi.status <> 'Não iniciado' OR fi.status IS NULL)
    ) AS t
    GROUP BY t.nome_funcao
    ORDER BY
      FIELD(t.nome_funcao, 'Caderno', 'Filtro de assets', 'Modelagem', 'Composição', 'Pré-finalização', 'Finalização Parcial','Finalização Completa','Finalização de Planta Humanizada', 'Pós-produção', 'Alteração'),
      funcao_order";
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
