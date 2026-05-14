<?php
require_once __DIR__ . '/../config/session_bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclua a conexão com o banco de dados
include('conexao.php');

function same_caderno_date($left, $right)
{
    $normalize = static function ($value) {
        if ($value === null || $value === '') {
            return null;
        }

        return explode(' ', trim((string) $value))[0];
    };

    return $normalize($left) === $normalize($right);
}

// Verifique se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coletando dados do formulário
    $status = $_POST['status'];
    $prazo = $_POST['prazo'];
    $idfuncao_imagem = (int) $_POST['idfuncao_imagem'];
    $actorColaboradorId = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null;
    $actorUsuarioId = isset($_SESSION['idusuario']) ? (int) $_SESSION['idusuario'] : null;

    $prazoAnterior  = null;
    $statusAnterior = null;
    $stmtCurrentPrazo = $conn->prepare("SELECT prazo, status FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1");
    $stmtCurrentPrazo->bind_param('i', $idfuncao_imagem);
    $stmtCurrentPrazo->execute();
    $rowAtual = $stmtCurrentPrazo->get_result()->fetch_assoc();
    $stmtCurrentPrazo->close();

    if ($rowAtual) {
        $prazoAnterior  = $rowAtual['prazo']   ?? null;
        $statusAnterior = $rowAtual['status']  ?? null;
    }

    $conn->begin_transaction();

    // Consulta de atualização (UPDATE)
    $sql = "UPDATE funcao_imagem 
            SET status = ?, prazo = ?
            WHERE idfuncao_imagem = ?";

    // Preparando a consulta para evitar SQL Injection
    if ($stmt = $conn->prepare($sql)) {
        // Vinculando os parâmetros
        $stmt->bind_param('ssi', $status, $prazo, $idfuncao_imagem);

        // Executa a consulta
        if ($stmt->execute()) {
            if (!same_caderno_date($prazoAnterior, $prazo)) {
                $origem = 'arquitetura_caderno';
                $stmtHistory = $conn->prepare(
                    "INSERT INTO funcao_imagem_prazo_historico (
                        funcao_imagem_id,
                        prazo_anterior,
                        prazo_novo,
                        alterado_por_colaborador_id,
                        alterado_por_usuario_id,
                        origem,
                        motivo,
                        status_anterior,
                        status_novo
                    ) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?)"
                );
                $stmtHistory->bind_param(
                    'issiisss',
                    $idfuncao_imagem,
                    $prazoAnterior,
                    $prazo,
                    $actorColaboradorId,
                    $actorUsuarioId,
                    $origem,
                    $statusAnterior,
                    $status
                );
                $stmtHistory->execute();
                $stmtHistory->close();
            }

            $conn->commit();
            echo "Atualização feita com sucesso!";
        } else {
            $conn->rollback();
            echo "Erro ao atualizar: " . $stmt->error;
        }

        // Fecha a declaração
        $stmt->close();
    } else {
        $conn->rollback();
        echo "Erro de preparação da consulta: " . $conn->error;
    }
}

// Fecha a conexão
$conn->close();
