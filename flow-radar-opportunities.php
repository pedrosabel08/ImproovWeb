<?php
// Flow Radar - Oportunidades formatadas em tabela HTML
// Regras de workflow customizadas conforme especificação do usuário
include_once __DIR__ . '/conexao.php';

if (!function_exists('conectarBanco')) {
    if (file_exists(__DIR__ . '/conexaoMain.php')) include_once __DIR__ . '/conexaoMain.php';
}
if (!function_exists('conectarBanco')) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<p><strong>Erro:</strong> Função conectarBanco() não encontrada. Verifique conexao.php.</p>';
    exit;
}

$conn = conectarBanco();

$funcao_id = isset($_GET['funcao_id']) ? (int)$_GET['funcao_id'] : 0;
// opcional: colaborador que consultou (pode ser usado para filtrar sugestões)
$colaborador_id = isset($_GET['colaborador_id']) ? (int)$_GET['colaborador_id'] : 0;

// Mapeamento de nomes de função (para cabeçalho)
$funcoes_map = [1=>'Caderno',8=>'Filtro de assets',2=>'Modelagem',3=>'Composição',4=>'Finalização',5=>'Pós-produção'];

if (!$funcao_id || !isset($funcoes_map[$funcao_id])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>Parâmetro obrigatório: <code>funcao_id</code> (1,2,3,4,5 ou 8).</p>';
    exit;
}

// Função auxiliar: verifica se determinada funcao_imagem possui aprovação
function funcaoAprovada($conn, $funcao_imagem_id) {
    if (!$funcao_imagem_id) return false;
    $sql = "SELECT 1 FROM historico_aprovacoes h WHERE h.funcao_imagem_id = ? AND h.status_novo IN ('Aprovado','Aprovado com Ajustes','Finalizado') LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $funcao_imagem_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res->num_rows > 0;
    $stmt->close();
    return $ok;
}

// Função auxiliar: retorna idfuncao_imagem para imagem+funcao se existir
function getFuncaoImagemId($conn, $imagem_id, $funcao_id) {
    $sql = "SELECT idfuncao_imagem, colaborador_id, prazo FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $imagem_id, $funcao_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// Função auxiliar: checa se existe funcao anterior aprovada para a imagem
function existeFuncaoAnteriorAprovada($conn, $imagem_id, $funcao_anterior_ids) {
    // procura fp com funcao_id IN (...) para a mesma imagem e com historico aprovado
    $placeholders = implode(',', array_fill(0, count($funcao_anterior_ids), '?'));
    $types = str_repeat('i', count($funcao_anterior_ids) + 1);
    $sql = "SELECT fp.idfuncao_imagem FROM funcao_imagem fp WHERE fp.imagem_id = ? AND fp.funcao_id IN ($placeholders) LIMIT 1";
    $stmt = $conn->prepare($sql);
    $bind_params = array_merge([$imagem_id], $funcao_anterior_ids);
    // bind dynamically
    $ref = [];
    $ref[] = $types;
    foreach ($bind_params as $k => $v) $ref[] = &$bind_params[$k];
    call_user_func_array([$stmt, 'bind_param'], $ref);
    $stmt->execute();
    $res = $stmt->get_result();
    $found = false;
    while ($r = $res->fetch_assoc()) {
        if (funcaoAprovada($conn, $r['idfuncao_imagem'])) { $found = true; break; }
    }
    $stmt->close();
    return $found;
}

// Função auxiliar para checar modelagem de fachada aprovada na obra
function modelagemFachadaAprovadaParaObra($conn, $obra_id) {
    // procura funcao_imagem funcao_id=2 para imagens da obra onde tipo_imagem IN ('Fachada','Imagem Externa')
    $sql = "SELECT fp.idfuncao_imagem FROM funcao_imagem fp
        JOIN imagens_cliente_obra im ON im.idimagens_cliente_obra = fp.imagem_id
        WHERE fp.funcao_id = 2 AND im.obra_id = ? AND im.tipo_imagem IN ('Fachada','Imagem Externa') LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $obra_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = false;
    while ($r = $res->fetch_assoc()) {
        if (funcaoAprovada($conn, $r['idfuncao_imagem'])) { $ok = true; break; }
    }
    $stmt->close();
    return $ok;
}

// Consulta base: imagens ativas na obra
$sqlImgs = "SELECT i.idimagens_cliente_obra, i.imagem_nome, i.tipo_imagem, i.obra_id, o.nome_obra
    FROM imagens_cliente_obra i
    JOIN obra o ON o.idobra = i.obra_id
    WHERE o.status_obra = 0";

$resultImgs = $conn->query($sqlImgs);

$rows = [];
while ($img = $resultImgs->fetch_assoc()) {
    $imagem_id = (int)$img['idimagens_cliente_obra'];
    $tipo = $img['tipo_imagem'];
    $obra_id = (int)$img['obra_id'];

    // Determinar se esta imagem é candidata para a funcao solicitada
    $candidato = false;
    $status_anterior = '-';
    $idfuncao_imagem = null;

    // obter registro existente para a funcao (se houver)
    $fi = getFuncaoImagemId($conn, $imagem_id, $funcao_id);
    if ($fi) {
        $idfuncao_imagem = $fi['idfuncao_imagem'];
        $colab = (int)$fi['colaborador_id'];
        // se já tem colaborador, não é oportunidade
        if ($colab && $colab != 0) { continue; }
    }

    // regras por funcao
    switch ($funcao_id) {
        case 1: // Caderno - inicial para Unidade, Interna, Externa
        case 8: // Filtro de assets - mesma lógica inicial
            if (in_array($tipo, ['Unidade','Imagem Interna','Imagem Externa'])) $candidato = true;
            break;

        case 2: // Modelagem
            if (in_array($tipo, ['Unidade','Imagem Interna','Imagem Externa'])) {
                // requer caderno/filtro aprovado
                if (existeFuncaoAnteriorAprovada($conn, $imagem_id, [1,8])) {
                    $candidato = true;
                    // pegar status anterior (do ultimo historico da funcao anterior)
                    $status_anterior = 'Caderno/Filtro aprovado';
                }
            } elseif (in_array($tipo, ['Fachada','Imagem Externa'])) {
                // modelagem de fachada: tarefa única por obra
                // incluir a primeira imagem encontrada da obra para representar a obra
                // garantir que ainda não exista modelagem (funcao_id=2) aprovada para a obra
                $sqlChk = "SELECT fp.idfuncao_imagem FROM funcao_imagem fp JOIN imagens_cliente_obra im ON im.idimagens_cliente_obra = fp.imagem_id WHERE fp.funcao_id = 2 AND im.obra_id = ? LIMIT 1";
                $st = $conn->prepare($sqlChk);
                $st->bind_param('i', $obra_id);
                $st->execute();
                $r = $st->get_result()->fetch_assoc();
                $st->close();
                if (!$r) {
                    // nenhum registro de modelagem para a obra: considerar candidato (uma vez por obra)
                    // evitar duplicatas: marcaremos candidato apenas para a primeira imagem Fachada/Externa encontrada por obra
                    // para isso checamos se obra já adicionada
                    $rows_key = "obra_".$obra_id;
                    if (!isset($rows[$rows_key])) {
                        $candidato = true;
                        // setaremos idfuncao_imagem null e tipo especial
                        $img['imagem_nome'] = 'Modelagem da Fachada (obra: '.$img['nome_obra'].')';
                    }
                }
            }
            break;

        case 3: // Composição
            if (in_array($tipo, ['Unidade','Imagem Interna'])) {
                // requer modelagem da mesma imagem
                if (existeFuncaoAnteriorAprovada($conn, $imagem_id, [2])) { $candidato = true; $status_anterior='Modelagem aprovada'; }
            } elseif ($tipo === 'Imagem Externa') {
                // composição de imagem externa: requer modelagem da fachada aprovada para a obra
                if (modelagemFachadaAprovadaParaObra($conn, $obra_id)) { $candidato = true; $status_anterior='Modelagem de Fachada aprovada'; }
            }
            break;

        case 4: // Finalização
            if ($tipo === 'Fachada') {
                // após modelagem (fachada) aprovada
                if (modelagemFachadaAprovadaParaObra($conn, $obra_id)) { $candidato = true; $status_anterior='Modelagem de Fachada aprovada'; }
            } elseif (in_array($tipo, ['Unidade','Imagem Interna','Imagem Externa'])) {
                // após composição da mesma imagem aprovada
                if (existeFuncaoAnteriorAprovada($conn, $imagem_id, [3])) { $candidato = true; $status_anterior='Composição aprovada'; }
            }
            break;

        case 5: // Pós-produção - após finalização
            if (existeFuncaoAnteriorAprovada($conn, $imagem_id, [4])) { $candidato = true; $status_anterior='Finalização aprovada'; }
            break;
    }

    if ($candidato) {
        // evitar duplicatas por obra para modelagem de fachada: chave já criada acima
        if (isset($rows_key) && isset($rows[$rows_key])) continue;
        $rows[] = array_merge($img, [
            'idfuncao_imagem' => $idfuncao_imagem,
            'status_anterior' => $status_anterior,
        ]);
    }
}

// Gerar HTML
header('Content-Type: text/html; charset=utf-8');
echo '<h3>FLOW RADAR — Oportunidades para função: '.htmlspecialchars($funcoes_map[$funcao_id]).'</h3>';
if (empty($rows)) {
    echo '<p>Nenhuma tarefa disponível no momento.</p>';
    exit;
}

echo '<table border="1" cellpadding="6" cellspacing="0">';
echo '<thead><tr><th>#</th><th>Obra</th><th>Imagem</th><th>Tipo</th><th>Status anterior</th><th>Ações</th></tr></thead><tbody>';
$i=1;
foreach ($rows as $r) {
    $idcell = htmlspecialchars($r['idimagens_cliente_obra']);
    $imgname = htmlspecialchars($r['imagem_nome']);
    $obra = htmlspecialchars($r['nome_obra']);
    $tipo = htmlspecialchars($r['tipo_imagem'] ?? ($r['tipo'] ?? '-'));
    $status_ant = htmlspecialchars($r['status_anterior'] ?? '-');

    // Ações: atribuir (envia para atribuir_flow_radar.php), ignorar (marca localmente) e ver imagem
    $btnAssign = '<button onclick="assignTask('.$idcell.','.$funcao_id.')">Atribuir</button>';
    $btnIgnore = '<button onclick="ignoreTask('.$idcell.','.$funcao_id.')">Ignorar</button>';
    $btnView = '<a href="/ImproovWeb/Imagens/index.php?imagem_id='.$idcell.'" target="_blank">Ver imagem</a>';

    echo '<tr>';
    echo '<td>'.($i++).'</td>';
    echo '<td>'.$obra.'</td>';
    echo '<td>'.$imgname.'</td>';
    echo '<td>'.$tipo.'</td>';
    echo '<td>'.$status_ant.'</td>';
    echo '<td>'.$btnAssign.' '.$btnIgnore.' '.$btnView.'</td>';
    echo '</tr>';
}
echo '</tbody></table>';

// Pequeno script JS para acionar ações (usar fetch para chamadas API existentes)
echo "
<script>
function assignTask(imagemId, funcaoId){
  const body = { colaborador_id: 0, imagem_id: imagemId, funcao_id: funcaoId };
  // aqui o backend deve aceitar criação/atribuição; ajuste conforme implementação
  fetch('/ImproovWeb/atribuir_flow_radar.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})
    .then(r=>r.json()).then(j=>alert(JSON.stringify(j))).catch(e=>alert('Erro:'+e));
}
function ignoreTask(imagemId, funcaoId){
  alert('Ignorar ainda não implementado no servidor. imagem='+imagemId+' funcao='+funcaoId);
}
</script>
";

$conn->close();

?>
