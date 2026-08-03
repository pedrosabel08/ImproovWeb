<?php
return static function (array $p): array {
    $operation = flow_connect_tpl_escape($p['operacao'] ?? 'upload');
    $attempt = max(1, (int) ($p['tentativa'] ?? 1));
    $error = flow_connect_tpl_escape(flow_connect_tpl_truncate($p['erro_tecnico_seguro'] ?? 'operation_failed', 240));
    return flow_connect_tpl_message(flow_connect_tpl_title_with_actor('🚨 Falha técnica no envio SFTP.', $p), $p, "Operação: *{$operation}* · tentativa {$attempt}\nErro seguro: `{$error}`\nO status de negócio não foi alterado.");
};
