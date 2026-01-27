<?php

class ZapSignClient
{
    private string $apiUrl;
    private string $apiToken;

    public function __construct(string $apiToken, string $apiUrl = 'https://api.zapsign.com.br/api/v1')
    {
        $this->apiToken = $apiToken;
        $this->apiUrl = rtrim($apiUrl, '/');
    }

    /**
     * Cria um documento a partir de um modelo (template) do ZapSign.
     * Endpoint esperado: /models/create-doc/
     *
     * $fields deve mapear placeholders do template (ex: CONTRATADA_QUALIFICACAO_COMPLETA)
     * para valores. O client converte para o formato {de, para}.
     */
    public function createDocumentFromTemplate(string $templateId, string $signerName, string $signerEmail, array $fields, bool $sandbox = false): array
    {
        $url = $this->apiUrl . '/models/create-doc/';

        $dataPairs = [];
        foreach ($fields as $key => $value) {
            $k = trim((string)$key);
            if ($k === '') continue;
            $dataPairs[] = [
                'de' => '{{' . $k . '}}',
                'para' => (string)$value,
            ];
        }

        $payload = [
            'template_id' => $templateId,
            'signer_name' => $signerName,
            'signer_email' => $signerEmail,
            'send_automatic_email' => true,
            'data' => $dataPairs,
        ];

        // Em contas sem plano API em produção, a ZapSign pede usar sandbox=true para testes.
        if ($sandbox) {
            $payload['sandbox'] = true;
        }

        return $this->request('POST', $url, $payload);
    }

    private function request(string $method, string $url, array $payload = []): array
    {
        $ch = curl_init($url);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $auth = $this->apiToken;
        if (stripos($auth, 'Bearer ') !== 0) {
            $auth = 'Bearer ' . $auth;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: ' . $auth,
            ],
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_TIMEOUT => 30,
        ]);

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException('Falha ZapSign: ' . $err);
        }

        $data = json_decode($resp, true);
        if (!is_array($data)) {
            throw new RuntimeException('Resposta inválida ZapSign (HTTP ' . $code . '): ' . $resp);
        }

        if ($code >= 400) {
            $msg = $data['message'] ?? $data['detail'] ?? 'Erro API ZapSign';
            throw new RuntimeException('ZapSign HTTP ' . $code . ': ' . $msg);
        }

        return $data;
    }
}
