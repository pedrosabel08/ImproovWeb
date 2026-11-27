"""Regressão dos ciclos da consulta de métricas, sem alterar dados do projeto."""
import pathlib
import re
import sqlite3
import unittest
from datetime import datetime


class PosMetricasTest(unittest.TestCase):
    def test_ciclos_e_revisoes(self):
        source = (pathlib.Path(__file__).resolve().parents[1] / 'Pos-Producao/metricas.php').read_text(encoding='utf-8')
        query = re.search(r'\$sql = "(SELECT s\.nome_status AS revisao, envio\.data AS envio,.*?envio\.data <= \?)";', source, re.S).group(1)
        query = query.replace('TIMESTAMPDIFF(SECOND, inicio.data, envio.data)', 'seconds_between(inicio.data, envio.data)')
        query = query.replace('anterior.status_anterior <=> anterior.status_novo', 'anterior.status_anterior IS anterior.status_novo')
        query = query.replace('envio.status_anterior <=> envio.status_novo', 'envio.status_anterior IS envio.status_novo')
        db = sqlite3.connect(':memory:')
        db.create_function('seconds_between', 2, lambda a, b: (datetime.fromisoformat(b) - datetime.fromisoformat(a)).total_seconds())
        db.executescript('''
            CREATE TABLE funcao_imagem (idfuncao_imagem INTEGER, imagem_id INTEGER, funcao_id INTEGER);
            CREATE TABLE log_alteracoes (idlog INTEGER, funcao_imagem_id INTEGER, status_anterior TEXT, status_novo TEXT, data TEXT);
            CREATE TABLE historico_imagens (idhistorico INTEGER, imagem_id INTEGER, status_id INTEGER, data_movimento TEXT);
            CREATE TABLE status_imagem (idstatus INTEGER, nome_status TEXT);
            INSERT INTO funcao_imagem VALUES (1, 1, 5), (2, 2, 5);
            INSERT INTO status_imagem VALUES (1, 'R00'), (2, 'R01');
            INSERT INTO historico_imagens VALUES (1, 1, 1, '2026-11-12 09:00:00'), (2, 1, 2, '2026-11-14 13:00:00');
            INSERT INTO log_alteracoes VALUES
                (1, 1, 'Não iniciado', 'Em andamento', '2026-11-12 10:00:00'),
                (2, 1, 'Em andamento', 'Em aprovação', '2026-11-12 11:00:00'),
                (3, 1, 'Ajuste', 'Em andamento', '2026-11-14 13:00:00'),
                (4, 1, 'HOLD', 'Em andamento', '2026-11-14 14:00:00'),
                (5, 1, 'Em andamento', 'Em aprovação', '2026-11-14 14:30:00'),
                (6, 1, 'Ajuste', 'Em aprovação', '2026-11-14 15:00:00'),
                (7, 2, 'Não iniciado', 'Em andamento', '2026-11-15 10:00:00'),
                (8, 2, 'Em andamento', 'Em aprovação', '2026-11-15 10:10:00');
        ''')
        self.assertEqual(db.execute(query, ('2026-09-29 00:00:00', '2026-11-28 00:00:00')).fetchall(), [('R00', '2026-11-12 11:00:00', 3600.0), ('R01', '2026-11-14 14:30:00', 1800.0), (None, '2026-11-15 10:10:00', 600.0)])


if __name__ == '__main__':
    unittest.main()
