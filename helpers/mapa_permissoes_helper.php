<?php

/** Administração do mapa e do prédio: Nicolle, André L. e Pedro. */
function mp_can_edit(array $session): bool
{
    return ($session['logado'] ?? false) === true
        && in_array((int)($session['idcolaborador'] ?? 0), [1, 9, 21], true);
}

function mp_require_admin(array $session): void
{
    if (!mp_can_edit($session)) {
        throw new RuntimeException('Somente administradores podem alterar o mapa ou o modelo 3D.', 403);
    }
}
