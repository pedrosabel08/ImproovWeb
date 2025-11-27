<?php
// Cookie-only authentication: validate `flow_auth` cookie and expose `idusuario` to frontend.
require_once __DIR__ . '/../conexao.php';

$idusuario = null;
if (isset($_COOKIE['flow_auth']) && !empty($_COOKIE['flow_auth'])) {
    $token = $_COOKIE['flow_auth'];
    $stmt = $conn->prepare("SELECT user_id, expires_at FROM login_tokens WHERE token_hash = SHA2(?, 256) LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (strtotime($row['expires_at']) > time()) {
                // Token válido → define idusuario para uso no frontend
                $idusuario = (int) $row['user_id'];

                // Renovar cookie (estende validade por mais 2 dias)
                $expires = time() + 86400 * 2;
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                setcookie("flow_auth", $token, $expires, "/", "", $secure, true);
            }
        }
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css"
        integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <!-- <link rel="stylesheet" href="../css/styleSidebar.css"> -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/tributejs@5.1.3/dist/tribute.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://unpkg.com/tabulator-tables@5.5.0/dist/css/tabulator.min.css" rel="stylesheet">


    <title>Flow Review</title>
</head>

<body>
    <?php if (empty($idusuario)): ?>
        <div class="auth-page">
            <main class="auth-card">
                <h1>Entrar ou Registrar</h1>
                <p class="muted">Acesse o Flow Review com sua conta</p>

                <div class="auth-tabs">
                    <button id="showLogin" class="button primary" style="margin-right:8px;">Entrar</button>
                    <button id="showRegister" class="button ghost">Registrar</button>
                </div>

                <div id="loginBox" style="margin-top:12px;">
                    <form id="loginForm" action="auth_login.php" method="post" autocomplete="off">
                        <label>E-mail
                            <input type="email" name="email" required maxlength="150">
                        </label>
                        <label>Senha
                            <input type="password" name="senha" required minlength="6">
                        </label>
                        <div class="actions">
                            <button type="submit" class="button primary">Entrar</button>
                        </div>
                    </form>
                </div>

                <div id="registerBox" style="display:none; margin-top:12px;">
                    <form id="registerForm" action="auth_register.php" method="post" autocomplete="off">
                        <label>Nome
                            <input type="text" name="nome_usuario" required maxlength="100">
                        </label>
                        <label>E-mail
                            <input type="email" name="email" required maxlength="150">
                        </label>
                        <label>Senha
                            <input type="password" name="senha" required minlength="6">
                        </label>
                        <label>Cargo
                            <input type="text" name="cargo" required maxlength="80">
                        </label>
                        <div class="actions">
                            <button type="submit" class="button primary">Criar conta</button>
                        </div>
                    </form>
                </div>

                <p style="margin-top:10px;font-size:12px;color:#6b7280;">Ao entrar, um cookie seguro será definido por 2
                    dias.</p>
            </main>
        </div>

        <!-- auth UI is controlled from script.js -->

    <?php endif; ?>

    <?php if (!empty($idusuario)): ?>
        <script>
            // make idusuario available to client-side code
            try {
                localStorage.setItem('idusuario', '<?php echo $idusuario; ?>');
            } catch (e) {
                /* ignore */ }
        </script>
    <?php endif; ?>
    <div class="main">



        <div class="container-main">
            <select id="filtroFuncao" style="display: none;">
                <option value="">Todas as funções</option>
            </select>
            <div class="containerObra"></div>
            <div class="tarefasObra hidden">
                <div class="header">
                    <nav class="breadcrumb-nav">
                        <a href="https://improov.com.br/sistema/Revisao/index2.php">Flow Review</a>
                        <a id="obra_id_nav" class="obra_nav"
                            href="https://improov.com.br/sistema/Revisao/index2.php?obra_id=57">Obra</a>
                    </nav>
                    <div class="filtros">
                        <div>
                            <label for="filtro_colaborador">Colaborador:</label>
                            <select name="filtro_colaborador" id="filtro_colaborador"></select>
                        </div>
                        <input type="hidden" name="filtro_obra" id="filtro_obra">
                    </div>
                </div>
                <div class="tarefasImagensObra"></div>
            </div>
        </div>
    </div>

    <div class="container-aprovacao hidden">
        <header>
            <div class="task-info" id="task-info">
                <h3 id="imagem_nome"></h3>
            </div>
        </header>



        <div class="imagens">
            <div class="wrapper-sidebar">
                <div id="sidebarTabulator" class="sidebar-min"></div>
            </div>
            <nav>
                <div id="imagens"></div>
            </nav>
            <div id="imagem_completa">
                <div class="nav-select">
                    <select id="indiceSelect"></select>
                    <div class="buttons">
                        <button id="reset-zoom"><i class="fa-solid fa-compress"></i></button>
                        <button id="btn-menos-zoom"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                        <button id="btn-mais-zoom"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                        <button id="btn-download-imagem"><i class="fa-solid fa-download"></i></button>
                    </div>
                </div>
                <div id="image_wrapper" class="image_wrapper">
                </div>
            </div>
            <div class="sidebar-direita">
                <button id="submit_decision">Enviar aprovação</button>

                <!-- Modal -->
                <div id="decisionModal" class="modal-decision hidden">
                    <div class="modal-content-decision">
                        <span class="close">&times;</span>
                        <label><input type="radio" name="decision" value="aprovado"> Aprovado</label><br>
                        <label><input type="radio" name="decision" value="aprovado_com_ajustes"> Aprovado com
                            ajustes</label><br>
                        <label><input type="radio" name="decision" value="ajuste"> Ajuste</label><br>

                        <div class="modal-observacao">
                            <textarea id="decisionObservation" rows="3" placeholder="Observação (opcional)" style="width:100%; padding:6px; margin-top:8px;"></textarea>
                        </div>

                        <div class="modal-footer">
                            <button id="cancelBtn" class="cancel-btn">Cancel</button>
                            <button id="confirmBtn" class="confirm-btn">Confirm</button>
                        </div>
                    </div>
                </div>
                <div id="decisoes" class="decisoes"></div>
                <div class="comentarios"></div>
            </div>
        </div>
    </div>
    <ul id="menuContexto">
        <li onclick="excluirImagem()">Excluir <span>🗑️</span></li>
    </ul>
    <div id="comentarioModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Novo Comentário</h3>
            <textarea id="comentarioTexto" rows="5" placeholder="Digite um comentário..."
                style="width: calc(100% - 10px); padding: 5px;"></textarea>
            <input type="file" id="imagemComentario" accept="image/*" />
            <div class="modal-actions">
                <button id="enviarComentario" style="background-color: green;">Enviar</button>
                <button id="fecharComentarioModal" style="background-color: red;">Cancelar</button>
            </div>
        </div>
    </div>


    <div id="modal-imagem" class="modal-imagem" onclick="fecharImagemModal()">
        <img id="imagem-ampliada" src="" alt="Imagem ampliada">
    </div>

    <!-- Modal -->
    <div id="imagem-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Enviar Imagens</h2>
            <input type="file" id="input-imagens" multiple accept="image/*">
            <div id="preview" class="preview-container"></div>
            <button id="btn-enviar-imagens">Enviar</button>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://unpkg.com/tributejs@5.1.3/dist/tribute.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/tabulator-tables@5.5.0/dist/js/tabulator.min.js"></script>


    <script src="script.js"></script>

</body>

</html>