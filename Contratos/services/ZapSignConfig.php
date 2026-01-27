<?php

class ZapSignConfig
{
    public static function getApiUrl(): string
    {
        $val = getenv('ZAPSIGN_API_URL');
        if ($val === false || trim((string)$val) === '') {
            $env = self::loadEnv();
            $val = $env['ZAPSIGN_API_URL'] ?? '';
        }
        $val = trim((string)$val);
        if ($val !== '') {
            return rtrim($val, '/');
        }

        // Default: escolhe host conforme sandbox
        if (self::isSandbox()) {
            return 'https://sandbox.api.zapsign.com.br/api/v1';
        }
        return 'https://api.zapsign.com.br/api/v1';
    }

    public static function getToken(): string
    {
        $token = getenv('ZAPSIGN_API_TOKEN');
        if ($token !== false && $token !== '') {
            return $token;
        }
        $env = self::loadEnv();
        return $env['ZAPSIGN_API_TOKEN'] ?? '';
    }

    public static function getTemplateId(): string
    {
        $id = getenv('ZAPSIGN_TEMPLATE_ID');
        if ($id !== false && $id !== '') {
            return $id;
        }
        $env = self::loadEnv();
        return $env['ZAPSIGN_TEMPLATE_ID'] ?? '';
    }

    public static function isSandbox(): bool
    {
        $val = getenv('ZAPSIGN_SANDBOX');
        if ($val === false || $val === '') {
            $env = self::loadEnv();
            $val = $env['ZAPSIGN_SANDBOX'] ?? '';
        }
        $val = strtolower(trim((string)$val));
        return in_array($val, ['1', 'true', 'yes', 'on'], true);
    }

    private static function loadEnv(): array
    {
        $envPath = __DIR__ . '/../../.env';
        if (!is_file($envPath)) {
            return [];
        }
        $data = parse_ini_file($envPath, false, INI_SCANNER_RAW);
        return is_array($data) ? $data : [];
    }
}
