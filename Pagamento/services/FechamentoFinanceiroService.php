<?php

require_once __DIR__ . '/FechamentoFinanceiroRepository.php';

/** Motor paralelo: não chamado por endpoints/UI atuais. Não prepara documento. */
final class FechamentoFinanceiroService
{
    private FechamentoFinanceiroRepository $repository;
    private FechamentoFinanceiroRules $rules;

    public function __construct(FechamentoFinanceiroRepository $repository, ?FechamentoFinanceiroRules $rules = null)
    {
        $this->repository = $repository;
        $this->rules = $rules ?? new FechamentoFinanceiroRules();
    }

    public function calcular(int $colaboradorId, string $competencia): array
    {
        return $this->calcularComContexto($colaboradorId, $competencia)['financeiro_servicos'];
    }

    /** Leitores internos auditados (1B/shadow); nenhuma entrada financeira da UI. */
    public function calcularComContexto(int $colaboradorId, string $competencia, ?callable $leituraAdicional = null): array
    {
        $leitura = $this->repository->carregar($colaboradorId, $competencia, $leituraAdicional);
        // Transação encerrada antes do cálculo em memória.
        $resultado = $this->rules->calcular($leitura['dados'], $colaboradorId, $competencia, $leitura['snapshot']);
        $resultado['consistencia'] = $leitura['consistencia'];
        return ['financeiro_servicos' => $resultado, 'contexto' => $leitura['adicional']];
    }
}
