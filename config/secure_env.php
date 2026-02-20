<?php

if (!function_exists('improov_load_env_once')) {
    function improov_load_env_once($envPath = null)
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        if ($envPath === null) {
            $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        }

        if (!is_file($envPath) || !is_readable($envPath)) {
            $loaded = true;
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            $loaded = true;
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            if ($key === '') {
                continue;
            }

            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') || (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
            if (!array_key_exists($key, $_SERVER)) {
                $_SERVER[$key] = $value;
            }
        }

        $loaded = true;
    }
}

if (!function_exists('improov_env')) {
    function improov_env($key, $default = null)
    {
        improov_load_env_once();

        $val = getenv($key);
        if ($val === false || $val === null || $val === '') {
            if ($default !== null) {
                return $default;
            }
            throw new RuntimeException('Variável de ambiente obrigatória ausente: ' . $key);
        }

        return $val;
    }
}

if (!function_exists('improov_sftp_config')) {
    function improov_sftp_config($prefix = 'IMPROOV_SFTP')
    {
        improov_load_env_once();
        $host = getenv($prefix . '_HOST');
        if ($host === false || $host === '') {
            $host = getenv('NAS_IP') ?: (getenv('NAS_HOST') ?: '');
        }
        if ($host === '') {
            throw new RuntimeException('Variável de ambiente obrigatória ausente: ' . $prefix . '_HOST');
        }

        return [
            'host' => $host,
            'port' => (int) improov_env($prefix . '_PORT', '2222'),
            'user' => improov_env($prefix . '_USER'),
            'pass' => improov_env($prefix . '_PASS'),
        ];
    }
}

if (!function_exists('improov_ftp_config')) {
    function improov_ftp_config($prefix = 'IMPROOV_FTP')
    {
        improov_load_env_once();

        $read = static function ($key) {
            $value = getenv($key);
            if ($value === false) {
                return '';
            }
            return trim((string)$value);
        };

        $host = getenv($prefix . '_HOST');
        if ($host === false) {
            $host = '';
        }
        $port = (int) improov_env($prefix . '_PORT', '21');
        $user = getenv($prefix . '_USER');
        if ($user === false) {
            $user = '';
        }
        $pass = getenv($prefix . '_PASS');
        if ($pass === false) {
            $pass = '';
        }
        $base = improov_env($prefix . '_BASE', '');

        $host = trim((string)$host);
        $user = trim((string)$user);
        $pass = trim((string)$pass);
        $base = trim((string)$base);

        if ($host === '') {
            $host = $read('IMPROOV_SFTP_HOST');
        }
        if ($user === '') {
            $user = $read('IMPROOV_SFTP_USER');
        }
        if ($pass === '') {
            $pass = $read('IMPROOV_SFTP_PASS');
        }
        if ($base === '') {
            $base = $read('IMPROOV_SFTP_REMOTE_PATH');
            if ($base === '') {
                $base = $read('IMPROOV_VPS_SFTP_REMOTE_PATH');
            }
        }

        return [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'pass' => $pass,
            'base' => $base,
        ];
    }
}
