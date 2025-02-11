<?php
// Configurações do banco de dados
$host = 'mysql.improov.com.br';
$dbName = 'improov';
$user = 'improov';
$password = 'Impr00v';

// Nome do arquivo de backup
$backupFile = __DIR__ . "/backup_completo_" . date('Y-m-d') . ".sql";

// Comando para fazer backup completo
$command = "mysqldump --host=$host --user=$user --password=$password $dbName > $backupFile";

// Executa o backup
exec($command, $output, $returnVar);

if ($returnVar === 0) {
    echo "Backup completo realizado com sucesso: $backupFile\n";

    // Limitar a quantidade de backups
    $backupDir = __DIR__; // Diretório onde os backups são armazenados
    $files = glob($backupDir . '/backup_completo_*.sql'); // Pega todos os arquivos de backup completo

    // Ordena os arquivos por data (mais recentes primeiro)
    usort($files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    // Mantém apenas os 5 arquivos mais recentes
    while (count($files) > 5) {
        unlink(array_pop($files)); // Exclui o arquivo mais antigo
    }
} else {
    echo "Erro ao fazer backup do banco de dados.\n";
}
