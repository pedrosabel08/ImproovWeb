<?php
include '../conexao.php'; // Conexão com o banco de dados
require_once __DIR__ . '/auth_cookie.php';

// Ler token antecipadamente (GET ou POST)
$token = null;
if (isset($_GET['token']) && $_GET['token'] !== '') $token = $_GET['token'];
if (isset($_POST['token']) && $_POST['token'] !== '') $token = $_POST['token'];

// Se não foi fornecido token, negar acesso (esta rota só funciona via token)
if (!$token) {
  header('Content-Type: application/json');
  echo json_encode(['erro' => 'token_obrigatorio', 'mensagem' => 'O parâmetro token é obrigatório para acessar este endpoint.']);
  $conn->close();
  exit();
}

// cookie-based user info
$idusuario = $flow_user_id;
$idcolaborador = $flow_idcolaborador;

try {
  // Resolvemos token -> obra_id
  $obraFiltro = null;
  $stmtToken = $conn->prepare("SELECT idobra FROM obra WHERE token = ? LIMIT 1");
  if ($stmtToken) {
    $stmtToken->bind_param('s', $token);
    $stmtToken->execute();
    $resToken = $stmtToken->get_result();
    if ($rowT = $resToken->fetch_assoc()) {
      $obraFiltro = intval($rowT['idobra']);
    } else {
      header('Content-Type: application/json');
      echo json_encode(['erro' => 'token_invalido', 'mensagem' => 'Obra não encontrada para o token informado']);
      $stmtToken->close();
      $conn->close();
      exit();
    }
    $stmtToken->close();
  } else {
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'db_error', 'mensagem' => 'Não foi possível validar o token (prepare falhou)']);
    $conn->close();
    exit();
  }

  if ($idusuario == 1 || $idusuario == 2) {
    $sql = "SELECT 
            f.idfuncao_imagem,
            f.funcao_id, 
            fun.nome_funcao, 
            f.status, 
            f.imagem_id, 
            i.imagem_nome, 
            f.colaborador_id, 
            c.nome_colaborador, 
            c.telefone,
            u.nome_slack,
            o.nome_obra,
            o.nomenclatura,
            o.idobra,
            s.nome_status,
            (SELECT MAX(hi.data_envio)
             FROM historico_aprovacoes_imagens hi
             WHERE hi.funcao_imagem_id = f.idfuncao_imagem) AS data_aprovacao,
            (SELECT h.status_novo
             FROM historico_aprovacoes h
             WHERE h.funcao_imagem_id = f.idfuncao_imagem
             ORDER BY h.data_aprovacao DESC 
             LIMIT 1) AS status_novo,
            (SELECT hi.imagem
             FROM historico_aprovacoes_imagens hi 
             WHERE hi.funcao_imagem_id = f.idfuncao_imagem
             ORDER BY hi.data_envio DESC 
             LIMIT 1) AS imagem
        FROM funcao_imagem f
        LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
        LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
        LEFT JOIN usuario u ON u.idcolaborador = c.idcolaborador
        LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
        LEFT JOIN status_imagem s ON i.status_id = s.idstatus
        LEFT JOIN obra o ON i.obra_id = o.idobra
        WHERE f.funcao_id = 5
          AND o.idobra = ?
          -- AND (f.status = 'Em aprovação' OR f.status = 'Ajuste' OR f.status = 'Aprovado com ajustes')
        ORDER BY data_aprovacao DESC";
  } elseif ($idusuario == 9 || $idusuario == 20 || $idusuario == 3) {
    $sql = "SELECT 
            f.idfuncao_imagem,
            f.funcao_id, 
            fun.nome_funcao, 
            f.status, 
            f.imagem_id, 
            i.imagem_nome, 
            f.colaborador_id, 
            c.nome_colaborador, 
            c.telefone,
            u.nome_slack,
            o.nome_obra,
            o.nomenclatura,
            (SELECT MAX(h.data_aprovacao)
             FROM historico_aprovacoes h
             WHERE h.funcao_imagem_id = f.idfuncao_imagem) AS data_aprovacao,
            (SELECT h.status_novo
             FROM historico_aprovacoes h
             WHERE h.funcao_imagem_id = f.idfuncao_imagem
             ORDER BY h.data_aprovacao DESC 
             LIMIT 1) AS status_novo,
            (SELECT hi.imagem
             FROM historico_aprovacoes_imagens hi 
             WHERE hi.funcao_imagem_id = f.idfuncao_imagem
             ORDER BY hi.data_envio DESC 
             LIMIT 1) AS imagem
        FROM funcao_imagem f
        LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
        LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
        LEFT JOIN usuario u ON u.idcolaborador = c.idcolaborador
        LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
        LEFT JOIN obra o ON i.obra_id = o.idobra
        WHERE f.funcao_id = 5
          AND o.idobra = ?
          AND (f.status = 'Em aprovação' OR f.status = 'Ajuste' OR f.status = 'Aprovado com ajustes')
        ORDER BY data_aprovacao DESC";
  } else {
    $sql = "SELECT 
            f.idfuncao_imagem,
            f.funcao_id, 
            fun.nome_funcao, 
            f.status, 
            f.imagem_id, 
            i.imagem_nome, 
            f.colaborador_id, 
            c.nome_colaborador, 
            c.telefone,
            u.nome_slack,
            o.nome_obra,
            o.nomenclatura,
            (SELECT MAX(h.data_aprovacao)
             FROM historico_aprovacoes h
             WHERE h.funcao_imagem_id = f.idfuncao_imagem) AS data_aprovacao,
            (SELECT h.status_novo
             FROM historico_aprovacoes h
             WHERE h.funcao_imagem_id = f.idfuncao_imagem
             ORDER BY h.data_aprovacao DESC 
             LIMIT 1) AS status_novo,
            (SELECT hi.imagem
             FROM historico_aprovacoes_imagens hi 
             WHERE hi.funcao_imagem_id = f.idfuncao_imagem
             ORDER BY hi.data_envio DESC 
             LIMIT 1) AS imagem
        FROM funcao_imagem f
        LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
        LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
        LEFT JOIN usuario u ON u.idcolaborador = c.idcolaborador
        LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
        LEFT JOIN obra o ON i.obra_id = o.idobra
        WHERE f.funcao_id = 5
          AND o.idobra = ?
          AND (f.status = 'Em aprovação' OR f.status = 'Ajuste' OR f.status = 'Aprovado com ajustes')
          AND o.idobra IN (
              SELECT i2.obra_id
              FROM imagens_cliente_obra i2
              JOIN funcao_imagem f2 ON f2.imagem_id = i2.idimagens_cliente_obra
              WHERE f2.colaborador_id = ?
          )
        ORDER BY data_aprovacao DESC";
  }

  // Instead of returning a flat list of tasks, return deliveries (entregas) grouped with their items.
  // This builds an array: entregas[] { entrega_id, nome_etapa, data_entrega, itens: [...] }

  // 1) Fetch entregas for the obra
  $sqlEntregas = "SELECT e.id as identrega, s.nome_status as nome_etapa, e.obra_id
                  FROM entregas e
                  LEFT JOIN status_imagem s ON e.status_id = s.idstatus
                  WHERE e.obra_id = ?
                  ORDER BY e.id DESC";
  $stmtEnt = $conn->prepare($sqlEntregas);
  $stmtEnt->bind_param("i", $obraFiltro);
  $stmtEnt->execute();
  $resEnt = $stmtEnt->get_result();

  $entregas = [];

  while ($ent = $resEnt->fetch_assoc()) {
    $entregaId = $ent['identrega'];

    // 2) For each entrega, fetch its items and related image/version metadata
    $sqlItens = "SELECT ei.id,
            ei.entrega_id,
            ei.imagem_id,
            ei.historico_id,
            ei.status AS entrega_item_status,
            hi.nome_arquivo,
            hi.data_envio,
            hi.id AS historico_imagem_id,
            hi.indice_envio,
            i.imagem_nome,
            s.nome_status AS nome_status_imagem,
            (SELECT COUNT(*) FROM angulos_imagens ai WHERE ai.entrega_item_id = ei.id) AS angulos_count
          FROM entregas_itens ei
          LEFT JOIN historico_aprovacoes_imagens hi ON hi.id = ei.historico_id
          LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = ei.imagem_id
          LEFT JOIN status_imagem s ON i.status_id = s.idstatus
          WHERE ei.entrega_id = ?";

    $stmtItens = $conn->prepare($sqlItens);
    $stmtItens->bind_param("i", $entregaId);
    $stmtItens->execute();
    $resItens = $stmtItens->get_result();

    $itens = [];
    while ($it = $resItens->fetch_assoc()) {
      // Build a usable image URL ensuring an extension; append .jpg if none present
      $imagemUrl = null;
      if (!empty($it['nome_arquivo'])) {
        $nf = $it['nome_arquivo'];
        $ext = strtolower(pathinfo($nf, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp','bmp'];
        if ($ext === '' || !in_array($ext, $allowed)) {
          $nf .= '.jpg';
        }
        $imagemUrl = 'https://improov.com.br/sistema/uploads/' . $nf;
      } elseif (!empty($it['imagem_nome'])) {
        $im = $it['imagem_nome'];
        $ext2 = strtolower(pathinfo($im, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp','bmp'];
        if ($ext2 === '' || !in_array($ext2, $allowed)) {
          $im .= '.jpg';
        }
        $imagemUrl = 'https://improov.com.br/sistema/uploads/' . $im;
      }

      $it['imagem'] = $imagemUrl;

      // Se houver ângulos vinculados, prefira a imagem do primeiro ângulo
      if (intval($it['angulos_count']) > 0) {
        $sqlAng = "SELECT hi.imagem AS ang_imagem
                   FROM angulos_imagens ai
                   LEFT JOIN historico_aprovacoes_imagens hi ON hi.id = ai.historico_id
                   WHERE ai.entrega_item_id = ?
                   ORDER BY ai.id ASC
                   LIMIT 1";
        $stmtAng = $conn->prepare($sqlAng);
        if ($stmtAng) {
          $stmtAng->bind_param('i', $it['id']);
          $stmtAng->execute();
          $resAng = $stmtAng->get_result();
          if ($rowAng = $resAng->fetch_assoc()) {
            $arquivoBaseAng = $rowAng['ang_imagem'];
            if (!empty($arquivoBaseAng)) {
              $extA = strtolower(pathinfo($arquivoBaseAng, PATHINFO_EXTENSION));
              if ($extA === '') $arquivoBaseAng .= '.jpg';
              // monta URL completa (mesma base usada nas outras imagens)
              $it['imagem'] = 'https://improov.com.br/sistema/' . $arquivoBaseAng;
            }
          }
          $stmtAng->close();
        }
      }

      // Flag para front decidir se deve buscar ângulos em carrossel
      // Regra ajustada: se não há historico_id (fluxo P00) e há ângulos vinculados
      // opcionalmente restringe ao status da imagem P00 para evitar acionar em outras etapas
      $it['carrossel_angulos'] = (empty($it['historico_id']) && intval($it['angulos_count']) > 0 && $it['nome_status_imagem'] === 'P00') ? 1 : 0;
      $itens[] = $it;
    }

    $ent['itens'] = $itens;
    $entregas[] = $ent;

    $stmtItens->close();
  }

  // Return entregas structure
  echo json_encode(['entregas' => $entregas]);

  $stmtEnt->close();
  $conn->close();
} catch (Exception $e) {
  // Retornar erro em caso de falha
  echo json_encode(['erro' => 'Erro ao executar a consulta', 'mensagem' => $e->getMessage()]);
}
