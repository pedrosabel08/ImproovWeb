<?php

declare(strict_types=1);

use FlowConnect\Infrastructure\DeliveryRepository;
use FlowConnect\Infrastructure\SlackIdentityRepository;

function fc_it_shadow_mode(FlowConnectIntegrationContext $ctx): void
{
    foreach (['mention', 'angle', 'task', 'direction', 'sftp', 'sla'] as $family) {
        $ctx->assert(flow_connect_review_mode($family) === 'shadow', "Família {$family} não está em shadow.");
    }
    $claimed = (new DeliveryRepository($ctx->conn, 30))->claimEligible(20, 'flow-connect-integration-shadow');
    $ctx->assert($claimed === [], 'Delivery shadow ficou elegível ao worker.');
    $ctx->assert((int) $ctx->conn->query("SELECT COUNT(*) total FROM flow_connect_delivery_attempts")->fetch_assoc()['total'] === 0, 'A bateria começou com attempts externas; abortar análise de ausência de envio.');

    $inactiveId = 990000000 + (int) getmypid();
    $identities = new SlackIdentityRepository($ctx->conn);
    $identities->upsert($inactiveId, 'UINACTIVE' . getmypid(), null, null, 'INACTIVE', 'flow_connect_integration_test');
    $ctx->assert($identities->findActiveByCollaborator($inactiveId) === null, 'Identidade INACTIVE não pode ser resolvida.');
    $ctx->assert($identities->findActiveByCollaborator($ctx->unresolvedCollaboratorId()) === null, 'Identidade UNRESOLVED não pode ser resolvida.');
    $roles = flow_connect_config()['flow_review']['roles'];
    $ctx->assert(!empty($roles['direction_group']) && !empty($roles['flow_review_managers']) && !empty($roles['technical_admins']), 'Fallback de destinatários não está configurado.');
}
