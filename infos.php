<?php
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
        iu.cnpj,
        iu.nome_fantasia,
        iu.nome_empresarial,
        iu.cpf,
        e.rua,
        e.numero,
        e.bairro,
        e.complemento,
        e.cep,
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

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/styleUsuario.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

    <title>Informações do Usuário</title>
</head>

<body class="flex items-center justify-center min-h-screen bg-gray-100">
    <button id="voltar" onclick="window.location.href='inicio.php'"><img src="assets/curva-seta-para-a-esquerda.png" alt=""></button>

    <div class="w-full max-w-[1000px] p-6 bg-white rounded-lg shadow-md">
        <h1 class="text-4xl mb-6 text-center">Informações:</h1>

        <form id="userForm">

            <fieldset class="mb-6">
                <legend class="text-2xl font-bold mb-4">Informações Básicas</legend>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">Nome:</h3>
                    <input class="border border-black w-full p-2 rounded" type="text" name="nome" id="nome"
                        value="<?php echo htmlspecialchars($userData['nome_usuario']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">Senha:</h3>
                    <input class="border border-black w-full p-2 rounded" type="text" name="senha" id="senha"
                        value="<?php echo htmlspecialchars($userData['senha']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">Email:</h3>
                    <input class="border border-black w-full p-2 rounded" type="email" name="email" id="email"
                        value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">Telefone:</h3>
                    <input class="border border-black w-full p-2 rounded" type="text" name="telefone" id="telefone"
                        value="<?php echo htmlspecialchars($userData['telefone']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">CPF:</h3>
                    <input class="border border-black w-full p-2 rounded" type="text" name="cpf" id="cpf"
                        value="<?php echo htmlspecialchars($userData['cpf']); ?>" required>
                </div>
            </fieldset>

            <!-- Seção: Endereço -->
            <fieldset class="mb-6">
                <legend class="text-2xl font-bold mb-4">Endereço</legend>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">CEP:</h3>
                    <input class="border border-black w-full p-2 rounded" onkeyup="buscaEndereco(this.value);"
                        type="number" id="cep" name="cep"
                        value="<?php echo htmlspecialchars($userData['cep']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">Bairro:</h3>
                    <input class="border border-black w-full p-2 rounded" type="text" id="bairro" name="bairro"
                        value="<?php echo htmlspecialchars($userData['bairro']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">Rua:</h3>
                    <input class="border border-black w-full p-2 rounded" type="text" id="rua" name="rua"
                        value="<?php echo htmlspecialchars($userData['rua']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">N°:</h3>
                    <input class="border border-black w-full p-2 rounded" type="number" id="numero" name="numero"
                        value="<?php echo htmlspecialchars($userData['numero']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">Complemento:</h3>
                    <input class="border border-black w-full p-2 rounded" type="text" maxlength="45" id="complemento"
                        name="complemento" value="<?php echo htmlspecialchars($userData['complemento']); ?>">
                </div>
            </fieldset>

            <fieldset class="mb-6">
                <legend class="text-2xl font-bold mb-4">Cadastro CNPJ</legend>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">CNPJ:</h3>
                    <input class="border border-black w-full p-2 rounded" type="text" id="cnpj" name="cnpj"
                        value="<?php echo htmlspecialchars($userData['cnpj']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">Nome empresarial:</h3>
                    <input class="border border-black w-full p-2 rounded" type="text" id="nome_empresarial" name="nome_empresarial"
                        value="<?php echo htmlspecialchars($userData['nome_empresarial']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">Nome fantasia:</h3>
                    <input class="border border-black w-full p-2 rounded" type="text" id="nome_fantasia" name="nome_fantasia"
                        value="<?php echo htmlspecialchars($userData['nome_fantasia']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">CEP:</h3>
                    <input class="border border-black w-full p-2 rounded" onkeyup="buscaEnderecoCNPJ(this.value);"
                        type="number" id="cep_cnpj" name="cep_cnpj"
                        value="<?php echo htmlspecialchars($userData['cep_cnpj']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">Bairro:</h3>
                    <input class="border border-black w-full p-2 rounded" type="text" id="bairro_cnpj" name="bairro_cnpj"
                        value="<?php echo htmlspecialchars($userData['bairro_cnpj']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">UF:</h3>
                    <input class="border border-black w-full p-2 rounded" type="text" id="uf_cnpj" name="uf_cnpj"
                        value="<?php echo htmlspecialchars($userData['uf_cnpj']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">Localidade:</h3>
                    <input class="border border-black w-full p-2 rounded" type="text" id="localidade_cnpj" name="localidade_cnpj"
                        value="<?php echo htmlspecialchars($userData['localidade_cnpj']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">Rua:</h3>
                    <input class="border border-black w-full p-2 rounded" type="text" id="rua_cnpj" name="rua_cnpj"
                        value="<?php echo htmlspecialchars($userData['rua_cnpj']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">N°:</h3>
                    <input class="border border-black w-full p-2 rounded" type="number" id="numero_cnpj" name="numero_cnpj"
                        value="<?php echo htmlspecialchars($userData['numero_cnpj']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">Complemento:</h3>
                    <input class="border border-black w-full p-2 rounded" type="text" maxlength="45" id="complemento_cnpj"
                        name="complemento_cnpj" value="<?php echo htmlspecialchars($userData['complemento_cnpj']); ?>">
                </div>
            </fieldset>

            <!-- Seção: Filhos e Estado Civil -->
            <fieldset class="mb-6">
                <legend class="text-2xl font-bold mb-4">Filhos e Estado Civil</legend>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">Data de Nascimento:</h3>
                    <input class="border border-black w-full p-2 rounded" type="date" name="data" id="data"
                        value="<?php echo htmlspecialchars($userData['data_nascimento']); ?>" required>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">Estado Civil:</h3>
                    <select class="border border-black w-full p-2 rounded" name="estado_civil" id="estado_civil">
                        <option value="Solteiro" <?php echo ($userData['estado_civil'] === 'Solteiro') ? 'selected' : ''; ?>>Solteiro</option>
                        <option value="Casado" <?php echo ($userData['estado_civil'] === 'Casado') ? 'selected' : ''; ?>>Casado</option>
                        <option value="Divorciado" <?php echo ($userData['estado_civil'] === 'Divorciado') ? 'selected'
                                                        : ''; ?>>Divorciado</option>
                        <option value="Viúvo" <?php echo ($userData['estado_civil'] === 'Viúvo') ? 'selected' : ''; ?>>Viúvo</option>
                    </select>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg mb-2">Filhos:</h3>
                    <input class="border border-black w-full p-2 rounded" type="text" name="filho" id="filho"
                        value="<?php echo htmlspecialchars($userData['filhos']); ?>">
                </div>
            </fieldset>

            <fieldset class="mb-6">
                <legend class="text-2xl font-bold mb-4">Perfil do Colaborador</legend>

                <div class="mb-4">
                    <h3 class="text-lg mb-2">Horário Disponível:</h3>
                    <input class="border border-black w-full p-2 rounded"
                        type="text"
                        name="horario_disponivel"
                        id="horario_disponivel"
                        placeholder="Ex: Seg a Sex, 09h às 18h"
                        value="<?php echo htmlspecialchars($perfilData['horario_disponivel'] ?? ''); ?>">
                </div>

                <div class="mb-4">
                    <h3 class="text-lg mb-2">Modalidade:</h3>
                    <select class="border border-black w-full p-2 rounded"
                        name="modalidade"
                        id="modalidade">
                        <?php
                        $modalidades = ['Presencial', 'Híbrido', 'Remoto'];
                        foreach ($modalidades as $modo) {
                            $selected = ($perfilData['modalidade'] ?? '') === $modo ? 'selected' : '';
                            echo "<option value=\"$modo\" $selected>$modo</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="mb-4">
                    <h3 class="text-lg mb-2">Tamanho da Camisa:</h3>
                    <input class="border border-black w-full p-2 rounded"
                        type="text"
                        name="tamanho_camisa"
                        id="tamanho_camisa"
                        placeholder="Ex: P, M, G, GG"
                        value="<?php echo htmlspecialchars($perfilData['tamanho_camisa'] ?? ''); ?>">
                </div>

                <div class="mb-4">
                    <h3 class="text-lg mb-2">Tamanho do Calçado:</h3>
                    <input class="border border-black w-full p-2 rounded"
                        type="text"
                        name="tamanho_calcado"
                        id="tamanho_calcado"
                        placeholder="Ex: 37, 38, 39, 40"
                        value="<?php echo htmlspecialchars($perfilData['tamanho_calcado'] ?? ''); ?>">
                </div>

                <div class="mb-4">
                    <h3 class="text-lg mb-2">Observações:</h3>
                    <textarea class="border border-black w-full p-2 rounded"
                        name="observacoes"
                        id="observacoes"
                        rows="4"
                        placeholder="Informe alergias, restrições alimentares, preferências, entre outras observações."><?php echo htmlspecialchars(trim($perfilData['observacoes'] ?? '')); ?></textarea>
                </div>
            </fieldset>

            <div class="mt-6">
                <button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded w-full">Atualizar informações</button>
            </div>
        </form>
    </div>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script src="./script/scriptUsuario.js"></script>
</body>

</html>