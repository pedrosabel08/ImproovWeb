<?php
// conexao.php

function conectarBanco()
{
    $conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

function obterClientes($conn)
{
    $sql = "SELECT idcliente, nome_cliente FROM cliente ORDER BY nome_cliente ASC";
    $result = $conn->query($sql);
    $clientes = array();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $clientes[] = $row;
        }
    }

    return $clientes;
}

function obterObras($conn)
{
    $sql = "SELECT idobra, nome_obra, nomenclatura FROM obra WHERE status_obra = 0 ORDER BY nomenclatura ASC";
    $result = $conn->query($sql);
    $obras = array();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $obras[] = $row;
        }
    }

    return $obras;
}

function obterColaboradores($conn)
{
    $sql = "SELECT idcolaborador, nome_colaborador FROM colaborador WHERE ativo = 1 ORDER BY nome_colaborador";
    $result = $conn->query($sql);
    $colaboradores = array();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $colaboradores[] = $row;
        }
    }

    return $colaboradores;
}

function obterStatusImagens($conn)
{
    $sql = "SELECT idstatus, nome_status FROM status_imagem ORDER BY idstatus";
    $result = $conn->query($sql);
    $status_imagens = array();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $status_imagens[] = $row;
        }
    }

    return $status_imagens;
}

function obterFuncoes($conn)
{
    $sql = "SELECT idfuncao, nome_funcao FROM funcao ORDER BY idfuncao";
    $result = $conn->query($sql);
    $funcoes = array();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $funcoes[] = $row;
        }
    }

    return $funcoes;
}

function oberUsuarios($conn)
{
    $sql = "SELECT idusuario, nome_usuario FROM usuario ORDER BY nome_usuario ASC";
    $result = $conn->query($sql);
    $usuarios = array();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
    }

    return $usuarios;
}

function obterImagens($conn)
{
    $sql = "SELECT idimagens_cliente_obra, imagem_nome FROM imagens_cliente_obra ORDER BY obra_id ASC";
    $result = $conn->query($sql);
    $imagens = array();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $imagens[] = $row;
        }
    }

    return $imagens;
}

function obterStatus($conn)
{
    $sql = "SELECT id, nome_substatus FROM substatus_imagem";
    $result = $conn->query($sql);
    $status = array();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $status[] = $row;
        }
    }

    return $status;
}

