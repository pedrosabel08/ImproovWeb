<?php
// Conectar ao banco de dados
include '../conexao.php';

session_start();

// Lê os dados enviados via POST (JSON)
$data = json_decode(file_get_contents("php://input"), true);

// Verifica se os dados existem
if ($data && isset($data['imagem_id'])) {
    $imagem_id = $data['imagem_id'];
    $colaborador_id = $data['colaborador_id'];
    $responsavel_id = $_SESSION['idcolaborador'] ?? null;
    $obra_id = $data['obra_id'] ?? null;

    // Inicia uma transação para garantir que todas as operações ocorram corretamente
    $conn->begin_transaction();

    try {
        // 1. Primeiro, conta quantas alterações já existem para essa imagem ANTES de inserir a nova
        $stmt1 = $conn->prepare("SELECT COUNT(*) as total FROM alteracoes WHERE imagem_id = ?");
        $stmt1->bind_param("i", $imagem_id);
        $stmt1->execute();
        $result = $stmt1->get_result();
        $row = $result->fetch_assoc();
        $total_alteracoes = $row['total'];
        $stmt1->close();

        // 2. Definir o novo status com base na contagem ATUAL (antes da nova inserção)
        if ($total_alteracoes == 0) {
            $novo_status = 3;
            $numero_revisao = 1;
        } elseif ($total_alteracoes == 1) {
            $novo_status = 4;
            $numero_revisao = 2;
        } elseif ($total_alteracoes == 2) {
            $novo_status = 5;
            $numero_revisao = 3;
        } elseif ($total_alteracoes == 3) {
            $novo_status = 14;
            $numero_revisao = 4;
        } elseif ($total_alteracoes == 4) {
            $novo_status = 15;
            $numero_revisao = 5;
        } else {
            $novo_status = 15;
            $numero_revisao = 5;
        }

        // Função para adicionar dias úteis (segunda a sexta-feira)
        function adicionarDiasUteis($dataInicial, $diasUteis)
        {
            $diasAdicionados = 0;
            $data = strtotime($dataInicial);

            // Lista de feriados fixos (formato MM-DD)
            $feriadosFixos = [
                '01-01', // Confraternização Universal
                '04-21', // Tiradentes
                '05-01', // Dia do Trabalho
                '09-07', // Independência do Brasil
                '10-12', // Nossa Senhora Aparecida
                '11-02', // Finados
                '11-15', // Proclamação da República
                '12-25', // Natal
            ];

            while ($diasAdicionados < $diasUteis) {
                $data = strtotime("+1 day", $data);
                $diaSemana = date('N', $data);
                $mesDia = date('m-d', $data);
                $ano = date('Y', $data);

                // Verifica se é final de semana
                if ($diaSemana >= 6) continue;

                // Verifica se é feriado fixo
                if (in_array($mesDia, $feriadosFixos)) continue;

                // Verifica se é feriado móvel (tipo Páscoa, Corpus Christi...)
                if (in_array(date('Y-m-d', $data), feriadosMoveis($ano))) continue;

                $diasAdicionados++;
            }

            return date('Y-m-d', $data);
        }

        function feriadosMoveis($ano)
        {
            // Cálculo da Páscoa
            $pascoa = easter_date($ano);
            $dataPascoa = date('Y-m-d', $pascoa);

            // Feriados móveis com base na Páscoa
            $feriados = [
                $dataPascoa, // Páscoa
                date('Y-m-d', strtotime('-2 days', $pascoa)), // Sexta-feira Santa
                date('Y-m-d', strtotime('+60 days', $pascoa)), // Corpus Christi
                date('Y-m-d', strtotime('+47 days', $pascoa)), // Ascensão
                date('Y-m-d', strtotime('-48 days', $pascoa)), // Carnaval (terça-feira)
                date('Y-m-d', strtotime('-49 days', $pascoa)), // Segunda de Carnaval (opcional)
            ];

            return $feriados;
        }

        // 1. Obtém a data atual
        $dataAtual = date('Y-m-d');

        // 2. Calcula o novo prazo com 7 dias úteis
        $novoPrazo = adicionarDiasUteis($dataAtual, 7);

        // 3. Prepara o UPDATE com a data calculada
        $stmt2 = $conn->prepare("UPDATE imagens_cliente_obra SET status_id = ?, prazo = ? WHERE idimagens_cliente_obra = ?");
        if (!$stmt2) {
            die(json_encode([
                'status' => 'erro',
                'message' => 'Erro ao preparar a query UPDATE: ' . $conn->error
            ]));
        }
        $stmt2->bind_param("isi", $novo_status, $novoPrazo, $imagem_id);
        $stmt2->execute();
        $stmt2->close();

        // Insere o evento na tabela eventos_obra

        switch ($novo_status) {
            case 3:
                $nome_status = 'R01';
                break;
            case 4:
                $nome_status = 'R02';
                break;
            case 5:
                $nome_status = 'R03';
                break;
            case 14:
                $nome_status = 'R04';
                break;
            case 15:
                $nome_status = 'R05';
                break;
            default:
                $nome_status = 'Desconhecido';
                break;
        }

        $descricao = "Entrega Alteração ($nome_status)";
        $tipo_evento = "Entrega";

        $stmt3 = $conn->prepare("INSERT INTO eventos_obra (descricao, data_evento, tipo_evento, obra_id, responsavel_id) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt3) {
            die(json_encode([
                'status' => 'erro',
                'message' => 'Erro ao preparar o INSERT: ' . $conn->error
            ]));
        }
        $stmt3->bind_param("sssii", $descricao, $novoPrazo, $tipo_evento, $obra_id, $responsavel_id);
        $stmt3->execute();
        $stmt3->close();


        // 4. Agora insere a nova alteração na tabela alteracoes
        $stmt4 = $conn->prepare("INSERT INTO alteracoes (imagem_id, colaborador_id, numero_revisao) VALUES (?, ?, ?)");
        $stmt4->bind_param("iii", $imagem_id, $colaborador_id, $numero_revisao);
        $stmt4->execute();
        $stmt4->close();

        // Confirma a transação
        $conn->commit();

        echo json_encode([
            'status' => 'sucesso',
            'message' => "Imagem ID '$imagem_id' status atualizado para '$novo_status' e alteração registrada. Descrição: '$descricao'"
        ]);
    } catch (Exception $e) {
        // Se ocorrer um erro, desfaz a transação
        $conn->rollback();

        echo json_encode([
            'status' => 'erro',
            'message' => 'Erro ao executar as consultas: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'erro',
        'message' => 'Dados incompletos ou inválidos.'
    ]);
}

// Fecha a conexão
$conn->close();
