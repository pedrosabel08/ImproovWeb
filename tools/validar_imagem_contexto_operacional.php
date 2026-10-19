<?php
/** CLI-only, read-only validation against the configured Flow database. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/imagem_contexto_operacional_helper.php';
$conn = conectarBanco();
$conn->query('SET SESSION TRANSACTION READ ONLY');
$conn->begin_transaction();
try {
    $cases = ['A' => 3246, 'B' => 3250, 'C' => 3248, 'D' => 3255, 'E' => 3260, 'PLANEJADO' => 3133];
    $contexts = flow_imagens_contextos_operacionais_lote($conn, array_values($cases));
    if (count($contexts[3246]['etapas_ativas']) !== 1) throw new RuntimeException('Caso A mudou: revisar amostra.');
    if (count($contexts[3250]['etapas_concluidas']) !== count($contexts[3250]['etapas'])) throw new RuntimeException('Caso B mudou: revisar amostra.');
    if (!array_filter($contexts[3248]['etapas'], static fn ($s) => $s['status'] === 'HOLD' && $s['estado_visual'] === 'BLOQUEADA')) throw new RuntimeException('Caso C mudou: revisar amostra.');
    if (!array_filter($contexts[3255]['etapas'], static fn ($s) => $s['status'] === 'Ajuste' && $s['estado_visual'] === 'ATIVA' && $s['execucao_individual']['conclusao_real'] === null)) throw new RuntimeException('Caso D mudou: revisar amostra.');
    if (count($contexts[3260]['etapas_ativas']) < 2) throw new RuntimeException('Caso E mudou: revisar amostra.');
    $examples = ['gerado_em' => date(DATE_ATOM), 'fonte' => 'Banco Flow, transação somente leitura', 'casos' => []];
    foreach ($cases as $case => $imageId) {
        $context = $contexts[$imageId];
        $examples['casos'][$case] = $context;
        echo "$case | $imageId | {$context['imagem']['imagem_nome']}\n";
        foreach ($context['etapas'] as $stage) {
            echo json_encode(array_intersect_key($stage, array_flip(['funcao_imagem_id', 'funcao_nome', 'status', 'responsavel_nome', 'prazo', 'estado_visual', 'avisos'])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
        }
        echo json_encode($context['resumo']), "\n";
    }
    foreach ($contexts as $context) {
        foreach ($context['etapas'] as $stage) {
            $expected = flow_execucao_resumir_tarefa(['tarefa_id' => $stage['funcao_imagem_id'], 'status' => $stage['status']], []);
            if ($stage['participa_operacao'] && (($stage['estado_visual'] === 'CONCLUIDA') !== $expected['concluida'])) throw new RuntimeException('Conclusão individual divergiu da regra existente.');
            if (isset($stage['contexto_planejamento']['timeline'])) throw new RuntimeException('Timeline coletiva exposta por engano.');
        }
    }
    if (!array_filter($contexts[3133]['etapas'], static fn ($s) => $s['contexto_planejamento']['disponivel'])) throw new RuntimeException('Amostra planejada mudou.');
    $output = __DIR__ . '/../docs/imagem-contexto-operacional-exemplos.json';
    file_put_contents($output, json_encode($examples, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    echo "OK: contrato real validado, incluindo amostra com planejamento. Exemplos em docs/imagem-contexto-operacional-exemplos.json\n";
} finally {
    $conn->rollback();
    $conn->close();
}
