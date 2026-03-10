<?php

/**
 * recalcular_valores_mes.php
 *
 * Atualiza funcao_imagem.valor para todos os registros cujo prazo
 * está no mês/ano informados (padrão: mês atual).
 *
 * Uso (CLI):
 *   php scripts/recalcular_valores_mes.php             # mês atual
 *   php scripts/recalcular_valores_mes.php 2026 3      # março 2026
 *
 * Uso (web / dry-run):
 *   GET /scripts/recalcular_valores_mes.php?ano=2026&mes=3&dry=1
 *
 * Flags:
 *   --dry  (CLI) ou ?dry=1 (web)  → apenas mostra o que seria alterado, sem gravar.
 *   --force (CLI) ou ?force=1     → recalcula TODOS os registros, inclusive os que
 *                                   já têm valor correto. Por padrão só processa
 *                                   linhas com valor IS NULL ou valor = 0.
 */

// ─── Proteção mínima em modo web ─────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    // Aceita acesso apenas de localhost em modo web
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remoteIp, ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        exit('Acesso negado.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/../conexao.php';

// ─── Parâmetros ───────────────────────────────────────────────────────────────
if (PHP_SAPI === 'cli') {
    // Robust CLI parsing: accept flags anywhere and numeric positional args
    $ano = null;
    $mes = null;
    $dryRun = false;
    $force = false;

    // iterate argv tokens (skip argv[0])
    for ($i = 1; $i < count($argv); $i++) {
        $token = $argv[$i];
        if ($token === '--dry' || $token === '-d') {
            $dryRun = true;
            continue;
        }
        if ($token === '--force' || $token === '-f') {
            $force = true;
            continue;
        }
        // numeric token: decide if year or month
        if (is_numeric($token)) {
            $num = (int)$token;
            // year: 4-digit or > 1900
            if ($num >= 1900 && $ano === null) {
                $ano = $num;
                continue;
            }
            // month candidate
            if ($num >= 1 && $num <= 12 && $mes === null) {
                $mes = $num;
                continue;
            }
            // fallback: if year missing and token looks like year
            if ($ano === null && strlen($token) === 4) {
                $ano = $num;
                continue;
            }
        }
    }

    if ($ano === null) $ano = (int)date('Y');
    if ($mes === null) $mes = (int)date('n');
} else {
    $ano    = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
    $mes    = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');
    $dryRun = !empty($_GET['dry']);
    $force  = !empty($_GET['force']);
}

if ($mes < 1 || $mes > 12 || $ano < 2020 || $ano > 2100) {
    fwrite(STDERR, "Mês ou ano inválido.\n");
    exit(1);
}

$nomeMes = [
    '',
    'Janeiro',
    'Fevereiro',
    'Março',
    'Abril',
    'Maio',
    'Junho',
    'Julho',
    'Agosto',
    'Setembro',
    'Outubro',
    'Novembro',
    'Dezembro'
][$mes];

echo "=================================================================\n";
echo " Recalcular valores — {$nomeMes}/{$ano}" . ($dryRun ? ' [DRY RUN]' : '') . "\n";
echo "=================================================================\n\n";

// ─── Função helper: valor de Planta Humanizada a partir do nome da imagem ────
function valorPlantaHumanizada(string $nomeImagem): float
{
    $n = mb_strtolower($nomeImagem, 'UTF-8');
    if (str_contains($n, 'lazer') || str_contains($n, 'implanta')) {
        return 200.00;
    }
    if (str_contains($n, 'pavimento') && (str_contains($n, 'repeti') || str_contains($n, 'varia'))) {
        return 80.00;
    }
    if (str_contains($n, 'pavimento') || str_contains($n, 'garagem')) {
        return 150.00;
    }
    if (str_contains($n, 'varia')) {
        return 80.00;
    }
    return 130.00;
}

// ─── Buscar registros do mês ──────────────────────────────────────────────────
$whereValor = $force ? '' : ' AND (fi.valor IS NULL OR fi.valor = 0)';

$sql = "
    SELECT
        fi.idfuncao_imagem,
        fi.colaborador_id,
        fi.funcao_id,
        fi.valor         AS valor_atual,
        ico.imagem_nome
    FROM funcao_imagem fi
    JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
    WHERE fi.colaborador_id IS NOT NULL
      AND YEAR(fi.prazo)  = ?
      AND MONTH(fi.prazo) = ?
      {$whereValor}
    ORDER BY fi.idfuncao_imagem
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    fwrite(STDERR, "Erro ao preparar SELECT: " . $conn->error . "\n");
    exit(1);
}
$stmt->bind_param('ii', $ano, $mes);
$stmt->execute();
$result = $stmt->get_result();
$registros = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total      = count($registros);
$atualizados = 0;
$pulados     = 0;
$erros       = 0;

echo "Registros encontrados: {$total}\n\n";

if ($total === 0) {
    echo "Nenhum registro para processar.\n";
    $conn->close();
    exit(0);
}

// ─── Prepara queries reutilizáveis ────────────────────────────────────────────
$stmtValorFC = $conn->prepare(
    "SELECT valor FROM funcao_colaborador WHERE colaborador_id = ? AND funcao_id = ? LIMIT 1"
);
if (!$stmtValorFC) {
    fwrite(STDERR, "Erro ao preparar SELECT funcao_colaborador: " . $conn->error . "\n");
    exit(1);
}

$stmtUpdate = $conn->prepare(
    "UPDATE funcao_imagem SET valor = ? WHERE idfuncao_imagem = ?"
);
if (!$stmtUpdate) {
    fwrite(STDERR, "Erro ao preparar UPDATE: " . $conn->error . "\n");
    exit(1);
}

// ─── Processar cada registro ──────────────────────────────────────────────────
printf(
    "%-8s %-5s %-5s %-12s %-12s %s\n",
    'ID_FI',
    'COLAB',
    'FUNC',
    'VALOR_ATUAL',
    'VALOR_NOVO',
    'STATUS'
);
echo str_repeat('-', 70) . "\n";

foreach ($registros as $row) {
    $id           = (int)$row['idfuncao_imagem'];
    $colaborId    = (int)$row['colaborador_id'];
    $funcaoId     = (int)$row['funcao_id'];
    $valorAtual   = $row['valor_atual'];
    $nomeImagem   = (string)$row['imagem_nome'];

    // Calcula valor novo
    $valorNovo = null;

    if ($funcaoId === 7) {
        // Planta Humanizada: derivado do nome da imagem
        $valorNovo = valorPlantaHumanizada($nomeImagem);
    } else {
        $stmtValorFC->bind_param('ii', $colaborId, $funcaoId);
        $stmtValorFC->execute();
        $resFC = $stmtValorFC->get_result();
        $rowFC = $resFC->fetch_assoc();
        $resFC->free();
        if ($rowFC !== null && $rowFC['valor'] !== null) {
            $valorNovo = (float)$rowFC['valor'];
        }
    }

    if ($valorNovo === null) {
        printf(
            "%-8d %-5d %-5d %-12s %-12s %s\n",
            $id,
            $colaborId,
            $funcaoId,
            $valorAtual ?? 'NULL',
            'NULL',
            'PULADO (sem valor em funcao_colaborador)'
        );
        $pulados++;
        continue;
    }

    $statusStr = $dryRun ? 'DRY' : 'OK';

    if (!$dryRun) {
        $stmtUpdate->bind_param('di', $valorNovo, $id);
        if (!$stmtUpdate->execute()) {
            printf(
                "%-8d %-5d %-5d %-12s %-12s %s\n",
                $id,
                $colaborId,
                $funcaoId,
                $valorAtual ?? 'NULL',
                number_format($valorNovo, 2, '.', ''),
                'ERRO: ' . $stmtUpdate->error
            );
            $erros++;
            continue;
        }
        $statusStr = ($stmtUpdate->affected_rows > 0) ? 'ATUALIZADO' : 'SEM MUDANÇA';
    }

    printf(
        "%-8d %-5d %-5d %-12s %-12s %s\n",
        $id,
        $colaborId,
        $funcaoId,
        $valorAtual ?? 'NULL',
        number_format($valorNovo, 2, '.', ''),
        $statusStr
    );

    $atualizados++;
}

$stmtValorFC->close();
$stmtUpdate->close();
$conn->close();

// ─── Resumo ───────────────────────────────────────────────────────────────────
echo "\n" . str_repeat('=', 70) . "\n";
echo " Resumo:\n";
echo "   Processados : {$total}\n";
echo "   " . ($dryRun ? 'Seriam atualizados' : 'Atualizados') . " : {$atualizados}\n";
echo "   Pulados (sem valor definido): {$pulados}\n";
if ($erros > 0) {
    echo "   Erros       : {$erros}\n";
}
echo str_repeat('=', 70) . "\n";
