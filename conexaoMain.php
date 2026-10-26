<?php
// conexao.php

require_once __DIR__ . '/config/database.php';

function conectarBanco()
{
    return improov_db_connect();
}

function improov_main_table_has_column(mysqli $conn, string $table, string $column): bool
{
    $sql = "SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

function obterClientes($conn)
{
    $nomeCompletoSelect = improov_main_table_has_column($conn, 'cliente', 'nome_completo')
        ? "c.nome_completo,"
        : "NULL AS nome_completo,";

    $sql = "SELECT c.idcliente,
                   c.nome_cliente,
                   {$nomeCompletoSelect}
                   (
                       SELECT SUBSTRING_INDEX(o.nomenclatura, '_', 1)
                       FROM obra o
                       WHERE o.cliente = c.idcliente
                         AND o.nomenclatura LIKE '%\_%'
                       ORDER BY o.idobra DESC
                       LIMIT 1
                   ) AS sigla_cliente
            FROM cliente c
            ORDER BY c.nome_cliente ASC";
    $result = $conn->query($sql);
    $clientes = array();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (empty($row['sigla_cliente'])) {
                $siglaFallback = preg_replace('/[^A-Za-z]/', '', (string) ($row['nome_cliente'] ?? ''));
                $row['sigla_cliente'] = strtoupper(substr((string) $siglaFallback, 0, 3));
            }
            $clientes[] = $row;
        }
    }

    return $clientes;
}

function improov_usuario_eh_gestor_sidebar($conn): bool
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $nivel = isset($_SESSION['nivel_acesso']) ? (int) $_SESSION['nivel_acesso'] : 0;
    if (in_array($nivel, [1, 5], true)) {
        $cache = true;
        return $cache;
    }

    $idusuario = isset($_SESSION['idusuario']) ? (int) $_SESSION['idusuario'] : 0;
    $cargoSessao = mb_strtolower((string) ($_SESSION['cargo_colaborador'] ?? ''), 'UTF-8');
    foreach (['gestor', 'diretor', 'gerente'] as $termoGestao) {
        if ($cargoSessao !== '' && mb_strpos($cargoSessao, $termoGestao, 0, 'UTF-8') !== false) {
            $cache = true;
            return $cache;
        }
    }

    if ($idusuario > 0) {
        $sql = "SELECT LOWER(car.nome) AS cargo
                FROM usuario_cargo uc
                JOIN cargo car ON car.id = uc.cargo_id
                WHERE uc.usuario_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $idusuario);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $cargo = (string) ($row['cargo'] ?? '');
                foreach (['gestor', 'diretor', 'gerente'] as $termoGestao) {
                    if ($cargo !== '' && mb_strpos($cargo, $termoGestao, 0, 'UTF-8') !== false) {
                        $stmt->close();
                        $cache = true;
                        return $cache;
                    }
                }
            }
            $stmt->close();
        }
    }

    $cache = false;
    return $cache;
}

function improov_usuario_eh_animacao($conn): bool
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $nivel = isset($_SESSION['nivel_acesso']) ? (int) $_SESSION['nivel_acesso'] : 0;
    if ($nivel === 4) {
        $cache = true;
        return $cache;
    }

    $cargoSessao = mb_strtolower((string) ($_SESSION['cargo_colaborador'] ?? ''), 'UTF-8');
    if ($cargoSessao !== '' && mb_strpos($cargoSessao, 'anima', 0, 'UTF-8') !== false) {
        $cache = true;
        return $cache;
    }

    $idcolaborador = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : 0;
    if ($idcolaborador > 0) {
        $sql = "SELECT 1
                FROM funcao_colaborador fc
                JOIN funcao f ON f.idfuncao = fc.funcao_id
                WHERE fc.colaborador_id = ?
                  AND LOWER(f.nome_funcao) LIKE '%anima%'
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $idcolaborador);
            $stmt->execute();
            $result = $stmt->get_result();
            $cache = ($result && $result->num_rows > 0);
            $stmt->close();
            return $cache;
        }
    }

    $cache = false;
    return $cache;
}

function improov_sidebar_contexto_usuario($conn): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    if (improov_usuario_eh_gestor_sidebar($conn)) {
        $cache = [
            'modo' => 'gestao',
            'pacote_tipo' => null,
            'label_obras' => 'Projetos Ativos',
            'label_inativas' => 'Projetos Não Ativos',
            'exibir_inativas' => true,
        ];
        return $cache;
    }

    if (improov_usuario_eh_animacao($conn)) {
        $cache = [
            'modo' => 'animacao',
            'pacote_tipo' => 'ANIMACAO',
            'label_obras' => 'Projetos de Animação',
            'label_inativas' => '',
            'exibir_inativas' => false,
        ];
        return $cache;
    }

    $cache = [
        'modo' => 'producao',
        'pacote_tipo' => 'STILL',
        'label_obras' => 'Projetos de Produção',
        'label_inativas' => '',
        'exibir_inativas' => false,
    ];
    return $cache;
}

function improov_registrar_contexto_sidebar(array $contexto): void
{
    $GLOBALS['improov_sidebar_project_mode'] = $contexto['modo'] ?? 'producao';
    $GLOBALS['improov_sidebar_project_label'] = $contexto['label_obras'] ?? 'Obras';
    $GLOBALS['improov_sidebar_inactive_label'] = $contexto['label_inativas'] ?? 'Obras inativas';
    $GLOBALS['improov_sidebar_show_inactive'] = !empty($contexto['exibir_inativas']);
}

function improov_usuario_pode_acessar_obra($conn, int $obraId): bool
{
    if ($obraId <= 0) {
        return false;
    }

    $contexto = improov_sidebar_contexto_usuario($conn);
    if (($contexto['modo'] ?? '') === 'gestao') {
        return true;
    }

    $tipoPacote = (string) ($contexto['pacote_tipo'] ?? '');
    if ($tipoPacote === '') {
        return false;
    }

    $sql = "SELECT 1
            FROM obra_pacote
            WHERE obra_id = ?
              AND tipo = ?
              AND status = 'ATIVO'
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('is', $obraId, $tipoPacote);
    $stmt->execute();
    $result = $stmt->get_result();
    $permitido = ($result && $result->num_rows > 0);
    $stmt->close();

    return $permitido;
}

function obterObras($conn, $status = 0)
{
    // $status: 0 = obras ativas (padrão), 1 = obras inativas, 'all' = todas as obras
    $obras = array();
    $contextoSidebar = improov_sidebar_contexto_usuario($conn);
    improov_registrar_contexto_sidebar($contextoSidebar);

    if (($contextoSidebar['modo'] ?? '') !== 'gestao') {
        if ($status !== 0 && $status !== 'all') {
            return $obras;
        }

        $tipoPacote = (string) ($contextoSidebar['pacote_tipo'] ?? '');
        if ($tipoPacote === '') {
            return $obras;
        }

        $sql = "SELECT DISTINCT o.idobra, o.nome_obra, o.nomenclatura, o.status_obra
                FROM obra o
                INNER JOIN obra_pacote op ON op.obra_id = o.idobra
                WHERE op.tipo = ?
                  AND op.status = 'ATIVO'
                  AND o.status_obra = 0
                ORDER BY o.nomenclatura ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            return $obras;
        }

        $stmt->bind_param('s', $tipoPacote);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $obras[] = $row;
            }
        }

        $stmt->close();
        return $obras;
    }

    $selectObraComPacotes = "SELECT o.idobra,
                                    o.nome_obra,
                                    o.nomenclatura,
                                    o.status_obra,
                                    GROUP_CONCAT(DISTINCT op.tipo ORDER BY op.tipo SEPARATOR ',') AS pacotes_obra,
                                    GROUP_CONCAT(DISTINCT CASE WHEN op.status = 'ATIVO' THEN op.tipo END ORDER BY op.tipo SEPARATOR ',') AS pacotes_ativos
                             FROM obra o
                             LEFT JOIN obra_pacote op ON op.obra_id = o.idobra";

    if ($status === 'all') {
        $sql = $selectObraComPacotes . "
                GROUP BY o.idobra, o.nome_obra, o.nomenclatura, o.status_obra
                ORDER BY o.nomenclatura ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            return $obras;
        }
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $status_int = (int) $status;

        if ($status_int === 0) {
            $nivelAcesso = isset($_SESSION['nivel_acesso']) ? (int) $_SESSION['nivel_acesso'] : 0;
            $statusWhere = in_array($nivelAcesso, [1, 5], true)
                ? 'o.status_obra IN (0, 2)'
                : 'o.status_obra = 0';
            $sql = $selectObraComPacotes . "
                    WHERE {$statusWhere}
                    GROUP BY o.idobra, o.nome_obra, o.nomenclatura, o.status_obra
                    ORDER BY o.nomenclatura ASC";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                return $obras;
            }
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $sql = $selectObraComPacotes . "
                    WHERE o.status_obra = ?
                    GROUP BY o.idobra, o.nome_obra, o.nomenclatura, o.status_obra
                    ORDER BY o.nomenclatura ASC";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                return $obras;
            }
            $stmt->bind_param('i', $status_int);
            $stmt->execute();
            $result = $stmt->get_result();
        }
    }

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $obras[] = $row;
        }
    }

    if (isset($stmt) && $stmt) {
        $stmt->close();
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
