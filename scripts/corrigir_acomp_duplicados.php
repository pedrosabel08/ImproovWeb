<?php
// Script: corrigir_acomp_duplicados.php
// Uso (CLI): php corrigir_acomp_duplicados.php --obra=123 [--dry-run] [--confirm]
// --dry-run: apenas mostra o que seria alterado
// --confirm: aplica as alterações (sem --confirm não faz alterações)

chdir(__DIR__ . '/..');
require_once __DIR__ . '/../conexao.php';

$opts = getopt('', ['obra:', 'dry-run', 'confirm']);

if (PHP_SAPI !== 'cli') {
    echo "Este script deve ser executado via CLI.\n";
}

if (empty($opts['obra'])) {
    echo "Uso: php corrigir_acomp_duplicados.php --obra=ID [--dry-run] [--confirm]\n";
    exit(1);
}

$obra_id = intval($opts['obra']);
$dryRun = isset($opts['dry-run']);
$confirm = isset($opts['confirm']);

echo "Obra ID: $obra_id\n";
echo $dryRun ? "Modo: dry-run (sem alterações)\n" : ($confirm ? "Modo: aplicando alterações\n" : "Modo: simulação (use --confirm para aplicar)\n");

$summary = ['groups_found' => 0, 'groups_processed' => 0, 'merged' => 0, 'deleted_rows' => 0];

$summary_special = ['pdf_groups_found' => 0, 'pdf_groups_processed' => 0];

// Primeiro: detectar grupos do tipo "Adicionado PDF para ..." na mesma data (vários tipos)
$sql_pdf = "SELECT `data`, COUNT(*) AS cnt FROM acompanhamento_email WHERE obra_id = ? AND tipo = 'arquivo' AND assunto LIKE 'Adicionado PDF para %' GROUP BY `data` HAVING cnt > 1";
$stmt_pdf = $conn->prepare($sql_pdf);
if ($stmt_pdf) {
    $stmt_pdf->bind_param('i', $obra_id);
    $stmt_pdf->execute();
    $res_pdf = $stmt_pdf->get_result();
    $pdf_groups = $res_pdf->fetch_all(MYSQLI_ASSOC);
    $summary_special['pdf_groups_found'] = count($pdf_groups);

    foreach ($pdf_groups as $pg) {
        $data_pdf = $pg['data'];
        echo "\n[PDF GROUP] data={$data_pdf} | count={$pg['cnt']}\n";

        // buscar todas as linhas deste dia com assunto 'Adicionado PDF para %'
        $qpdf = "SELECT * FROM acompanhamento_email WHERE obra_id = ? AND tipo = 'arquivo' AND `data` = ? AND assunto LIKE 'Adicionado PDF para %'";
        $sp = $conn->prepare($qpdf);
        if (!$sp) {
            echo "  Falha preparando select pdf group: " . $conn->error . "\n";
            continue;
        }
        $sp->bind_param('is', $obra_id, $data_pdf);
        $sp->execute();
        $rp = $sp->get_result();
        $rows_pdf = $rp->fetch_all(MYSQLI_ASSOC);
        if (count($rows_pdf) <= 1) {
            echo "  Apenas uma linha no pdf group — pular.\n";
            continue;
        }

        // detectar pk field
        $pkFieldPdf = null;
        $sampleKeysPdf = array_keys($rows_pdf[0]);
        foreach ($sampleKeysPdf as $k) {
            $lk = strtolower($k);
            if (strpos($lk, 'colaborador_id') !== false) continue;
            if ($lk === 'arquivo_id') continue;
            if (strpos($lk, 'id') !== false) {
                $pkFieldPdf = $k;
                break;
            }
        }
        if ($pkFieldPdf === null) $pkFieldPdf = $sampleKeysPdf[0];

        // ordenar e escolher master
        usort($rows_pdf, function ($a, $b) use ($pkFieldPdf) {
            return intval($a[$pkFieldPdf]) - intval($b[$pkFieldPdf]);
        });
        $masterPdf = $rows_pdf[0];
        $othersPdf = array_slice($rows_pdf, 1);

        // coletar arquivo_ids
        $arquivoIdsPdf = [];
        foreach ($rows_pdf as $rw) {
            if (!empty($rw['arquivo_id']) && is_numeric($rw['arquivo_id'])) $arquivoIdsPdf[] = intval($rw['arquivo_id']);
        }
        $arquivoIdsPdf = array_values(array_unique($arquivoIdsPdf));

        $arquivoParaManterPdf = count($arquivoIdsPdf) ? $arquivoIdsPdf[0] : null;

        echo "  master {$pkFieldPdf}={$masterPdf[$pkFieldPdf]} | arquivo_ids: " . json_encode($arquivoIdsPdf) . "\n";
        echo "  Linhas a deletar: " . json_encode(array_map(function ($r) use ($pkFieldPdf) {
            return intval($r[$pkFieldPdf]);
        }, $othersPdf)) . "\n";

        if ($dryRun || !$confirm) {
            echo "  [DRY-RUN] Não aplicando alterações neste pdf group. Use --confirm para aplicar.\n";
            $summary_special['pdf_groups_processed']++;
            continue;
        }

        // atualizar assunto do master para genérico
        $novoAssunto = 'Adicionado PDF para (todos os tipos)';
        $updPdf = $conn->prepare("UPDATE acompanhamento_email SET assunto = ?, arquivo_id = ? WHERE $pkFieldPdf = ?");
        if ($updPdf) {
            $aid = $arquivoParaManterPdf ?? null;
            $updPdf->bind_param('sii', $novoAssunto, $aid, $masterPdf[$pkFieldPdf]);
            if ($updPdf->execute()) {
                echo "  Master atualizado: assunto -> '$novoAssunto'" . ($aid ? ", arquivo_id=$aid" : "") . "\n";
            } else {
                echo "  Falha update master pdf: " . $updPdf->error . "\n";
            }
            $updPdf->close();
        } else {
            echo "  Falha preparando UPDATE master pdf: " . $conn->error . "\n";
        }

        // deletar os outros
        $toDelPdf = implode(',', array_map(function ($r) use ($pkFieldPdf) {
            return intval($r[$pkFieldPdf]);
        }, $othersPdf));
        if (!empty($toDelPdf)) {
            $delSqlPdf = "DELETE FROM acompanhamento_email WHERE $pkFieldPdf IN ($toDelPdf)";
            if ($conn->query($delSqlPdf)) {
                echo "  Deletadas " . $conn->affected_rows . " linhas duplicadas (pdf group).\n";
                $summary['deleted_rows'] += $conn->affected_rows;
                $summary['merged']++;
            } else {
                echo "  Falha delete pdf group: " . $conn->error . "\n";
            }
        }

        $summary_special['pdf_groups_processed']++;
    }
    $stmt_pdf->close();
}

// Buscar grupos com mesmo data e descricao
$sql = "SELECT `data`, assunto, COUNT(*) AS cnt FROM acompanhamento_email WHERE obra_id = ? AND tipo = 'arquivo' GROUP BY `data`, assunto HAVING cnt > 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "Erro preparando consulta: " . $conn->error . "\n";
    exit(1);
}
$stmt->bind_param('i', $obra_id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res) {
    echo "Erro na consulta: " . $conn->error . "\n";
    exit(1);
}

$groups = $res->fetch_all(MYSQLI_ASSOC);
$summary['groups_found'] = count($groups);

if ($summary['groups_found'] === 0) {
    echo "Nenhum acompanhamento duplicado encontrado para obra $obra_id.\n";
    exit(0);
}

foreach ($groups as $g) {
    $data = $g['data'];
    $assunto = $g['assunto'];

    echo "\nGrupo: data={$data} | assunto='" . ($assunto === null ? 'NULL' : $assunto) . "' | count={$g['cnt']}\n";

    // Seleciona todas as linhas deste grupo
    $q2 = "SELECT * FROM acompanhamento_email WHERE obra_id = ? AND tipo = 'arquivo' AND `data` = ? AND assunto = ?";
    $s2 = $conn->prepare($q2);
    if (!$s2) {
        echo "Falha preparando select do grupo: " . $conn->error . "\n";
        continue;
    }
    $s2->bind_param('iss', $obra_id, $data, $assunto);
    $s2->execute();
    $r2 = $s2->get_result();
    $rows = $r2->fetch_all(MYSQLI_ASSOC);
    if (count($rows) <= 1) {
        echo "  Apenas uma linha encontrada — pular.\n";
        continue;
    }

    // Detectar campo de PK na tabela (procura por chave que contenha 'id' e não seja 'colaborador_id'/'arquivo_id')
    $pkField = null;
    $sampleKeys = array_keys($rows[0]);
    foreach ($sampleKeys as $k) {
        $lk = strtolower($k);
        if (strpos($lk, 'colaborador_id') !== false) continue;
        if ($lk === 'arquivo_id') continue;
        if (strpos($lk, 'id') !== false) {
            $pkField = $k;
            break;
        }
    }
    if ($pkField === null) {
        // fallback para a primeira coluna
        $pkField = $sampleKeys[0];
    }

    echo "  PK detectada: $pkField\n";

    // Ordena por pk asc e escolhe o primeiro como master
    usort($rows, function ($a, $b) use ($pkField) {
        return intval($a[$pkField]) - intval($b[$pkField]);
    });

    $master = $rows[0];
    $others = array_slice($rows, 1);

    // Coletar arquivo_ids válidos
    $arquivoIds = [];
    foreach ($rows as $rw) {
        if (!empty($rw['arquivo_id']) && is_numeric($rw['arquivo_id'])) {
            $arquivoIds[] = intval($rw['arquivo_id']);
        }
    }
    $arquivoIds = array_values(array_unique($arquivoIds));

    echo "  master {$pkField}={$master[$pkField]} | arquivo_ids: " . json_encode($arquivoIds) . "\n";

    // Decidir arquivo a manter (primeiro não-nulo) apenas se existir
    $arquivoParaManter = count($arquivoIds) ? $arquivoIds[0] : null;

    if ($arquivoParaManter !== null) {
        echo "  Arquivo para manter: $arquivoParaManter\n";
    } else {
        echo "  Nenhum arquivo_id presente no grupo — manter master sem arquivo_id.\n";
    }

    // Preparar operações
    $toDeleteIds = array_map(function ($r) use ($pkField) {
        return intval($r[$pkField]);
    }, $others);

    echo "  Linhas a deletar: " . json_encode($toDeleteIds) . "\n";

    if ($dryRun || !$confirm) {
        echo "  [DRY-RUN] Não aplicando alterações. Use --confirm para aplicar.\n";
        $summary['groups_processed']++;
        continue;
    }

    // Aplica a atualização no master se necessário
    if ($arquivoParaManter !== null) {
        $upd = $conn->prepare("UPDATE acompanhamento_email SET arquivo_id = ? WHERE $pkField = ?");
        if ($upd) {
            $upd->bind_param('ii', $arquivoParaManter, $master[$pkField]);
            if ($upd->execute()) {
                echo "  Master atualizado: arquivo_id = $arquivoParaManter\n";
            } else {
                echo "  Falha update master: " . $upd->error . "\n";
            }
            $upd->close();
        } else {
            echo "  Falha preparando UPDATE master: " . $conn->error . "\n";
        }
    }

    // Deletar linhas duplicadas (others)
    $inList = implode(',', array_map('intval', $toDeleteIds));
    if (!empty($inList)) {
        $delSql = "DELETE FROM acompanhamento_email WHERE $pkField IN ($inList)";
        if ($conn->query($delSql)) {
            echo "  Deletadas " . $conn->affected_rows . " linhas duplicadas.\n";
            $summary['deleted_rows'] += $conn->affected_rows;
            $summary['merged']++;
        } else {
            echo "  Falha delete: " . $conn->error . "\n";
        }
    }

    $summary['groups_processed']++;
}

echo "\nResumo: " . json_encode($summary) . "\n";

$conn->close();

exit(0);
