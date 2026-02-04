<?php

class AdendoStatusService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function atualizarStatusPorToken(string $docToken, string $status, ?string $assinadoEm = null): void
    {
        $sql = "UPDATE adendos SET status = ?, assinado_em = ? WHERE zapsign_doc_token = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Falha ao preparar status: ' . $this->conn->error);
        }
        $stmt->bind_param('sss', $status, $assinadoEm, $docToken);
        $stmt->execute();
        $stmt->close();
    }

    public function atualizarStatusPorArquivoNome(string $arquivoNome, string $status, ?string $assinadoEm = null, ?string $docToken = null): void
    {
        if ($docToken) {
            $sql = "UPDATE adendos SET status = ?, assinado_em = ?, zapsign_doc_token = ? WHERE arquivo_nome = ?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Falha ao preparar status: ' . $this->conn->error);
            }
            $stmt->bind_param('ssss', $status, $assinadoEm, $docToken, $arquivoNome);
            $stmt->execute();
            $stmt->close();
            return;
        }

        $sql = "UPDATE adendos SET status = ?, assinado_em = ? WHERE arquivo_nome = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Falha ao preparar status: ' . $this->conn->error);
        }
        $stmt->bind_param('sss', $status, $assinadoEm, $arquivoNome);
        $stmt->execute();
        $stmt->close();
    }
}
