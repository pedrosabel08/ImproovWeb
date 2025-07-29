<?php
// process_csv.php

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csvFile'])) {
    $csvFile = $_FILES['csvFile']['tmp_name'];

    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        $conn = new mysqli("mysql.improov.com.br", "improov", "Impr00v", "improov");

        if ($conn->connect_error) {
            die("Falha na conexão: " . $conn->connect_error);
        }

        $conn->set_charset('utf8mb4');

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $cliente_id = $data[0];
            $obra_id = $data[1];
            $imagem_nome = trim($data[2]);
            $recebimento_arquivos = $data[3];
            $data_inicio = $data[4];
            $prazo = $data[5];
            $tipo_imagem = $data[6];

            // Buscar nomenclatura da obra
            $nomenclatura = "";
            $stmtNom = $conn->prepare("SELECT nomenclatura FROM obra WHERE idobra = ? LIMIT 1");
            $stmtNom->bind_param("i", $obra_id);
            $stmtNom->execute();
            $stmtNom->bind_result($nomenclatura);
            $stmtNom->fetch();
            $stmtNom->close();

            if (!empty($nomenclatura)) {
                // Extrair o prefixo numérico + ponto
                if (preg_match('/^(\d+\.)\s*(.*)$/', $imagem_nome, $matches)) {
                    $prefixo = $matches[1]; // ex: "1."
                    $restante = $matches[2]; // ex: "Fachada embasamento"
                    $imagem_nome = $prefixo . $nomenclatura . " " . $restante;
                } else {
                    // Se não tiver número no início, apenas insere a nomenclatura
                    $imagem_nome = $nomenclatura . " " . $imagem_nome;
                }
            }
            // Inserir no banco
            $sql = $conn->prepare("INSERT INTO imagens_cliente_obra 
                (cliente_id, obra_id, imagem_nome, recebimento_arquivos, data_inicio, prazo, tipo_imagem) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $sql->bind_param("iisssss", $cliente_id, $obra_id, $imagem_nome, $recebimento_arquivos, $data_inicio, $prazo, $tipo_imagem);

            if ($sql->execute()) {
                echo "Imagem inserida: $imagem_nome<br>";
            } else {
                echo "Erro ao inserir: " . $conn->error . "<br>";
            }
        }

        fclose($handle);
        $conn->close();
    } else {
        echo "Erro ao abrir o arquivo CSV.";
    }
}
