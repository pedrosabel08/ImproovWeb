<?php

include '../conexao.php';

if (isset($_POST["campo"], $_POST["valor"], $_POST["obraId"])) {
    $campo = $_POST["campo"];
    $valor = $_POST["valor"];
    $obraId = intval($_POST["obraId"]);

    // Preparar a consulta
    $sql = "UPDATE briefing SET $campo = ? WHERE obra_id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        die(json_encode(["sucesso" => false, "erro" => "Erro na preparação da consulta: " . $conn->error]));
    }

    // Verificar se é checkbox (espera-se 1 ou 0) ou texto
    if (in_array($campo, ["assets", "comp_planta"])) {
        // Se for checkbox, o valor será um número (1 ou 0)
        $stmt->bind_param("ii", $valor, $obraId); // "ii" para dois inteiros
    } else {
        // Caso contrário, o valor será texto
        $stmt->bind_param("si", $valor, $obraId); // "si" para string e inteiro
    }

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
