<?php
// process_csv.php

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csvFile'])) {
    $csvFile = $_FILES['csvFile']['tmp_name'];

    // Abrir o arquivo CSV
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        // Conectar ao banco de dados
        $conn = new mysqli("mysql.improov.com.br", "improov", "Impr00v", "improov");

        // Verificar conexão
        if ($conn->connect_error) {
            die("Falha na conexão: " . $conn->connect_error);
        }

        $conn->set_charset('utf8mb4');
        // Ler cada linha do arquivo CSV
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Atribuir os dados da linha às variáveis
            $cliente_id = $data[0];      // Exemplo de categoria
            $obra_id = $data[1];         // Exemplo de descrição
            $imagem_nome = $data[2];     // Exemplo de URL ou nome da imagem
            $recebimento_arquivos = $data[3]; // Data de recebimento de arquivos
            $data_inicio = $data[4];     // Data de início (deve ser string no formato 'YYYY-MM-DD')
            $prazo = $data[5];           // Data de prazo (também string no formato 'YYYY-MM-DD')
            $tipo_imagem = $data[6];     // Tipo de imagem ou outra informação
            $status_id = $data [7];

            // Preparar a query de inserção
            $sql = $conn->prepare("INSERT INTO imagens_cliente_obra (cliente_id, obra_id, imagem_nome, recebimento_arquivos, data_inicio, prazo, tipo_imagem, status_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            // "iis" - os dois primeiros são inteiros, o terceiro a sétimo são strings
            $sql->bind_param("iisssssi", $cliente_id, $obra_id, $imagem_nome, $recebimento_arquivos, $data_inicio, $prazo, $tipo_imagem, $status_id);

            // Executar a query
            if ($sql->execute()) {
                echo "Imagem inserida: $imagem_nome<br>";
            } else {
                echo "Erro ao inserir: " . $conn->error . "<br>";
            }
        }

        // Fechar o arquivo e a conexão
        fclose($handle);
        $conn->close();
    } else {
        echo "Erro ao abrir o arquivo CSV.";
    }
}
