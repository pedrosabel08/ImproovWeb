<?php
// Configurações do banco de dados
function loadEnvFile($envFile)
{
    if (!is_readable($envFile)) {
        exit("Erro ao carregar o arquivo .env.\n");
    }

    $env = [];
    foreach (file($envFile, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        $env[trim($key)] = $value;
    }

    return $env;
}

$env = loadEnvFile(dirname(__DIR__) . '/.env');

$host = $env['DB_HOST'] ?? '';
$port = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_DATABASE'] ?? '';
$user = $env['DB_USERNAME'] ?? '';
$password = $env['DB_PASSWORD'] ?? '';

if ($host === '' || $dbName === '' || $user === '' || $password === '') {
    exit("Variaveis de banco de dados ausentes no arquivo .env.\n");
}

$mysqldumpExecutable = $env['MYSQLDUMP_PATH'] ?? (dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'mysql' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysqldump.exe');
if (!is_file($mysqldumpExecutable)) {
    exit("mysqldump.exe nao foi encontrado. Configure MYSQLDUMP_PATH no .env.\n");
}


// Tabelas que serão incluídas no backup
$tabelas = ['funcao_imagem', 'obra', 'imagens_cliente_obra', 'acompanhamento_email'];

date_default_timezone_set('America/Sao_Paulo');


// Nome do arquivo de backup
$backupFile = __DIR__ . "/backup_tabelas_" . date('Y-m-d_H-i-s') . ".sql";
$temporaryFile = $backupFile . '.tmp';

// Comando para exportar apenas as tabelas específicas
$command = '"' . $mysqldumpExecutable . '"' . " --host=$host --port=$port --user=$user --password=$password $dbName " . implode(" ", $tabelas) . " > $temporaryFile";

// Executa o backup
exec($command, $output, $returnVar);

if ($returnVar === 0 && is_file($temporaryFile) && filesize($temporaryFile) > 0 && rename($temporaryFile, $backupFile)) {
    echo "Backup das tabelas realizado com sucesso: $backupFile";
} else {
    if (is_file($temporaryFile)) {
        unlink($temporaryFile);
    }

    echo "Erro ao fazer backup das tabelas.";
}

// Remove dumps incompletos e mantem apenas o ultimo backup valido.
$files = glob(__DIR__ . '/backup_tabelas_*.sql') ?: [];
foreach ($files as $file) {
    if (is_file($file) && filesize($file) === 0) {
        unlink($file);
    }
}

$files = array_values(array_filter($files, function ($file) {
    return is_file($file) && filesize($file) > 0;
}));
rsort($files, SORT_STRING);

while (count($files) > 1) {
    unlink(array_pop($files));
}
