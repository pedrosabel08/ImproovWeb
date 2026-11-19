import sys
import unittest
from pathlib import Path
from unittest.mock import Mock, patch
from contextlib import contextmanager

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from deadline_client import CommandResult
from deadline_farm import FarmCollector, collect_workers, incident_observations, incident_opened_at


class FarmTests(unittest.TestCase):
    def test_task_failure_switch_filters_before_limit_without_changing_incidents(self):
        cursor = Mock()
        cursor.fetchall.return_value = []
        database = Mock()
        @contextmanager
        def transaction():
            yield cursor
        database.transaction = transaction
        collector = FarmCollector(database, Mock(), Mock())
        for value, expected in (("0", 0), ("1", 1)):
            with patch.dict("os.environ", {"FLOW_CONNECT_RENDER_FARM_MODE": "active", "FLOW_CONNECT_RENDER_FARM_TASK_FAILURE_ENABLED": value}):
                collector.publish_transitions()
            sql, params = cursor.execute.call_args.args
            self.assertIn("kind<>'task_failure'", sql)
            self.assertIn("LIMIT 4", sql)
            self.assertEqual((expected,), params)

    def test_batch_parser_preserves_real_types_and_marks_missing_records(self):
        client = Mock()
        client.run.side_effect = [
            CommandResult(True, "Node-1\nNode-2\nNODE-2"),
            CommandResult(True, """[Node-1]
SlaveName=Node-1
SlaveState=Rendering
CurrentJobId=0123456789abcdef01234567
CurrentTaskProgresses=21.8%,22,2%
CurrentTaskStatus=Frame 0 - Rendering pass 10 (elapsed: 2:18:03, left: 8:09:53)
Groups=farm_dedicated,farm_all
FreeMemory=4096
"""),
        ]
        health, workers = collect_workers(client)
        self.assertEqual(health, "partial")
        self.assertEqual(len(workers), 2)
        self.assertEqual(workers[0]["progress"], 22)
        self.assertEqual(workers[0]["elapsed"], "2:18:03")
        self.assertEqual(workers[0]["remaining"], "8:09:53")
        self.assertEqual(workers[0]["groups"], ["farm_dedicated", "farm_all"])
        self.assertEqual(workers[0]["memory_free"], 4096)
        self.assertTrue(workers[1]["unavailable"])
        self.assertEqual(client.run.call_count, 2)

    def test_offline_alert_applies_only_to_dedicated_workers_after_grace(self):
        workers = [
            {"name": "Node-Farm", "state": "offline", "groups": ["farm_dedicated"], "job_id": None, "failed": 0},
            {"name": "Node-PC", "state": "offline", "groups": [], "job_id": None, "failed": 0},
        ]
        desired, closing = incident_observations("ok", workers, [], {}, 0, {"farm_dedicated"}, set())
        self.assertEqual(desired, {("node-farm", "offline")})
        self.assertEqual(closing, set())
        self.assertIsNone(incident_opened_at("offline", 100, 219))
        self.assertEqual(incident_opened_at("offline", 100, 220), 220)

    def test_communication_failure_preserves_worker_incident_until_recovery(self):
        failure = ("node-farm", "stalled")
        active = {failure: {"last_seen_at": 1}}
        desired, closing = incident_observations("unavailable", [], [], active, 10, set(), set())
        self.assertEqual(desired, {("", "communication")})
        self.assertFalse(closing)
        workers = [{"name": "node-farm", "state": "idle", "groups": [], "job_id": None, "failed": 0}]
        desired, closing = incident_observations(
            "ok", workers, [], {**active, ("", "communication"): {"last_seen_at": 2}}, 20, set(), set()
        )
        self.assertFalse(desired)
        self.assertEqual(closing, {("", "communication"), failure})
