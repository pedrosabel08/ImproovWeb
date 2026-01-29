<?php

class ContratoDataService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function getColaboradorContratoData(int $colaboradorId): array
    {
        $sql = "SELECT 
                c.idcolaborador,
                c.telefone,
                u.idusuario,
                u.email,
                u.nome_usuario as nome_colaborador,
                iu.cnpj,
                iu.nome_empresarial,
                iu.estado_civil,
                iu.cpf,
                e.rua, e.bairro, e.numero, e.complemento, e.cep, e.localidade, e.uf,
                ec.rua_cnpj, ec.numero_cnpj, ec.bairro_cnpj, ec.localidade_cnpj, ec.uf_cnpj, ec.cep_cnpj
            FROM colaborador c
            LEFT JOIN usuario u ON u.idcolaborador = c.idcolaborador
            LEFT JOIN informacoes_usuario iu ON iu.usuario_id = u.idusuario
            LEFT JOIN endereco e ON e.usuario_id = u.idusuario
            LEFT JOIN endereco_cnpj ec ON ec.usuario_id = u.idusuario
            WHERE c.idcolaborador = ? AND c.ativo = 1
            LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Falha ao preparar colaborador: ' . $this->conn->error);
        }

        $stmt->bind_param('i', $colaboradorId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            throw new RuntimeException('Colaborador não encontrado ou inativo.');
        }

        return $row;
    }

    public function getColaboradorFuncoes(int $colaboradorId): array
    {
        $sql = "SELECT DISTINCT f.idfuncao, f.nome_funcao, fc.nivel_finalizacao
            FROM funcao_colaborador fc
            JOIN funcao f ON f.idfuncao = fc.funcao_id
            WHERE fc.colaborador_id = ?
            ORDER BY f.idfuncao";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Falha ao preparar funções: ' . $this->conn->error);
        }

        $stmt->bind_param('i', $colaboradorId);
        $stmt->execute();
        $res = $stmt->get_result();
        $funcoes = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $funcoes[] = $r;
            }
        }
        $stmt->close();

        return $funcoes;
    }
}
