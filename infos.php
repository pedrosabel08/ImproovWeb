<?php
require_once __DIR__ . '/config/session_bootstrap.php';
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

session_start();
if (!isset($_SESSION['idusuario'])) {
    // Redirecionar para a página de login se não estiver autenticado
    header("Location: login.php");
    exit();
}

include 'conexao.php';

// Obter informações do usuário
$usuario_id = $_SESSION['idusuario'];
$colaborador_id = $_SESSION['idcolaborador'];
$query = "
    SELECT 
        u.nome_usuario,
        u.senha,
        u.email,
        iu.telefone,
        iu.data_nascimento,
        iu.estado_civil,
        iu.filhos,
        iu.thumb,
        iu.cnpj,
        iu.nome_fantasia,
        iu.nome_empresarial,
        iu.cpf,
        e.rua,
        e.numero,
        e.bairro,
        e.complemento,
        e.cep,
        e.uf,
        e.localidade,
        ec.rua_cnpj,
        ec.numero_cnpj,
        ec.bairro_cnpj,
        ec.complemento_cnpj,
        ec.cep_cnpj,
        ec.uf_cnpj,
        ec.localidade_cnpj
        FROM 
        usuario u
    LEFT JOIN 
        informacoes_usuario iu ON u.idusuario = iu.usuario_id
    LEFT JOIN 
        endereco e ON u.idusuario = e.usuario_id
    LEFT JOIN 
        endereco_cnpj ec ON u.idusuario = ec.usuario_id
    WHERE 
        u.idusuario = ?
";

// Prepara a consulta
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();

$stmt->close();

$sql = "SELECT * FROM perfil_colaborador WHERE colaborador_id = ?";
// Prepara a consulta
$stmt2 = $conn->prepare($sql);
$stmt2->bind_param("i", $colaborador_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
$perfilData = $result2->fetch_assoc();


$stmt2->close();
$conn->close();
?>

<?php
include 'conexaoMain.php';

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();


?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informações do Usuário</title>
    <link rel="stylesheet" href="<?php echo asset_url('./css/styleUsuario.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('./css/styleSidebar.css'); ?>">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
</head>

<body>

    <?php

    include 'sidebar.php';

    ?>
    <div class="container user-page">
        <header class="page-hero">
            <div class="hero-copy">
                <span class="hero-kicker">Meu perfil</span>
                <h1>Informacoes</h1>
                <p>Atualize seus dados e mantenha o perfil sempre alinhado.</p>
            </div>
        </header>
        <form id="userForm" class="user-form" enctype="multipart/form-data">

            <div class="profile-row">
                <div class="avatar-block">
                    <?php if (!empty($userData['thumb'])): ?>
                        <img id="avatarPreview" src="<?php echo htmlspecialchars($userData['thumb']); ?>" alt="Avatar" />
                    <?php else: ?>
                        <div id="avatarPreview" class="avatar-initial"><?php echo (!empty($userData['nome_usuario'])?htmlspecialchars(mb_strtoupper(mb_substr($userData['nome_usuario'],0,1,'UTF-8'))):''); ?></div>
                    <?php endif; ?>
                </div>
                <div class="avatar-actions">
                    <label class="file-label">Escolher imagem
                        <input type="file" name="thumb" id="thumb" accept="image/*">
                    </label>
                    <small class="muted">Tamanho máximo recomendado: 5MB. Formatos: JPG, PNG, WEBP.</small>
                </div>
            </div>

            <fieldset class="form-card">
                <legend>Informacoes basicas</legend>
                <div class="form-group">
                    <label for="nome">Nome:</label>
                    <input type="text" name="nome" id="nome"
                        value="<?php echo htmlspecialchars($userData['nome_usuario']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="senha">Senha:</label>
                    <input type="text" name="senha" id="senha"
                        value="<?php echo htmlspecialchars($userData['senha']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email"
                        value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone:</label>
                    <input type="text" name="telefone" id="telefone"
                        value="<?php echo htmlspecialchars($userData['telefone']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="cpf">CPF:</label>
                    <input type="text" name="cpf" id="cpf" value="<?php echo htmlspecialchars($userData['cpf']); ?>"
                        required>
                </div>
            </fieldset>

            <fieldset class="form-card">
                <legend>Endereco</legend>
                <div class="form-group">
                    <label for="cep">CEP:</label>
                    <input type="number" id="cep" name="cep" onkeyup="buscaEndereco(this.value);"
                        value="<?php echo htmlspecialchars($userData['cep']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="bairro">Bairro:</label>
                    <input type="text" id="bairro" name="bairro"
                        value="<?php echo htmlspecialchars($userData['bairro']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="rua">Rua:</label>
                    <input type="text" id="rua" name="rua" value="<?php echo htmlspecialchars($userData['rua']); ?>"
                        required>
                </div>
                <div class="form-group">
                    <label for="numero">N°:</label>
                    <input type="number" id="numero" name="numero"
                        value="<?php echo htmlspecialchars($userData['numero']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="complemento">Complemento:</label>
                    <input type="text" id="complemento" name="complemento" maxlength="45"
                        value="<?php echo htmlspecialchars($userData['complemento']); ?>">
                </div>
                <div class="form-group">
                    <label for="uf">UF:</label>
                    <input type="text" id="uf" name="uf" maxlength="45"
                        value="<?php echo htmlspecialchars($userData['uf']); ?>">
                </div>
                <div class="form-group">
                    <label for="localidade">Localidade:</label>
                    <input type="text" id="localidade" name="localidade" maxlength="45"
                        value="<?php echo htmlspecialchars($userData['localidade']); ?>">
                </div>
            </fieldset>

            <fieldset class="form-card">
                <legend>Cadastro CNPJ</legend>
                <div class="form-group">
                    <label for="cnpj">CNPJ:</label>
                    <input type="text" id="cnpj" name="cnpj" value="<?php echo htmlspecialchars($userData['cnpj']); ?>"
                        >
                </div>
                <div class="form-group">
                    <label for="nome_empresarial">Nome empresarial:</label>
                    <input type="text" id="nome_empresarial" name="nome_empresarial"
                        value="<?php echo htmlspecialchars($userData['nome_empresarial']); ?>" >
                </div>
                <div class="form-group">
                    <label for="nome_fantasia">Nome fantasia:</label>
                    <input type="text" id="nome_fantasia" name="nome_fantasia"
                        value="<?php echo htmlspecialchars($userData['nome_fantasia']); ?>" >
                </div>
                <div class="form-group">
                    <label for="cep_cnpj">CEP:</label>
                    <input type="number" id="cep_cnpj" name="cep_cnpj" onkeyup="buscaEnderecoCNPJ(this.value);"
                        value="<?php echo htmlspecialchars($userData['cep_cnpj']); ?>" >
                </div>
                <div class="form-group">
                    <label for="bairro_cnpj">Bairro:</label>
                    <input type="text" id="bairro_cnpj" name="bairro_cnpj"
                        value="<?php echo htmlspecialchars($userData['bairro_cnpj']); ?>" >
                </div>
                <div class="form-group">
                    <label for="uf_cnpj">UF:</label>
                    <input type="text" id="uf_cnpj" name="uf_cnpj"
                        value="<?php echo htmlspecialchars($userData['uf_cnpj']); ?>" >
                </div>
                <div class="form-group">
                    <label for="localidade_cnpj">Localidade:</label>
                    <input type="text" id="localidade_cnpj" name="localidade_cnpj"
                        value="<?php echo htmlspecialchars($userData['localidade_cnpj']); ?>" >
                </div>
                <div class="form-group">
                    <label for="rua_cnpj">Rua:</label>
                    <input type="text" id="rua_cnpj" name="rua_cnpj"
                        value="<?php echo htmlspecialchars($userData['rua_cnpj']); ?>">
                </div>
                <div class="form-group">
                    <label for="numero_cnpj">N°:</label>
                    <input type="number" id="numero_cnpj" name="numero_cnpj"
                        value="<?php echo htmlspecialchars($userData['numero_cnpj']); ?>" >
                </div>
                <div class="form-group">
                    <label for="complemento_cnpj">Complemento:</label>
                    <input type="text" id="complemento_cnpj" name="complemento_cnpj" maxlength="45"
                        value="<?php echo htmlspecialchars($userData['complemento_cnpj']); ?>">
                </div>
            </fieldset>

            <fieldset class="form-card">
                <legend>Filhos e estado civil</legend>
                <div class="form-group">
                    <label for="data">Data de Nascimento:</label>
                    <input type="date" name="data" id="data"
                        value="<?php echo htmlspecialchars($userData['data_nascimento']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="estado_civil">Estado Civil:</label>
                    <select name="estado_civil" id="estado_civil">
                        <option value="Solteiro" <?php echo ($userData['estado_civil'] === 'Solteiro') ? 'selected' : ''; ?>>Solteiro</option>
                        <option value="Casado" <?php echo ($userData['estado_civil'] === 'Casado') ? 'selected' : ''; ?>>
                            Casado</option>
                        <option value="Divorciado" <?php echo ($userData['estado_civil'] === 'Divorciado') ? 'selected' : ''; ?>>Divorciado</option>
                        <option value="Viúvo" <?php echo ($userData['estado_civil'] === 'Viúvo') ? 'selected' : ''; ?>>
                            Viúvo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filho">Filhos:</label>
                    <input type="text" name="filho" id="filho"
                        value="<?php echo htmlspecialchars($userData['filhos']); ?>">
                </div>
            </fieldset>

            <fieldset class="form-card">
                <legend>Perfil do colaborador</legend>
                <div class="form-group">
                    <label for="horario_disponivel">Horário Disponível:</label>
                    <input type="text" name="horario_disponivel" id="horario_disponivel"
                        placeholder="Ex: Seg a Sex, 09h às 18h"
                        value="<?php echo htmlspecialchars($perfilData['horario_disponivel'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="modalidade">Modalidade:</label>
                    <select name="modalidade" id="modalidade">
                        <?php
                        $modalidades = ['Presencial', 'Híbrido', 'Remoto'];
                        foreach ($modalidades as $modo) {
                            $selected = ($perfilData['modalidade'] ?? '') === $modo ? 'selected' : '';
                            echo "<option value=\"$modo\" $selected>$modo</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tamanho_camisa">Tamanho da Camisa:</label>
                    <input type="text" name="tamanho_camisa" id="tamanho_camisa" placeholder="Ex: P, M, G, GG"
                        value="<?php echo htmlspecialchars($perfilData['tamanho_camisa'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="tamanho_calcado">Tamanho do Calçado:</label>
                    <input type="text" name="tamanho_calcado" id="tamanho_calcado" placeholder="Ex: 37, 38, 39, 40"
                        value="<?php echo htmlspecialchars($perfilData['tamanho_calcado'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="observacoes">Observações:</label>
                    <textarea name="observacoes" id="observacoes" rows="4"
                        placeholder="Informe alergias, restrições alimentares, preferências, entre outras observações."><?php echo htmlspecialchars(trim($perfilData['observacoes'] ?? '')); ?></textarea>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn-submit">Atualizar informacoes</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="<?php echo asset_url('./script/scriptUsuario.js'); ?>"></script>
    <script src="<?php echo asset_url('./script/sidebar.js'); ?>"></script>
</body>

</html>