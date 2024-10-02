<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csvFile'])) {
    $csvFile = $_FILES['csvFile']['tmp_name'];

    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        $conn = new mysqli("mysql.improov.com.br", "improov", "Impr00v", "improov");

        // Verificar conexão
        if ($conn->connect_error) {
            die("Falha na conexão: " . $conn->connect_error);
        }

        $conn->set_charset('utf8mb4');
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

            $resp = $data[0];      
            $contato = $data[1];        
            $construtora = $data[2];     
            $obra = $data[3]; 
            $valor = $data[4];     
            $status = $data[5];          
            $mes = $data[6];   
            $ano = $data[7];

            $sql = $conn->prepare("INSERT INTO controle_comercial (resp, contato, construtora, obra, valor, status, mes, ano) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            $sql->bind_param("ssssdsss", $resp, $contato, $construtora, $obra, $valor, $status, $mes, $ano);

            if ($sql->execute()) {
                header("Location: index.html");
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
