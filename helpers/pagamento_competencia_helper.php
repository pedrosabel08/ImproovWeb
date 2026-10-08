<?php
require_once __DIR__.'/../Entregas/prazo_entrega_helper.php';

function pagamento_competencia_inicio(): string
{
    $ref = getenv('PAGAMENTO_COMPETENCIA_INICIO') ?: '2026-09';
    if (!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D', $ref)) throw new RuntimeException('Competência inicial inválida.');
    return $ref;
}

function pagamento_competencia_nova(string $ref): bool
{
    if (!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D', $ref)) throw new InvalidArgumentException('Competência inválida.');
    return $ref >= pagamento_competencia_inicio();
}

/** O calendário de Entregas é a única fonte de fins de semana e feriados. */
function pagamento_previsao(string $ref): string
{
    pagamento_competencia_nova($ref);
    $ultimo = (new DateTimeImmutable($ref.'-01', new DateTimeZone('America/Sao_Paulo')))->modify('last day of this month');
    return entregas_adicionar_dias_uteis($ultimo->format('Y-m-d'), 5, true);
}

function pagamento_competencia_nome(string $ref): string
{
    $meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    pagamento_competencia_nova($ref);
    return $meses[(int)substr($ref,5,2)-1].'/'.substr($ref,0,4);
}

function pagamento_bloquear_legado(string $ref): void
{
    if (pagamento_competencia_nova($ref)) throw new DomainException('Esta competência usa fechamento mensal. Registre o pagamento sobre o fechamento concluído.');
}
