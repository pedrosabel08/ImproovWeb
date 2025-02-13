<?php

include '../conexao.php';

if (isset($_POST["campo"], $_POST["valor"], $_POST["obraId"])) {
    $campo = $_POST["campo"];
    $valor = $_POST["valor"];
    $obraId = intval($_POST["obraId"]);

    // Lista de campos que pertencem a cada tabela
    $camposBriefing = ["assets", "comp_planta", "nivel", "conceito", "valor_media", "outro_padrao", "vidro", "esquadria"];
    $camposObra = ["link_drive", "local", "altura_drone"];

    // Determinar a tabela e a chave correta
    if (in_array($campo, $camposBriefing)) {
        $tabela = "briefing";
        $chavePrimaria = "obra_id"; // Chave usada na tabela briefing
    } elseif (in_array($campo, $camposObra)) {
        $tabela = "obra";
        $chavePrimaria = "idobra"; // Chave usada na tabela obra
    } else {
        die(json_encode(["sucesso" => false, "erro" => "Campo inválido"]));
    }

    // Preparar a consulta dinamicamente
    $sql = "UPDATE $tabela SET $campo = ? WHERE $chavePrimaria = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        die(json_encode(["sucesso" => false, "erro" => "Erro na preparação da consulta: " . $conn->error]));
    }

    $stmt->bind_param("si", $valor, $obraId);

    // Executar a consulta
    $executado = $stmt->execute();

    if ($executado) {
        echo json_encode(["sucesso" => true]);
    } else {
        echo json_encode(["sucesso" => false, "erro" => "Erro ao executar: " . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(["sucesso" => false, "erro" => "Parâmetros ausentes"]);
}

$conn->close();
