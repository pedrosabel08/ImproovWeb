<?php
require_once __DIR__ . '/../config/session_bootstrap.php';

include '../conexao.php'; // Conexão com o banco de dados

// Verifique se o usuário está autenticado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
  header("Location: ../index.html");
  exit();
}

$idusuario = $_SESSION['idusuario'];
$idcolaborador = $_SESSION['idcolaborador'];

try {
  // Construção da query com base no usuário
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
            (SELECT MAX(hi.data_aprovacao)
             FROM historico_aprovacoes hi
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
        WHERE f.funcao_id IN (1, 2, 3, 4, 5, 6, 7, 8, 9)
          AND (
            f.status IN ('Em aprovação', 'Ajuste', 'Aprovado com ajustes')
            OR (f.status IN ('Em andamento', 'Não iniciado') AND f.funcao_id = 4 AND EXISTS (
                SELECT 1 FROM angulos_imagens ai WHERE ai.imagem_id = f.imagem_id AND ai.liberada = 1
            ))
          )
          AND o.status_obra = 0
        ORDER BY data_aprovacao DESC";
  } elseif ($idusuario == 5) {
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
          AND (
            f.status IN ('Em aprovação', 'Ajuste', 'Aprovado com ajustes')
            OR (f.status IN ('Em andamento', 'Não iniciado') AND f.funcao_id = 4 AND EXISTS (
                SELECT 1 FROM angulos_imagens ai WHERE ai.imagem_id = f.imagem_id AND ai.liberada = 1
            ))
          )
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
            o.idobra,
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
        WHERE f.funcao_id IN (1, 2, 3, 4, 5, 6, 7, 8, 9)
          AND (
            f.status IN ('Em aprovação', 'Ajuste', 'Aprovado com ajustes')
            OR (f.status IN ('Em andamento', 'Não iniciado') AND f.funcao_id = 4 AND EXISTS (
                SELECT 1 FROM angulos_imagens ai WHERE ai.imagem_id = f.imagem_id AND ai.liberada = 1
            ))
          )
        ORDER BY data_aprovacao DESC";
  } else {
    // Se for colaborador não-admin, limitar por obras associadas ao colaborador.
    // Permitir que o colaborador 8 veja as tarefas dele e do colaborador 40.
    if ($idcolaborador == 8) {
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
        WHERE f.funcao_id IN (1, 2, 3, 4, 5, 6, 7, 8, 9)
          AND (
            f.status IN ('Em aprovação', 'Ajuste', 'Aprovado com ajustes')
            OR (f.status IN ('Em andamento', 'Não iniciado') AND f.funcao_id = 4 AND EXISTS (
                SELECT 1 FROM angulos_imagens ai WHERE ai.imagem_id = f.imagem_id AND ai.liberada = 1
            ))
          )
          AND o.idobra IN (
              SELECT i2.obra_id
              FROM imagens_cliente_obra i2
              JOIN funcao_imagem f2 ON f2.imagem_id = i2.idimagens_cliente_obra
              WHERE f2.colaborador_id IN (8, 40, 23)
          )
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
            o.idobra,
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
        WHERE f.funcao_id IN (1, 2, 3, 4, 5, 6, 7, 8, 9)
          AND (
            f.status IN ('Em aprovação', 'Ajuste', 'Aprovado com ajustes')
            OR (f.status IN ('Em andamento', 'Não iniciado') AND f.funcao_id = 4 AND EXISTS (
                SELECT 1 FROM angulos_imagens ai WHERE ai.imagem_id = f.imagem_id AND ai.liberada = 1
            ))
          )
          AND o.idobra IN (
              SELECT i2.obra_id
              FROM imagens_cliente_obra i2
              JOIN funcao_imagem f2 ON f2.imagem_id = i2.idimagens_cliente_obra
              WHERE f2.colaborador_id = ?
          )
        ORDER BY data_aprovacao DESC";
    }
  }

  // Preparar e executar a query
  // Somente usuários não-admin precisam de bind por colaborador.
  if (!($idusuario == 1 || $idusuario == 2 || $idusuario == 9 || $idusuario == 20 || $idusuario == 3 || $idusuario == 5)) {
    if ($idcolaborador == 8) {
      // SQL já contém os colaboradores (8 e 40) sem placeholder
      $stmt = $conn->prepare($sql);
    } else {
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("i", $idcolaborador);
    }
  } else {
    // Usuários admin (1,2,9,20,3,5) não precisam de bind
    $stmt = $conn->prepare($sql);
  }

  $stmt->execute();
  $result = $stmt->get_result();

  // Processar os resultados
  $tarefas = [];
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $tarefas[] = $row;
    }
  }

  // ==== ÂNGULO APROVADO FLAG ====
  // Tarefas com status 'Não iniciado' ou 'Em andamento' que passaram pelo filtro
  // do EXISTS (angulos_imagens.liberada=1) recebem flag para exibição no front-end.
  foreach ($tarefas as &$t) {
    if (intval($t['funcao_id']) === 4 && in_array($t['status'], ['Não iniciado', 'Em andamento'])) {
      $t['angulo_aprovado'] = true;
    }
  }
  unset($t);
  // ==== END ÂNGULO APROVADO FLAG ====

  // ==== UNIFIED PAIR BADGE ====
  // For secondary functions (Filtro=8, Composição=3), detect if they belong to a unified pair
  // by checking if the corresponding primary (Caderno=1, Modelagem=2) is "Finalizado"
  // for the same imagem_id and colaborador_id, and the pair is not explicitly separated.
  $pairMap = [8 => ['primary_id' => 1, 'primary_nome' => 'Caderno', 'par_tipo' => 'caderno_filtro'],
               3 => ['primary_id' => 2, 'primary_nome' => 'Modelagem', 'par_tipo' => 'modelagem_composicao']];

  $imagemIdsSec = [];
  foreach ($tarefas as $t) {
    $fid = intval($t['funcao_id']);
    if (isset($pairMap[$fid])) {
      $imagemIdsSec[] = intval($t['imagem_id']);
    }
  }

  $primFinalizados  = []; // [imagem_id:funcao_id] => true
  $paresSepFR       = [];
  if (!empty($imagemIdsSec)) {
    $imagemIdsSec = array_unique($imagemIdsSec);
    $inSec = implode(',', array_fill(0, count($imagemIdsSec), '?'));
    $typesSec = str_repeat('i', count($imagemIdsSec));

    try {
      // Which primaries are Finalizado?
      $stmtPrim = $conn->prepare(
        "SELECT imagem_id, funcao_id FROM funcao_imagem
         WHERE imagem_id IN ($inSec)
           AND funcao_id IN (1, 2)
           AND status = 'Finalizado'"
      );
      $stmtPrim->bind_param($typesSec, ...$imagemIdsSec);
      $stmtPrim->execute();
      $resPrim = $stmtPrim->get_result();
      while ($rp = $resPrim->fetch_assoc()) {
        $primFinalizados[$rp['imagem_id'] . ':' . $rp['funcao_id']] = true;
      }
      $stmtPrim->close();

      // Which pairs are separated?
      $stmtSepFR = $conn->prepare(
        "SELECT imagem_id, par_tipo FROM funcao_par_separado WHERE imagem_id IN ($inSec)"
      );
      $stmtSepFR->bind_param($typesSec, ...$imagemIdsSec);
      $stmtSepFR->execute();
      $resSepFR = $stmtSepFR->get_result();
      while ($rs = $resSepFR->fetch_assoc()) {
        $paresSepFR[$rs['imagem_id'] . ':' . $rs['par_tipo']] = true;
      }
      $stmtSepFR->close();
    } catch (Exception $ex) {
      // funcao_par_separado may not exist yet; ignore
    }

    foreach ($tarefas as &$t) {
      $fid = intval($t['funcao_id']);
      if (isset($pairMap[$fid])) {
        $pc       = $pairMap[$fid];
        $imgId    = intval($t['imagem_id']);
        $primKey  = $imgId . ':' . $pc['primary_id'];
        $sepKey   = $imgId . ':' . $pc['par_tipo'];
        if (isset($primFinalizados[$primKey]) && !isset($paresSepFR[$sepKey])) {
          $t['par_primario_nome']   = $pc['primary_nome'];
          $t['par_primario_status'] = 'Finalizado';
        }
      }
    }
    unset($t);
  }
  // ==== END UNIFIED PAIR BADGE ====

  // ==== FINALIZADOR PODE APROVAR PÓS-PRODUÇÃO ====
  // Se o colaborador atual tem uma tarefa de Finalização (funcao_id=4)
  // para a mesma imagem, pode aprovar a Pós-produção (funcao_id=5).
  $imagemIdsPosP = [];
  foreach ($tarefas as $t) {
    if (intval($t['funcao_id']) == 5) {
      $imagemIdsPosP[] = intval($t['imagem_id']);
    }
  }

  if (!empty($imagemIdsPosP) && !empty($idcolaborador)) {
    $imagemIdsPosP = array_unique($imagemIdsPosP);
    $inP     = implode(',', array_fill(0, count($imagemIdsPosP), '?'));
    $typesP  = 'i' . str_repeat('i', count($imagemIdsPosP));
    $paramsP = array_merge([$idcolaborador], $imagemIdsPosP);

    $stmtFinP = $conn->prepare(
      "SELECT imagem_id FROM funcao_imagem
       WHERE funcao_id = 4 AND colaborador_id = ? AND imagem_id IN ($inP)"
    );
    $stmtFinP->bind_param($typesP, ...$paramsP);
    $stmtFinP->execute();
    $resFinP = $stmtFinP->get_result();
    $imgComFinalizacao = [];
    while ($rowP = $resFinP->fetch_assoc()) {
      $imgComFinalizacao[$rowP['imagem_id']] = true;
    }
    $stmtFinP->close();

    foreach ($tarefas as &$t) {
      // Não marcar finalizador_pode_aprovar se a tarefa já está aguardando direção
      if (intval($t['funcao_id']) == 5 && isset($imgComFinalizacao[$t['imagem_id']]) && empty($t['pendente_direcao'])) {
        $t['finalizador_pode_aprovar'] = true;
      }
    }
    unset($t);
  }
  // ==== END FINALIZADOR PODE APROVAR ====

  // ==== PENDENTE DIREÇÃO ====
  // Para tarefas de Pós-produção (funcao_id=5) com status_novo histórico = 'Aguardando Direção',
  // sinaliza pendente_direcao e, se o colaborador atual for direção (9 ou 21), diretor_pode_aprovar.
  $funcaoImagemIds5 = [];
  foreach ($tarefas as $t) {
    if (intval($t['funcao_id']) == 5) {
      $funcaoImagemIds5[] = intval($t['idfuncao_imagem']);
    }
  }

  if (!empty($funcaoImagemIds5)) {
    $funcaoImagemIds5 = array_unique($funcaoImagemIds5);
    $inDir   = implode(',', array_fill(0, count($funcaoImagemIds5), '?'));
    $typDir  = str_repeat('i', count($funcaoImagemIds5));

    $stmtDir = $conn->prepare(
      "SELECT DISTINCT funcao_imagem_id FROM historico_aprovacoes
       WHERE funcao_imagem_id IN ($inDir) AND status_novo = 'Aguardando Direção'"
    );
    $stmtDir->bind_param($typDir, ...$funcaoImagemIds5);
    $stmtDir->execute();
    $resDir = $stmtDir->get_result();
    $pendenteDirecaoIds = [];
    while ($rowDir = $resDir->fetch_assoc()) {
      $pendenteDirecaoIds[$rowDir['funcao_imagem_id']] = true;
    }
    $stmtDir->close();

    $isDirecao = in_array((int)$idcolaborador, [9, 21]);

    foreach ($tarefas as &$t) {
      if (intval($t['funcao_id']) == 5 && isset($pendenteDirecaoIds[$t['idfuncao_imagem']])) {
        $t['pendente_direcao']      = true;
        // Remove finalizador_pode_aprovar se foi marcado antes desta detecção
        unset($t['finalizador_pode_aprovar']);
        if ($isDirecao) {
          $t['diretor_pode_aprovar'] = true;
        }
      }
    }
    unset($t);
  }
  // ==== END PENDENTE DIREÇÃO ====

  // Retornar os resultados no formato JSON
  echo json_encode($tarefas);

  $stmt->close();
  $conn->close();
} catch (Exception $e) {
  // Retornar erro em caso de falha
  echo json_encode(['erro' => 'Erro ao executar a consulta', 'mensagem' => $e->getMessage()]);
}
