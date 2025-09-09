<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Upload de Arquivo</title>
</head>

<body>
    <h2>Upload de Arquivo</h2>
    <form action="processa_upload.php" method="POST" enctype="multipart/form-data">
        <label>Projeto:</label>
        <input type="text" name="projeto" value="TES_TES" required><br><br>

        <label>Responsável pela revisão:</label>
        <input type="text" name="responsavel" required><br><br>

        <label>Arquivo:</label>
        <input type="file" name="arquivo" required><br><br>

        <button type="submit">Enviar</button>
    </form>
</body>

</html>