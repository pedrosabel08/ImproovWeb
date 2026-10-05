-- DESTRUTIVO: somente banco descartável, ou arquivamento e autorização explícita.
-- Rollback operacional preferível: desabilitar consumidor, manter o histórico.
DROP TABLE pagamento_fechamento_operacao;
DROP TABLE pagamento_fechamento_revisao;
DROP TABLE pagamento_fechamento_evidencia;
DROP TABLE pagamento_fechamento_extra;
DROP TABLE pagamento_fechamento_decisao;
DROP TABLE pagamento_fechamento;
