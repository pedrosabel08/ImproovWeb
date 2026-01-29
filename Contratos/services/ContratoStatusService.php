<?php

class ContratoStatusService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function atualizarStatusPorToken(string $docToken, string $status, ?string $assinadoEm = null): void
    {
        $sql = "UPDATE contratos SET status = ?, assinado_em = ? WHERE zapsign_doc_token = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Falha ao preparar status: ' . $this->conn->error);
        }
        $stmt->bind_param('sss', $status, $assinadoEm, $docToken);
        $stmt->execute();
        $stmt->close();

        if ($status === 'assinado') {
            $this->liberarAcessoPorToken($docToken);
        } else {
            $this->bloquearAcessoPorToken($docToken);
        }
    }

    public function atualizarStatusPorArquivoNome(string $arquivoNome, string $status, ?string $assinadoEm = null, ?string $docToken = null): void
    {
        if ($docToken) {
            $sql = "UPDATE contratos SET status = ?, assinado_em = ?, zapsign_doc_token = ? WHERE arquivo_nome = ?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Falha ao preparar status: ' . $this->conn->error);
            }
            $stmt->bind_param('ssss', $status, $assinadoEm, $docToken, $arquivoNome);
            $stmt->execute();
            $stmt->close();

            if ($status === 'assinado') {
                $this->liberarAcessoPorToken($docToken);
            } else {
                $this->bloquearAcessoPorToken($docToken);
            }
            return;
        }

        $sql = "UPDATE contratos SET status = ?, assinado_em = ? WHERE arquivo_nome = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Falha ao preparar status: ' . $this->conn->error);
        }
        $stmt->bind_param('sss', $status, $assinadoEm, $arquivoNome);
        $stmt->execute();
        $stmt->close();
    }

    private function liberarAcessoPorToken(string $docToken): void
    {
        $sql = "UPDATE colaborador c 
            JOIN contratos ct ON ct.colaborador_id = c.idcolaborador
            SET c.ativo = 1
            WHERE ct.zapsign_doc_token = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('s', $docToken);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function bloquearAcessoPorToken(string $docToken): void
    {
        $sql = "UPDATE colaborador c 
            JOIN contratos ct ON ct.colaborador_id = c.idcolaborador
            SET c.ativo = 0
            WHERE ct.zapsign_doc_token = ? AND ct.status <> 'assinado'";
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('s', $docToken);
            $stmt->execute();
            $stmt->close();
        }
    }
}
