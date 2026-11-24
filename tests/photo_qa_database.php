<?php
// Local test support. Creates only a randomly named schema and a restricted account on loopback.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$qa = json_decode(stream_get_contents(STDIN), true, 16, JSON_THROW_ON_ERROR);
$name = $qa['schema'];
if (!preg_match('/^qa_photo_[a-f0-9]{16}$/D', $name) || !preg_match('/^[a-f0-9]{32}$/D', $qa['password'])) exit(2);
$db = new mysqli('127.0.0.1', 'root', '', null, (int)($qa['port'] ?? 33791));
$db->set_charset('utf8mb4');
if ($argv[1] === 'dispose') {
    $db->query("DROP DATABASE IF EXISTS `$name`");
    $db->query("DROP USER IF EXISTS '$name'@'127.0.0.1'");
    exit;
}
$db->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
$db->select_db($name);
$db->query("CREATE USER '$name'@'127.0.0.1' IDENTIFIED BY '{$qa['password']}'");
$db->query("GRANT SELECT, INSERT, UPDATE, DELETE ON `$name`.* TO '$name'@'127.0.0.1'");
foreach ([
    'obra'=>'idobra INT PRIMARY KEY,nome_obra VARCHAR(80),nomenclatura VARCHAR(20),status_obra INT',
    'obra_pacote'=>'obra_id INT,tipo VARCHAR(20),status VARCHAR(20)',
    'colaborador'=>'idcolaborador INT PRIMARY KEY,nome_colaborador VARCHAR(80)',
    'imagens_cliente_obra'=>'idimagens_cliente_obra INT PRIMARY KEY,obra_id INT,imagem_nome VARCHAR(150),tipo_imagem VARCHAR(80),largura INT,altura INT',
    'mapa_modelos_3d'=>'id INT PRIMARY KEY,obra_id INT',
    'mapa_modelo_3d_versoes'=>'id INT PRIMARY KEY,modelo_id INT,numero INT,arquivo VARCHAR(80)'
] as $table=>$columns) $db->query("CREATE TABLE $table ($columns) ENGINE=InnoDB");
$obra=(int)$qa['obra']; $model=(int)$qa['model']; $version=(int)$qa['version'];
$db->query("INSERT INTO obra VALUES ($obra,'Obra de homologação','QA',0)");
$db->query("INSERT INTO obra_pacote VALUES ($obra,'STILL','ATIVO')");
$db->query("INSERT INTO colaborador VALUES (1,'QA 1'),(9,'QA 9'),(21,'QA 21'),(2,'QA 2')");
$db->query("INSERT INTO imagens_cliente_obra VALUES (31,$obra,'01. Fachada principal','Fachada',4,5),(32,$obra,'02. Vista aérea','Externa',16,9)");
$db->query("INSERT INTO mapa_modelos_3d VALUES ($model,$obra)");
$db->query("INSERT INTO mapa_modelo_3d_versoes VALUES ($version,$model,1,'qa.glb')");
$db->query(file_get_contents(__DIR__.'/../sql/2026-11-24_imagem_enquadramentos_3d.sql'));

