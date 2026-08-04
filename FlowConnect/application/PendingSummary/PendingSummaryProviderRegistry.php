<?php
declare(strict_types=1);
namespace FlowConnect\Application\PendingSummary;
use FlowConnect\Application\PendingSummary\Providers\{FlowReviewPendingProvider,RenderPendingProvider,ProjetoPendingProvider,ImagemPendingProvider,PreAlteracaoPendingProvider,FotograficoPendingProvider,FlowBlockPendingProvider,CobrancaClientePendingProvider,LinksPendingProvider,UploadPendingProvider};
final class PendingSummaryProviderRegistry {
    /** @return list<\FlowConnect\Contracts\PendingSummaryProviderInterface> */
    public static function registered(array $config): array { return [new FlowReviewPendingProvider($config),new RenderPendingProvider($config),new ProjetoPendingProvider($config),new ImagemPendingProvider($config),new PreAlteracaoPendingProvider($config),new FotograficoPendingProvider($config),new FlowBlockPendingProvider($config),new CobrancaClientePendingProvider($config),new LinksPendingProvider($config),new UploadPendingProvider($config)]; }
}
