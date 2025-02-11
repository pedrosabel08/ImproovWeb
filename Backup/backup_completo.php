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

    // Git commands
    $gitDir = 'C:/xampp/htdocs/ImproovWeb';  // Caminho para o repositório Git

    // Garante que está na branch Backup
    exec("cd $gitDir && git checkout Backup", $gitOutput, $gitReturnVar);
    if ($gitReturnVar !== 0) {
        echo "Erro ao trocar para a branch Backup: " . implode("\n", $gitOutput);
        exit;
    }

    // Adiciona todos os arquivos da pasta Backup
    exec("cd $gitDir && git add Backup/*", $gitOutput, $gitReturnVar);
    if ($gitReturnVar !== 0) {
        echo "Erro ao adicionar arquivos ao Git: " . implode("\n", $gitOutput);
        exit;
    }

    // Realiza o commit com a data/hora
    exec("cd $gitDir && git commit -m 'Backup automático: " . date('Y-m-d H:i:s') . "'", $gitOutput, $gitReturnVar);
    if ($gitReturnVar === 0) {
        echo "Commit do backup realizado com sucesso: " . implode("\n", $gitOutput) . "\n";
    } else {
        echo "Erro ao fazer commit: " . implode("\n", $gitOutput) . "\n";
        exit;
    }

    // Push para o repositório remoto
    exec("cd $gitDir && git push origin Backup", $gitOutput, $gitReturnVar);
    if ($gitReturnVar === 0) {
        echo "Push realizado com sucesso para o repositório remoto.\n";
    } else {
        echo "Erro ao fazer push para o repositório remoto: " . implode("\n", $gitOutput) . "\n";
        exit;
    }

    echo "Backup completo realizado com sucesso: $backupFile";

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
    echo "Erro ao fazer backup do banco de dados.";
}
