<?php

use FlowConnect\Application\PendingSummary\AbstractPendingSummaryProvider;
use FlowConnect\Application\PendingSummary\PendingSummaryFactory;
use FlowConnect\Application\PendingSummary\PendingSummaryCollector;
use FlowConnect\Application\RecipientResolver;
use FlowConnect\Application\TemplateRenderer;
use FlowConnect\Contracts\PendingSummaryProviderInterface;

function flow_connect_test_pending_summary(): void
{
    $previousSpecific = getenv('FLOW_CONNECT_PENDING_SUMMARY_MODE');
    $previousGeneral = getenv('FLOW_CONNECT_MODE');
    try {
        putenv('FLOW_CONNECT_PENDING_SUMMARY_MODE');
        putenv('FLOW_CONNECT_MODE=shadow');
        fc_assert_same('shadow', flow_connect_pending_summary_mode(), 'general mode is fallback');
        putenv('FLOW_CONNECT_PENDING_SUMMARY_MODE=active');
        fc_assert_same('active', flow_connect_pending_summary_mode(), 'specific mode overrides general mode');
        putenv('FLOW_CONNECT_PENDING_SUMMARY_MODE=off');
        fc_assert_same('off', flow_connect_pending_summary_mode(), 'specific off disables summary');
    } finally {
        putenv($previousSpecific === false ? 'FLOW_CONNECT_PENDING_SUMMARY_MODE' : 'FLOW_CONNECT_PENDING_SUMMARY_MODE=' . $previousSpecific);
        putenv($previousGeneral === false ? 'FLOW_CONNECT_MODE' : 'FLOW_CONNECT_MODE=' . $previousGeneral);
    }
    $config = ['operational' => ['pending_summary' => ['mode' => 'shadow', 'preview_limit_per_module' => 3, 'origin_url' => 'https://example.test/ImproovWeb/', 'normal_max' => 5, 'attention_max' => 10, 'include_managers' => true, 'manager_collaborator_ids' => [7, 21]]]];
    $provider = new class($config) extends AbstractPendingSummaryProvider {
        public function __construct(array $config) { parent::__construct($config, 'arquivo', 'Arquivos'); }
        public function collect(mysqli $conn): array { return []; }
        public function normalize(array $rows): array { return $this->summarize($rows); }
    };
    fc_assert_same([], $provider->normalize([]), 'empty provider produces no summaries');
    $rows = [];
    for ($i = 1; $i <= 101; $i++) $rows[] = ['entity_type' => 'funcao_imagem', 'entity_id' => (string) $i, 'collaborator_id' => 21, 'title' => 'Arquivo ' . $i, 'created_at' => '2026-08-01 08:00:00', 'due_at' => '2026-08-02 08:00:00', 'priority' => 'NORMAL'];
    $rows[] = $rows[0]; // duplicate same funcao_imagem must not count twice
    $summary = $provider->normalize($rows)[0];
    fc_assert_same(101, $summary['total'], 'more than one hundred and duplicate item are consolidated correctly');
    fc_assert_same(3, count($summary['preview_items']), 'preview is limited per module');
    $multi = $provider->normalize([
        ['entity_type' => 'checklist', 'entity_id' => 'a', 'collaborator_id' => 1, 'title' => 'Operacional', 'created_at' => null, 'due_at' => null, 'priority' => 'NORMAL'],
        ['entity_type' => 'checklist', 'entity_id' => 'b', 'collaborator_id' => 2, 'title' => 'Operacional', 'created_at' => null, 'due_at' => null, 'priority' => 'NORMAL'],
    ]);
    fc_assert_same([1, 2], array_column($multi, 'collaborator_id'), 'multiple collaborators remain separated');

    $throwing = new class implements PendingSummaryProviderInterface {
        public function collect(mysqli $conn): array { throw new RuntimeException('provider failure'); }
    };
    $empty = new class implements PendingSummaryProviderInterface {
        public function collect(mysqli $conn): array { return []; }
    };
    $collection = (new PendingSummaryCollector([$throwing, $empty]))->collect(mysqli_init());
    fc_assert_same(1, count($collection['providers_failed']), 'provider error does not block the remaining providers');
    fc_assert_same(1, count($collection['providers_success']), 'empty provider is a valid provider result');

    fc_assert_same('NORMAL', PendingSummaryFactory::priority(1, $config), 'normal priority');
    fc_assert_same('ATTENTION', PendingSummaryFactory::priority(6, $config), 'attention priority');
    fc_assert_same('CRITICAL', PendingSummaryFactory::priority(11, $config), 'critical priority');
    fc_assert_same('NONE', PendingSummaryFactory::priority(0, $config), 'zero pending is not sendable');

    $event = PendingSummaryFactory::event(21, '2026-08-05T10:15', [
        ['module_key' => 'arquivo', 'module_name' => 'Arquivos', 'total' => 5, 'oldest_created_at' => null, 'oldest_due_at' => null, 'highest_priority' => 'NORMAL', 'preview_items' => [], 'origin_url' => ''],
        ['module_key' => 'flow_review', 'module_name' => 'Flow Review', 'total' => 3, 'oldest_created_at' => null, 'oldest_due_at' => null, 'highest_priority' => 'NORMAL', 'preview_items' => [], 'origin_url' => ''],
    ], $config, ['UploadPendingProvider'], []);
    fc_assert_same('pending-summary:2026-08-05:10:15:21:v1', $event['idempotency_key'], 'window idempotency key');
    fc_assert_same('shadow', $event['metadata']['flow_connect_mode'], 'summary event preserves explicit shadow mode');
    fc_assert_same(['flow_review', 'arquivo'], array_column($event['payload']['modules'], 'module_key'), 'fixed module order');
    $definition = require dirname(__DIR__) . '/config/events/pending_summary.php';
    fc_assert_same('SUMMARY', $definition['pending.summary.ready']['category'], 'summary event category');
    fc_assert_same('INFO', $definition['pending.summary.ready']['severity'], 'summary event severity');
    fc_assert_same('pending_summary', $definition['pending.summary.ready']['template'], 'summary event template catalog');
    fc_assert_same('summary_owner', $definition['pending.summary.ready']['recipient_strategy'], 'summary owner strategy catalog');

    $recipients = (new RecipientResolver(['operational' => ['pending_summary' => $config['operational']['pending_summary']]]))->resolveForEvent('summary_owner', ['event_type' => 'pending.summary.ready', 'payload' => ['collaborator_id' => 21]]);
    fc_assert_same([21], array_column($recipients, 'collaborator_id'), 'summary creates one owner delivery');
    $text = (new TemplateRenderer())->render('pending_summary', ['payload' => $event['payload']])['text'];
    fc_assert(strpos($text, 'Flow Review') !== false && strpos($text, 'Arquivos') !== false && strpos($text, 'Total: 8') !== false, 'summary template is concise and renders modules');
    fc_assert(strpos($text, 'Abrir Pendências') !== false, 'summary template renders CTA');

    $invalid = $event;
    $invalid['payload']['collaborator_id'] = 0;
    try {
        \FlowConnect\Contracts\EventValidator::validate($invalid);
        fc_assert(false, 'summary without collaborator is rejected');
    } catch (InvalidArgumentException) {
        fc_assert(true, 'summary without collaborator is rejected');
    }
    $emptyModules = $event;
    $emptyModules['payload']['modules'] = [];
    $emptyModules['payload']['total_pending'] = 0;
    $emptyModules['payload']['total_modules'] = 0;
    \FlowConnect\Contracts\EventValidator::validate($emptyModules);
    fc_assert(true, 'summary with empty modules has valid schema');
}
