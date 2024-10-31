<?php
// Configurações do banco de dados
$host = 'mysql.improov.com.br';
$dbName = 'improov';
$user = 'improov';
$password = 'Impr00v';

// Nome do arquivo de backup (com data e hora)
$backupFile = __DIR__ . '/backup_' . $dbName . '_' . date('Y-m-d_H-i-s') . '.sql';

// Comando de dump do MySQL
$command = "mysqldump --host=$host --user=$user --password=$password $dbName > $backupFile";

// Executa o comando e verifica se deu certo
exec($command, $output, $returnVar);
if ($returnVar === 0) {
    echo "Backup realizado com sucesso: $backupFile";
} else {
    echo "Erro ao fazer backup do banco de dados.";
}
?>
