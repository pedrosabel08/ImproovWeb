<?php

function improov_management_kpi_allowed_user_ids(): array
{
    return [1, 9, 21];
}

function improov_kpi_permissions_for_user(?int $userId): array
{
    $normalizedUserId = (int) ($userId ?? 0);

    return [
        'management' => in_array($normalizedUserId, improov_management_kpi_allowed_user_ids(), true),
    ];
}

function improov_can_view_kpi_scope(string $scope, ?int $userId): bool
{
    $normalizedScope = trim($scope);

    if ($normalizedScope === '' || $normalizedScope === 'public') {
        return true;
    }

    $permissions = improov_kpi_permissions_for_user($userId);

    return !empty($permissions[$normalizedScope]);
}