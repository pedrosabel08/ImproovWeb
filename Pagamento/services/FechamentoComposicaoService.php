<?php

require_once __DIR__ . '/FechamentoFinanceiroService.php';
require_once __DIR__ . '/FechamentoComposicaoRepository.php';
require_once __DIR__ . '/FechamentoComposicaoRules.php';

/** Paralelo: não prepara documento nem altera qualquer cadastro/decisão. */
final class FechamentoComposicaoService
{
    public function __construct(private FechamentoFinanceiroService $servicos,
        private FechamentoComposicaoRepository $repository,
        private ?FechamentoComposicaoRules $rules = null) {}

    public function calcular(int $colaboradorId, string $competencia): array
    {
        return $this->calcularComContexto($colaboradorId, $competencia)['composicao'];
    }

    public function calcularComContexto(int $colaboradorId, string $competencia): array
    {
        $leitura = $this->servicos->calcularComContexto($colaboradorId, $competencia,
            fn(mysqli $conn, array $dados, DateTimeImmutable $snapshot) => $this->repository->carregarComplementos($conn, $colaboradorId, $competencia, $snapshot));
        $r = ($this->rules ?? new FechamentoComposicaoRules())->compor($leitura['financeiro_servicos'], $leitura['contexto']);
        $r['persistencia'] = $leitura['contexto']['persistencia'];
        return ['composicao' => $r, 'contexto' => $leitura['contexto']];
    }
}
