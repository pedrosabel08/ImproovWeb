<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../helpers/mapa_permissoes_helper.php';

// O nível geral não concede administração espacial a outros colaboradores.
foreach ([1, 9, 21] as $id) {
    $session = ['logado' => true, 'idcolaborador' => $id, 'nivel_acesso' => 3];
    if (!mp_can_edit($session)) throw new RuntimeException("Administrador $id recusado.");
    mp_require_admin($session);
}
foreach ([[], ['logado' => false, 'idcolaborador' => 21],
    ['logado' => true, 'idcolaborador' => 34, 'nivel_acesso' => 1],
    ['logado' => true, 'idcolaborador' => 2, 'nivel_acesso' => 2],
    ['logado' => true, 'idcolaborador' => 13],
    ['logado' => true, 'idcolaborador' => 20]] as $session) {
    if (mp_can_edit($session)) throw new RuntimeException('Permissão indevida.');
    try {
        mp_require_admin($session);
        throw new LogicException('A operação deveria ser bloqueada.');
    } catch (RuntimeException $e) {
        if ($e->getCode() !== 403) throw $e;
    }
}
echo "Permissões do mapa/3D: OK\n";
