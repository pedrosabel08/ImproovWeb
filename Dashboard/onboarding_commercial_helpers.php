<?php

require_once __DIR__ . '/../Custos/comercial_helper.php';

function dashboard_onboarding_validate_commercial_images(array $entries): void
{
    foreach ($entries as $index => $entry) {
        if (!is_array($entry)) {
            throw new InvalidArgumentException('Imagem ' . ($index + 1) . ': dados comerciais ausentes.');
        }

        $value = trim((string) ($entry['valor'] ?? ''));
        if ($value === '') {
            throw new InvalidArgumentException('Informe o valor vendido para cada imagem.');
        }
        custos_decimal($value);

        $tax = trim((string) ($entry['valor_imposto'] ?? ''));
        if ($tax === '') {
            throw new InvalidArgumentException('Informe o imposto de cada imagem; use 0,00 quando não houver imposto.');
        }
        custos_decimal($tax);
        if (custos_centavos($tax) > custos_centavos($value)) {
            throw new InvalidArgumentException('O imposto não pode ultrapassar o valor bruto da imagem.');
        }

        $contract = trim((string) ($entry['numero_contrato'] ?? ''));
        if (mb_strlen($contract) > 255) {
            throw new InvalidArgumentException('O texto do contrato deve ter até 255 caracteres.');
        }
    }
}

function dashboard_onboarding_save_image_commercial(mysqli $conn, int $obraId, array $insertedImages): int
{
    $saved = 0;
    foreach ($insertedImages as $inserted) {
        $entry = is_array($inserted['entry'] ?? null) ? $inserted['entry'] : [];
        $gross = custos_decimal($entry['valor'] ?? '');
        $tax = custos_decimal($entry['valor_imposto'] ?? '0');
        $grossCents = custos_centavos($gross);
        $taxCents = custos_centavos($tax);
        $taxPercent = $grossCents > 0
            ? number_format(($taxCents / $grossCents) * 100, 2, '.', '')
            : '0.00';
        $values = custos_comercial_validar($conn, $obraId, [
            'categoria' => 'imagem',
            'imagem_id' => (int) ($inserted['imagem_id'] ?? 0),
            'valor' => $gross,
            'imposto' => $taxPercent,
            'valor_imposto' => $tax,
            'numero_contrato' => $entry['numero_contrato'] ?? '',
        ]);
        custos_comercial_salvar($conn, $obraId, $values);
        $saved++;
    }

    return $saved;
}

function dashboard_onboarding_save_photo_service(mysqli $conn, int $obraId, string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }

    $values = custos_comercial_validar($conn, $obraId, [
        'categoria' => 'foto',
        'valor' => $value,
    ]);
    custos_comercial_salvar($conn, $obraId, $values);
    return true;
}
