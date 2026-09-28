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

        $tax = trim((string) ($entry['imposto'] ?? ''));
        if ($tax === '') {
            throw new InvalidArgumentException('Informe o percentual de imposto de cada imagem; use 0,00 quando não houver imposto.');
        }
        custos_decimal($tax);
        if ((float) $tax > 100) {
            throw new InvalidArgumentException('O percentual de imposto deve estar entre 0 e 100.');
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
        $taxPercent = custos_decimal($entry['imposto'] ?? '0');
        $taxAmount = number_format(((float) $gross * (float) $taxPercent) / 100, 2, '.', '');
        $values = custos_comercial_validar($conn, $obraId, [
            'categoria' => 'imagem',
            'imagem_id' => (int) ($inserted['imagem_id'] ?? 0),
            'valor' => $gross,
            'imposto' => $taxPercent,
            'valor_imposto' => $taxAmount,
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
