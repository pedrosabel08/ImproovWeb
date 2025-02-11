<?php
// Configurações do banco de dados
$host = 'mysql.improov.com.br';
$dbName = 'improov';
$user = 'improov';
$password = 'Impr00v';

// Tabelas que serão incluídas no backup
$tabelas = ['funcao_imagem', 'obra', 'imagens_cliente_obra', 'acompanhamento_email'];

date_default_timezone_set('America/Sao_Paulo');

// Nome do arquivo de backup
$backupFile = __DIR__ . "/backup_tabelas_" . date('Y-m-d_H-i-s') . ".sql";

// Comando para exportar apenas as tabelas específicas
$command = "mysqldump --host=$host --user=$user --password=$password $dbName " . implode(" ", $tabelas) . " > $backupFile";

// Executa o backup
exec($command, $output, $returnVar);

if ($returnVar === 0) {
    echo "Backup das tabelas realizado com sucesso: $backupFile\n";

    // Limitar a quantidade de backups
    $backupDir = __DIR__; // Diretório onde os backups são armazenados
    $files = glob($backupDir . '/backup_tabelas_*.sql'); // Pega todos os arquivos de backup das tabelas

    // Ordena os arquivos por data (mais recentes primeiro)
    usort($files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    // Mantém apenas o último arquivo
    while (count($files) > 1) {
        unlink(array_pop($files)); // Exclui os arquivos mais antigos, mantendo o mais recente
    }

    // Comandos Git para commit e push automático
    chdir($backupDir); // Muda para o diretório do backup

    exec("git checkout Backup", $gitOutput, $gitReturnVar);
    if ($gitReturnVar !== 0) {
        exec("git checkout -b Backup"); // Cria a branch se não existir
    }

    exec("git add backup_tabelas_*.sql"); // Adiciona os arquivos ao Git
    exec('git commit -m "Backup automático ' . date('Y-m-d H:i:s') . '"'); // Faz o commit
    exec("git push origin Backup"); // Envia para o repositório remoto

    echo "Backup commitado e enviado para o repositório na branch Backup.\n";
} else {
    echo "Erro ao fazer backup das tabelas.\n";
}