<?php
return static function (array $p): array {
    return flow_connect_tpl_message(flow_connect_tpl_title_with_actor('⏳ Validação da direção solicitada.', $p), $p);
};
