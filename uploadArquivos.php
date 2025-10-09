<?php
header('Content-Type: application/json');
require 'conexao.php';

// ---------- Dados FTP ----------
$ftp_host = "ftp.improov.com.br";
$ftp_port = 21; // porta padrão FTP
$ftp_user = "improov";
$ftp_pass = "Impr00v";
$ftp_base = "/www/sistema/uploads/"; // pasta remota já existente

// ---------- Funções utilitárias ----------
function removerTodosAcentos($str)
{
    return preg_replace(
        ['/[áàãâä]/ui', '/[éèêë]/ui', '/[íìîï]/ui', '/[óòõôö]/ui', '/[úùûü]/ui', '/[ç]/ui'],
        ['a', 'e', 'i', 'o', 'u', 'c'],
        $str
    );
}

function sanitizeFilename($str)
{
    $str = removerTodosAcentos($str);
    $str = preg_replace('/[\/\\\:*?"<>|]/', '', $str);
    $str = preg_replace('/\s+/', '_', $str);
    return $str;
}

function getProcesso($nomeFuncao)
{
    $map = [
        'Pré-Finalização' => 'PRE',
        'Pós-Produção'    => 'POS',
    ];
    if (isset($map[$nomeFuncao])) return $map[$nomeFuncao];
    $semAcento = mb_strtoupper(removerTodosAcentos($nomeFuncao), 'UTF-8');
    return mb_substr($semAcento, 0, 3, 'UTF-8');
}

function enviarArquivoFTP($conn_ftp, $arquivoLocal, $arquivoRemoto)
{
    // Ativa modo passivo
    ftp_pasv($conn_ftp, true);

    // Envia o arquivo
    if (ftp_put($conn_ftp, $arquivoRemoto, $arquivoLocal, FTP_BINARY)) {
        return [true, $arquivoRemoto];
    } else {
        return [false, "⚠ Erro ao enviar via FTP: $arquivoRemoto"];
    }
}

// ---------- Parâmetros ----------
$dataIdFuncoes = $_POST['dataIdFuncoes'] ?? '';
$numeroImagem  = preg_replace('/\D/', '', $_POST['numeroImagem'] ?? '');
$nomenclatura  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['nomenclatura'] ?? '');
$nomeFuncao    = $_POST['nome_funcao'] ?? '';
$nome_imagem   = $_POST['nome_imagem'] ?? '';
$idimagem   = $_POST['idimagem'] ?? '';

if (!$dataIdFuncoes || !$numeroImagem || !$nomenclatura || !$nomeFuncao) {
    echo json_encode(["error" => "Parâmetros insuficientes"]);
    exit;
}

$idFuncaoImagem = $dataIdFuncoes;
$processo = getProcesso($nomeFuncao);

// ---------- Índice de envio ----------
$stmt = $conn->prepare("SELECT MAX(indice_envio) AS max_indice FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ?");
$stmt->bind_param("i", $idFuncaoImagem);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$indice_envio = ($result['max_indice'] ?? 0) + 1;
$stmt->close();

// ---------- Status nome ----------
$stmt2 = $conn->prepare("SELECT s.nome_status FROM status_imagem s
                         JOIN imagens_cliente_obra i ON s.idstatus = i.status_id
                         WHERE i.idimagens_cliente_obra = ?");
$stmt2->bind_param("i", $idimagem);
$stmt2->execute();
$result2 = $stmt2->get_result()->fetch_assoc();
$status_nome = $result2 ? $result2['nome_status'] : null; // evita erro se não encontrar
$stmt2->close();

// ---------- Conexão FTP ----------
$conn_ftp = ftp_connect($ftp_host, $ftp_port, 10); // timeout 10s
if (!$conn_ftp) {
    echo json_encode(["error" => "Não foi possível conectar ao servidor FTP."]);
    exit;
}
if (!ftp_login($conn_ftp, $ftp_user, $ftp_pass)) {
    ftp_close($conn_ftp);
    echo json_encode(["error" => "Falha na autenticação FTP."]);
    exit;
}

// ---------- Upload das imagens ----------
if (!isset($_FILES['imagens'])) {
    echo json_encode(["error" => "Nenhuma imagem recebida"]);
    exit;
}

$imagens = $_FILES['imagens'];
$totalImagens = count($imagens['name']);
$imagensEnviadas = [];
$nomeImagemSanitizado = sanitizeFilename($nome_imagem);


$sqlTipoImagem = "SELECT tipo_imagem FROM imagens_cliente_obra WHERE idimagens_cliente_obra = $idimagem";
$resultTipo = $conn->query($sqlTipoImagem);
$tipoImagem = $resultTipo->fetch_assoc()['tipo_imagem'] ?? '';


for ($i = 0; $i < $totalImagens; $i++) {
    $numeroPrevia = $i + 1;

    $imagemAtual = [
        'name'     => $imagens['name'][$i],
        'tmp_name' => $imagens['tmp_name'][$i],
        'error'    => $imagens['error'][$i]
    ];

    if ($imagemAtual['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["error" => "Erro no upload temporário da imagem {$imagemAtual['name']}"]);
        exit;
    }

    // Nome final do arquivo (sem extensão)
    if ($nomeFuncao === 'Pós-produção' || $nomeFuncao === 'Alteração' || $tipoImagem === 'Planta Humanizada') {
        $nomeFinalSemExt = "{$nomeImagemSanitizado}_{$status_nome}_{$indice_envio}_{$numeroPrevia}";
    } else {
        $nomeFinalSemExt = "{$numeroImagem}.{$nomenclatura}-{$processo}-{$indice_envio}-{$numeroPrevia}";
    }

    $extensao = pathinfo($imagemAtual['name'], PATHINFO_EXTENSION);
    $arquivoRemoto = $ftp_base . $nomeFinalSemExt . "." . $extensao;

    list($ok, $msg) = enviarArquivoFTP($conn_ftp, $imagemAtual['tmp_name'], $arquivoRemoto);
    if (!$ok) {
        echo json_encode(["error" => $msg]);
        exit;
    }

    $caminhoBanco = 'uploads/' . $nomeFinalSemExt . "." . $extensao;


    // Salva no banco o caminho remoto
    $stmt = $conn->prepare("INSERT INTO historico_aprovacoes_imagens (funcao_imagem_id, imagem, indice_envio, nome_arquivo) 
                            VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $idFuncaoImagem, $caminhoBanco, $indice_envio, $nomeFinalSemExt);
    if ($stmt->execute()) {
        $imagensEnviadas[] = $caminhoBanco;
    } else {
        echo json_encode(["error" => "Erro ao salvar no banco: " . $stmt->error]);
        exit;
    }
    $stmt->close();
}

// Fecha conexão FTP
ftp_close($conn_ftp);

// ---------- Atualiza status ----------
$hoje = date('Y-m-d');
$stmt = $conn->prepare("UPDATE funcao_imagem 
                        SET status = 'Em aprovação', prazo = ? 
                        WHERE idfuncao_imagem = ?");
$stmt->bind_param("si", $hoje, $idFuncaoImagem);
if (!$stmt->execute()) {
    echo json_encode(["error" => "Erro ao atualizar status/prazo: " . $stmt->error]);
    exit;
}
$stmt->close();

echo json_encode([
    "success"      => "Imagens enviadas com sucesso via FTP!",
    "indice_envio" => $indice_envio,
    "imagens"      => $imagensEnviadas
]);
