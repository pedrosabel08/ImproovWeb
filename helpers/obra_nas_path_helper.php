<?php

require_once __DIR__ . '/../config/secure_env.php';

/** A project's storage year is a physical location, never a date inferred from a delivery. */
function flow_obra_nas_descobrir(string $nomenclatura, array $anos, callable $existe): array
{
    if ($nomenclatura === '' || preg_match('/[\\\\\/:*?"<>|\x00-\x1f]/u', $nomenclatura)
        || in_array($nomenclatura, ['.', '..'], true)) {
        return ['estado' => 'NAO_IDENTIFICADO', 'base' => null];
    }
    $encontradas = [];
    foreach (array_unique($anos) as $ano) {
        if (!preg_match('/^[12][0-9]{3}$/D', (string) $ano)) {
            continue;
        }
        if ($existe((string) $ano, $nomenclatura)) {
            $encontradas[] = 'Z:\\' . $ano . '\\' . $nomenclatura;
        }
    }
    return count($encontradas) === 1 ? ['estado' => 'IDENTIFICADO', 'base' => $encontradas[0]]
        : ['estado' => $encontradas ? 'AMBIGUO' : 'NAO_IDENTIFICADO', 'base' => null];
}

function flow_obra_nas_base(int $obraId, string $nomenclatura): array
{
    try {
        $cfg = improov_sftp_config();
        $root = rtrim(improov_env('IMPROOV_SFTP_CLIENTES_ROOT', '/mnt/clientes'), '/');
        // Private server cache, shared by sessions; no public asset or database migration.
        $key = hash('sha256', $cfg['host'] . ':' . $cfg['port'] . ':' . $root . ':' . $obraId . ':' . $nomenclatura);
        $cache = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flow-obra-nas-' . $key . '.json';
        if (is_file($cache) && filemtime($cache) > time() - 86400) {
            $saved = json_decode((string) file_get_contents($cache), true);
            if (($saved['estado'] ?? '') === 'IDENTIFICADO'
                && preg_match('/^Z:\\\\[12][0-9]{3}\\\\/D', (string) ($saved['base'] ?? ''))
                && substr($saved['base'], 8) === $nomenclatura) {
                return $saved;
            }
        }
        require_once __DIR__ . '/../vendor/autoload.php';
        $sftp = new \phpseclib3\Net\SFTP($cfg['host'], (int) $cfg['port'], 8);
        if (!$sftp->login($cfg['user'], $cfg['pass'])) {
            throw new RuntimeException('NAS indisponível.');
        }
        $anos = $sftp->nlist($root);
        if (!is_array($anos)) {
            throw new RuntimeException('Não foi possível consultar as pastas.');
        }
        $result = flow_obra_nas_descobrir(
            $nomenclatura,
            $anos,
            static fn ($ano, $nome) => $sftp->is_dir($root . '/' . $ano . '/' . $nome)
        );
        if ($result['base']) {
            if (@file_put_contents($cache, json_encode($result, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
                @chmod($cache, 0600);
            }
        }
        $sftp->disconnect();
        return $result;
    } catch (Throwable $e) {
        return ['estado' => 'INDISPONIVEL', 'base' => null];
    }
}

function flow_entrega_nas_caminho(string $base, string $ciclo, ?array $p00 = null): ?string
{
    if (!preg_match('/^Z:\\\\[12][0-9]{3}\\\\[^\\\\\/]+$/D', $base)) {
        return null;
    }
    if ($p00 !== null) {
        $relativo = str_replace('\\', '/', trim((string) ($p00['nas_path'] ?? '')));
        if ($relativo === '') {
            $relativo = '03.Models/Modelagem_Fachada/Toons/' . ($p00['versao_label'] ?? 'V1');
        }
        if (preg_match('/[\x00-\x1f:*?"<>|]/', $relativo) || $relativo[0] === '/'
            || array_intersect(explode('/', $relativo), ['', '.', '..'])) {
            return null;
        }
        return $base . '\\' . str_replace('/', '\\', $relativo);
    }
    if (!preg_match('/^(?:[PR][0-9]{2}|EF)$/D', $ciclo)) {
        return null;
    }
    return $base . '\\04.Finalizacao\\' . $ciclo;
}
