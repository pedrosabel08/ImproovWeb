<?php
// Conectar ao banco de dados
$pdo = new PDO('mysql:host=mysql.improov.com.br;dbname=improov', 'improov', 'Impr00v');

// Obter o colaborador_id da URL
$colaborador_id = $_GET['colaborador_id'];

// Preparar e executar a consulta
$stmt = $pdo->prepare("SELECT 
        DATE_FORMAT(prazo, '%Y-%m') AS mes,
        COUNT(*) AS total_funcoes,
		SUM(valor) as total_valor
    FROM 
        funcao_imagem
    WHERE 
        colaborador_id = 14
    GROUP BY 
        mes
    ORDER BY 
        mes ASC
");
$stmt->execute([$colaborador_id]);

// Obter os resultados
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retornar os resultados como JSON
header('Content-Type: application/json');
echo json_encode($resultados);
