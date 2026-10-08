<?php

require_once __DIR__ . '/../pagamento_auth.php';
require_once __DIR__ . '/../../config/pagamento_fechamento.php';
require_once __DIR__ . '/../services/FechamentoInterfaceService.php';

final class FechamentoHttp
{
    public static function inteiro($value, bool $zero = false): int
    {
        if ((!is_int($value) && !(is_string($value) && preg_match('/^(0|[1-9][0-9]{0,15})$/D', $value)))
            || (float)$value > 9007199254740991 || (int)$value < ($zero ? 0 : 1)) {
            throw new InvalidArgumentException('Identificador/versão inválido.');
        }
        return (int)$value;
    }

    public static function campos(array $data, array $allowed, array $required): void
    {
        if (array_diff(array_keys($data), $allowed) || array_diff($required, array_keys($data))) {
            throw new InvalidArgumentException('Campos ausentes ou não permitidos.');
        }
    }

    public static function validar(string $action, array $data): array
    {
        if (in_array($action, ['mensal','iniciar'], true)) {
            self::campos($data, $action === 'iniciar' ? ['competencia','idempotency_key'] : ['competencia'], $action === 'iniciar' ? ['competencia','idempotency_key'] : ['competencia']);
            if (!is_string($data['competencia'])) {
                throw new InvalidArgumentException('Competência inválida.');
            }
            FechamentoFinanceiroRules::periodo($data['competencia']);
            if ($action === 'iniciar' && (!is_string($data['idempotency_key']) || !preg_match('/^[A-Za-z0-9_.:-]{1,80}$/D', $data['idempotency_key']))) {
                throw new InvalidArgumentException('Chave inválida.');
            }
            return $data;
        }
        $context = ['colaborador_id','competencia'];
        $specific = match($action) {
            'incluir' => ['idempotency_key'],
            'preparar' => ['expected_version','idempotency_key'],
            'obter' => ['revision_id'],
            'decidir' => ['expected_version','idempotency_key','tipo','input'],
            'gerar' => ['fechamento_id','revision_id','idempotency_key'],
            'visualizar' => ['document_id','idempotency_key'],
            'confirmar' => ['document_id','revision_id','pdf_hash','idempotency_key'],
            'recuperar' => ['operation_id','idempotency_key'],
            'revisoes', 'listar' => [],
            default => throw new InvalidArgumentException('Ação desconhecida.'),
        };
        self::campos($data, array_merge($context, $specific), array_merge($context, $action === 'obter' ? [] : $specific));
        $data['colaborador_id'] = self::inteiro($data['colaborador_id']);
        if (!is_string($data['competencia'])) {
            throw new InvalidArgumentException('Competência inválida.');
        }
        FechamentoFinanceiroRules::periodo($data['competencia']);
        foreach (['colaborador_id','fechamento_id','revision_id','document_id','operation_id','expected_version'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = self::inteiro($data[$field], $field === 'expected_version');
            }
        }
        if (isset($data['idempotency_key']) && (!is_string($data['idempotency_key'])
            || !preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $data['idempotency_key']))) {
            throw new InvalidArgumentException('Chave idempotente inválida.');
        }
        if ($action === 'confirmar' && (!is_string($data['pdf_hash']) || !preg_match('/^[a-f0-9]{64}$/D', $data['pdf_hash']))) {
            throw new InvalidArgumentException('Hash PDF inválido.');
        }
        if ($action === 'decidir') {
            if (!is_array($data['input']) || array_is_list($data['input'])) {
                throw new InvalidArgumentException('Decisão inválida.');
            }
            $input = $data['input'];
            $type = $data['tipo'];
            $allowed = match($type) {
                'FIXO' => ['estado','motivo','valor'],
                'DESCONTO' => ['estado','motivo','valor'],
                'SERVICOS' => ['estado','motivo','alvo','identidade','funcao_id'],
                'BONUS' => ['estado','motivo','itens'],
                'LIQUIDACAO' => ['estado','motivo','evidencias'],
                default => throw new InvalidArgumentException('Decisão não suportada.'),
            };
            self::campos($input, $allowed, ['estado','motivo']);
            if (!is_string($input['motivo']) || trim($input['motivo']) === '' || strlen($input['motivo']) > 1000) {
                throw new InvalidArgumentException('Informe o motivo da decisão.');
            }
            if (!is_string($input['estado'])) {
                throw new InvalidArgumentException('Estado inválido.');
            }
            if ($type === 'SERVICOS') {
                if (!in_array($input['estado'],['RETIRAR','RESTAURAR'],true) || !in_array($input['alvo']??null,['ITEM','FUNCAO'],true)) throw new InvalidArgumentException('Ação/alvo de serviços inválidos.');
                if ($input['alvo']==='ITEM') {
                    self::campos($input,['estado','motivo','alvo','identidade'],['estado','motivo','alvo','identidade']);
                    if (!is_array($input['identidade'])) throw new InvalidArgumentException('Identidade inválida.');
                    self::campos($input['identidade'],['beneficiario_id','origem','origem_id','classe'],['beneficiario_id','origem','origem_id','classe']);
                    $id=$input['identidade'];
                    if (!is_int($id['beneficiario_id']) || !is_int($id['origem_id']) || !is_string($id['origem']) || !is_string($id['classe'])) throw new InvalidArgumentException('Identidade inválida.');
                } else {
                    self::campos($input,['estado','motivo','alvo','funcao_id'],['estado','motivo','alvo','funcao_id']);
                    if (!is_int($input['funcao_id']) || $input['funcao_id']<=0) throw new InvalidArgumentException('Função inválida.');
                }
            } elseif ($type === 'DESCONTO') {
                self::campos($input,['estado','motivo','valor'],['estado','motivo','valor']);
                if ($input['estado']!=='DEFINIDO' || !is_string($input['valor'])) throw new InvalidArgumentException('Desconto inválido.');
            } elseif ($type === 'FIXO') {
                if (!in_array($input['estado'], ['CADASTRO','SEM_VALOR_FIXO','OVERRIDE'], true)) {
                    throw new InvalidArgumentException('Estado de fixo inválido.');
                }
                if (($input['estado'] === 'OVERRIDE') !== array_key_exists('valor', $input)) {
                    throw new InvalidArgumentException('Valor só é permitido no override.');
                }
            } else {
                $field = $type === 'BONUS' ? 'itens' : 'evidencias';
                if (!isset($input[$field]) || !is_array($input[$field]) || !array_is_list($input[$field]) || count($input[$field]) > 100) {
                    throw new InvalidArgumentException('Conjunto inválido.');
                }
                foreach ($input[$field] as $item) {
                    if (!is_array($item)) {
                        throw new InvalidArgumentException('Item inválido.');
                    }
                    $keys = $type === 'BONUS' ? ['categoria','referencia','valor'] : ['tipo','referencia','valor','origem_verificavel'];
                    self::campos($item, $keys, $keys);
                    foreach ($item as $v) {
                        if (!is_string($v) || strlen($v) > 1000) {
                            throw new InvalidArgumentException('Campo de item inválido.');
                        }
                    }
                }
            }
            if (isset($input['valor']) && !is_string($input['valor'])) {
                throw new InvalidArgumentException('Informe o valor como texto monetário.');
            }
        }
        return $data;
    }

    public static function erro(Throwable $e): array
    {
        $message = $e->getMessage();
        if (str_contains($message, 'STALE_VERSION')) {
            return [409,'STALE_VERSION','Este fechamento foi atualizado por outra ação. Recarregue os dados antes de continuar.'];
        }
        if (str_contains($message, 'autorização')) {
            return [403,'FORBIDDEN','Seu usuário não está autorizado a gerir este fechamento.'];
        }
        if (str_contains($message, 'outro fechamento')) {
            return [404,'SCOPE_MISMATCH','O documento ou revisão não pertence a este fechamento.'];
        }
        if (str_contains($message, 'idempotência')) {
            return [409,'IDEMPOTENCY_CONFLICT','Esta chave já foi usada em outra ação. Confira a operação anterior.'];
        }
        if (str_contains($message, 'Visualize este')) {
            return [409,'PREVIEW_NOT_VIEWED','Visualize este documento antes de confirmar.'];
        }
        if (str_contains($message, 'participar explicitamente')) {
            return [409,'PARTICIPACAO_NAO_HABILITADA','O cadastro precisa estar ativo e marcado como participante. Volte ao fechamento e atualize os cadastros.'];
        }
        if (str_contains($message, 'não correspondem ao visualizado')) {
            return [409,'DOCUMENT_MISMATCH','O adendo mudou. Atualize os valores e confira o documento novamente.'];
        }
        if (preg_match('/hash|integridade|corromp|selagem|PDF incompleto/i', $message)) {
            return [422,'PDF_HASH_MISMATCH','O adendo não passou na verificação de integridade. Atualize os valores para revisar novamente.'];
        }
        if (str_contains($message, 'Arquivo documental ausente')) {
            return [404,'NOT_FOUND','O arquivo deste adendo está indisponível. Solicite a conferência ao responsável.'];
        }
        if (preg_match('/Migration|Proteção|InnoDB|Storage|raiz pública|Configuração|Implantação/i', $message)) {
            return [503,'DEPLOYMENT_NOT_READY','O novo fechamento ainda não está disponível neste ambiente. Solicite a conferência da implantação.'];
        }
        if (str_contains($message, 'não encontrado')) {
            return [404,'NOT_FOUND','O registro solicitado não foi encontrado.'];
        }
        if ($e instanceof InvalidArgumentException) {
            return [422,'INVALID_PAYLOAD','Os dados da ação são inválidos. Confira os campos, valores e a competência.'];
        }
        if ($e instanceof DomainException) {
            if (preg_match('/^Fechamento conclu?do|^Colaborador fora do snapshot|^Pagamentos existentes divergem/',$message)) return [409,'OPERATION_BLOCKED',$message];
            return [409,'OPERATION_BLOCKED','A ação está bloqueada. Confira os itens com atenção e atualize os valores.'];
        }
        return [500,'OPERATION_FAILED','Não foi possível concluir a ação. Use Tentar novamente para continuar com segurança.'];
    }

    public static function executar(string $action, string $method): void
    {
        ini_set('display_errors', '0');
        $request = bin2hex(random_bytes(12));
        header('X-Request-Id: ' . $request);
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        $conn = null;
        $data = [];
        try {
            pagamento_require_gestor($method === 'POST');
            if (!pagamento_fechamento_enabled()) {
                pagamento_json(['success' => false,'code' => 'FEATURE_DISABLED','error' => 'O novo fluxo de fechamento está desativado.'], 404);
            }
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
                header('Allow: '.$method);
                pagamento_json(['success' => false,'code' => 'METHOD_NOT_ALLOWED','error' => 'Método não permitido.'], 405);
            }
            $usuario = pagamento_current_user_id();
            if (!$usuario) {
                pagamento_json(['success' => false,'code' => 'INVALID_SESSION','error' => 'Sessão inválida. Entre novamente.'], 401);
            }
            if ($method === 'POST') {
                if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
                    throw new InvalidArgumentException('Envie JSON.');
                }
                $raw = file_get_contents('php://input', false, null, 0, 65537);
                if (!is_string($raw) || strlen($raw) > 65536) {
                    throw new InvalidArgumentException('Payload excede limite.');
                }
                $decoded = json_decode($raw, false, 64, JSON_THROW_ON_ERROR);
                if (!$decoded instanceof stdClass) {
                    throw new InvalidArgumentException('Objeto JSON obrigatório.');
                }
                $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            } else {
                $data = $_GET;
            }
            $data = self::validar($action, $data);
            // Libera o lock de sessão: concorrência é resolvida pelo expected_version/journal.
            session_write_close();
            $conn = pagamento_fechamento_connection();
            $service = new FechamentoInterfaceService($conn, pagamento_fechamento_storage(), $usuario, true);
            $b = $data['colaborador_id'] ?? 0;
            $ref = $data['competencia'];
            $result = match($action) {
                'mensal' => $service->mensal($ref),
                'iniciar' => $service->mensal($ref, true, $data['idempotency_key']),
                'incluir' => $service->incluirParticipante($ref, $b, $data['idempotency_key']),
                'obter' => $service->obter($b, $ref, $data['revision_id'] ?? null),
                'revisoes' => $service->revisoes($b, $ref),
                'preparar' => $service->preparar($b, $ref, $data['expected_version'], $data['idempotency_key']),
                'decidir' => $service->decidir($b, $ref, $data['expected_version'], $data['idempotency_key'], $data['tipo'], $data['input']),
                'gerar' => $service->gerar($b, $ref, $data['fechamento_id'], $data['revision_id'], $data['idempotency_key']),
                'listar' => $service->listarDocumentos($b, $ref),
                'visualizar' => $service->visualizar($b, $ref, $data['document_id'], $data['idempotency_key']),
                'confirmar' => $service->confirmar($b, $ref, $data['document_id'], $data['revision_id'], $data['pdf_hash'], $data['idempotency_key']),
                'recuperar' => $service->recuperar($b, $ref, $data['operation_id'], $data['idempotency_key']),
            };
            if ($action === 'visualizar') {
                $meta = $result['metadata'];
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="preview-'.$meta['document_id'].'.pdf"');
                header('X-Documento-Id: '.$meta['document_id']);
                header('X-Revisao-Id: '.$meta['revision_id']);
                header('X-Pdf-Hash: '.$meta['pdf_hash']);
                header('X-Visualizado: 1');
                header('Content-Length: '.strlen($result['bytes']));
                echo $result['bytes'];
                return;
            }
            pagamento_json(['success' => true,'data' => $result,'request_id' => $request]);
        } catch (Throwable $e) {
            [$status,$code,$message] = $e instanceof JsonException ? [422,'INVALID_PAYLOAD','JSON inválido.'] : self::erro($e);
            $context = [];
            foreach (['colaborador_id','fechamento_id','revision_id','document_id','operation_id'] as $field) {
                if (isset($data[$field]) && (is_int($data[$field]) || (is_string($data[$field]) && preg_match('/^[0-9]{1,16}$/D', $data[$field])))) {
                    $context[$field] = $data[$field];
                }
            }
            if (isset($data['competencia']) && is_string($data['competencia']) && preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D', $data['competencia'])) {
                $context['competencia'] = $data['competencia'];
            }
            // Somente IDs/código; não registrar mensagem SQL, dinheiro, snapshot, CPF ou PDF.
            error_log('pagamento_fechamento '.json_encode(['request_id' => $request,'action' => $action,'code' => $code,'context' => $context,'exception' => get_class($e)]));
            pagamento_json(['success' => false,'code' => $code,'error' => $message,'request_id' => $request],$status);
        } finally {
            if ($conn instanceof mysqli) {
                $conn->close();
            }
        }
    }
}
