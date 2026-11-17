from __future__ import annotations

import json
import importlib.util
import logging
import os
import re
import subprocess
import sys
import unittest
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timedelta
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import Mock, patch
from uuid import uuid4

RENDER = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(RENDER))

from deadline_diagnosis import classify, extract
from deadline_diagnostic_collector import DiagnosticCollector, DiagnosticSettings
from deadline_diagnostic_store import read, window_count

JOB_A, JOB_B = "a" * 24, "b" * 24
CRASH = 'RenderPluginException: Monitored managed process "3dsmaxProcess" has exited or been terminated.'
REFERENCE = (RENDER / "tests/fixtures/deadline_imp_frm_004.txt").read_text(encoding="utf-8")
ASSET = "System.IO.FileNotFoundException: Could not find the file."
LICENSE = "License checkout failed."
CRITICAL = "License server unreachable."
NETWORK = r"The network path was not found: \\nas\project\scene.max"


def make_report(body, number=1, job=JOB_A, worker="IMP-FRM-004", stamp=None):
    return {
        "job_id": job, "job_name": "ANIMA ABF_ALM_Tracking_0053", "task_id": "0", "frame": "0-2",
        "worker": worker, "occurred_at": stamp.isoformat(sep=" ") if stamp else None,
        "report_id": f"{number:024x}", "body": body, "message": body.splitlines()[0] if body else "",
        "metadata_hash": f"{number:064x}", "truncated": False, "save_error": False,
        "plugin": "3dsmax", "peak_ram": None, "capacity": {"physical_bytes": 64 * 1024**3},
    }


class RuleTests(unittest.TestCase):
    def result(self, body):
        report = make_report(body)
        return classify(extract(body, report), report)

    def test_reference(self):
        diagnosis = self.result(REFERENCE)
        self.assertEqual(("memory", "provável"), (diagnosis["category"], diagnosis["confidence"]))
        self.assertIn("RAM reportada em 99%.", diagnosis["evidence"])
        self.assertEqual(diagnosis["alternatives"][0]["rule"], "max_process_exit")
        self.assertEqual(diagnosis["alternatives"][0]["role"], "symptom")
        self.assertEqual([], diagnosis["factors"])

    def test_crash_alone_and_unknown(self):
        self.assertEqual(self.result(CRASH)["confidence"], "indeterminado")
        self.assertEqual(self.result(CRASH + "\nRAM usage: 99%")["category"], "max_exit")
        self.assertEqual(self.result("RenderPluginException: generic")["category"], "unknown")
        self.assertNotEqual(self.result("RenderPluginException: invalid material channel")["signature"],
                            self.result("RenderPluginException: invalid codec setting")["signature"])

    def test_initial_categories(self):
        for report, category in (
            (LICENSE, "license"), (ASSET, "missing_asset"), (NETWORK, "network"),
            ("Failed to load plugin RailClone", "plugin"), ("Task timed out.", "timeout"),
            ("Corona Renderer internal error", "corona"), ("Unable to launch 3dsmax.exe", "max_initialization"),
            ("Worker IMP-FRM-004 has stalled", "worker_stalled"), ("Disk full", "io"),
            ("Worker does not meet minimum requirements", "worker_configuration"),
            ("Failed to connect to Deadline Repository", "deadline_communication"),
            ("System.OutOfMemoryException: insufficient space", "memory"),
        ):
            with self.subTest(report=report):
                self.assertEqual(category, self.result(report)["category"])
        self.assertEqual("CRITICO", self.result(CRITICAL)["severity"])
        self.assertNotEqual(self.result("Failed to load plugin RailClone")["signature"],
                            self.result("Failed to load plugin ForestPack")["signature"])

    def test_capacity_comparison_and_redaction(self):
        body = NETWORK + "\nWarning: password=privado\nWarning: https://secret.example/abc\n"
        report = make_report(body)
        report["capacity"]["required_bytes"] = 96 * 1024**3
        evidence = extract(body, report)
        self.assertTrue(classify(evidence, report)["factors"])
        for secret in ("privado", "secret.example", r"nas\project"):
            self.assertNotIn(secret, json.dumps(evidence))

    def test_rolling_window(self):
        start = datetime(2026, 11, 16, 12)
        self.assertEqual(3, window_count([start, start + timedelta(minutes=5), start + timedelta(minutes=15)], timedelta(minutes=15)))
        self.assertEqual(2, window_count([start, start + timedelta(minutes=5), start + timedelta(minutes=16)], timedelta(minutes=15)))
        self.assertEqual(0, window_count([], timedelta(minutes=15)))

    def test_active_requires_explicit_cutoff_and_shadow_defaults(self):
        with patch.dict(os.environ, {"DEADLINE_DIAGNOSTIC_NOTIFY_MODE": "active", "DEADLINE_DIAGNOSTIC_ALERT_FROM_UTC": ""}):
            self.assertEqual(DiagnosticSettings.from_env().mode, "off")
        with patch.dict(os.environ, {"DEADLINE_DIAGNOSTIC_NOTIFY_MODE": "shadow", "DEADLINE_DIAGNOSTIC_ALERT_FROM_UTC": ""}):
            self.assertEqual(DiagnosticSettings.from_env().mode, "shadow")

    def test_sdk_export_uses_official_metadata_and_skips_known_bodies(self):
        def date(utc_time):
            return SimpleNamespace(
                Year=2026, Kind="Utc",
                ToUniversalTime=lambda: SimpleNamespace(ToString=lambda _: "2026-11-16 " + utc_time),
            )
        sdk_report = SimpleNamespace(
            ReportID="a" * 24, ReportJobName="Tracking", ReportSlaveName="IMP-FRM-004",
            ReportTaskID=0, ReportFrameString="0-2", ReportDateTimeOf=date("15:00:00.000000"),
            ReportMessage=CRASH, ReportPluginName="3dsmax", ReportPeakRam=0, ReportError="",
        )
        sdk = Mock()
        sdk.GetJob.return_value = SimpleNamespace(JobId=JOB_A, JobGroup="farm", JobPool="anima",
                                                 GetJobExtraInfoKeyValue=lambda _: "")
        sdk.GetJobReports.return_value.GetErrorReports.return_value = [sdk_report]
        sdk.GetJobTasks.return_value.TaskCollectionAllTasks = []
        sdk.GetJobReports.return_value.GetLogReports.return_value = []
        sdk.GetSlaveInfo.return_value = None
        sdk.GetSlaveSettings.return_value = None
        sdk.GetJobReportLog.return_value = REFERENCE
        modules = {"Deadline.Scripting": SimpleNamespace(RepositoryUtils=sdk),
                   "System": SimpleNamespace(DateTime=None, DateTimeKind=None)}
        spec = importlib.util.spec_from_file_location("sdk_fixture", RENDER / "deadline_report_export.py")
        module = importlib.util.module_from_spec(spec)
        with patch.dict(sys.modules, modules):
            spec.loader.exec_module(module)
        request = {"known": {}, "failures": [], "limit": 4}
        collected = module.export(JOB_A, request)["reports"][0]
        self.assertEqual("0", collected["task_id"])
        self.assertEqual("2026-11-16 15:00:00.000000", collected["occurred_at"])
        self.assertIsNone(collected["peak_ram"])
        request["known"][collected["report_id"]] = [collected["metadata_hash"], True]
        self.assertEqual([], module.export(JOB_A, request)["reports"])
        sdk.GetJobReportLog.assert_called_once_with(sdk_report)
        request["known"][collected["report_id"]][1] = False
        sdk.GetJobReportLog.side_effect = OSError("temporary")
        self.assertEqual("", module.export(JOB_A, request)["reports"][0]["body"])
        job = sdk.GetJob.return_value
        reports = sdk.GetJobReports.return_value
        task = SimpleNamespace(TaskId="0", TaskStatus="Completed", TaskSlaveName="IMP-FRM-004",
            TaskRenderTime=SimpleNamespace(TotalSeconds=300), TaskRenderStartTime=date("15:01:00.000000"),
            TaskCompletedDateTime=date("15:06:00.000000"))
        sdk.GetJobTasks.return_value.TaskCollectionAllTasks = [task]
        reports.GetLogReports.return_value = [SimpleNamespace(ReportTaskID=0, ReportID="success",
            ReportSlaveName="IMP-FRM-004", ReportDateTimeOf=date("15:06:04.000000"), ReportTaskSecondsElapsed=300)]
        recovery = module.recovery_evidence(job, reports, {("0", "imp-frm-004")}, False)
        self.assertEqual("success", recovery[0]["report_id"])
        task.TaskSlaveName = "IMP-FRM-005"
        self.assertEqual([], module.recovery_evidence(job, reports, {("0", "imp-frm-004")}, False))


@unittest.skipUnless(os.getenv("FLOW_TEST_DIAGNOSTICS_MYSQL_PORT"), "explicit isolated MySQL test port required")
class DiagnosticIntegrationTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        import pymysql
        cls.pymysql = pymysql
        cls.options = {"host": "127.0.0.1", "port": int(os.environ["FLOW_TEST_DIAGNOSTICS_MYSQL_PORT"]),
                       "user": os.getenv("FLOW_TEST_DIAGNOSTICS_MYSQL_USER", "root"),
                       "password": os.getenv("FLOW_TEST_DIAGNOSTICS_MYSQL_PASSWORD", ""), "charset": "utf8mb4"}
        cls.schema = "flow_diagnosis_test_" + uuid4().hex
        with pymysql.connect(**cls.options, autocommit=True) as conn, conn.cursor() as cursor:
            cursor.execute(f"CREATE DATABASE `{cls.schema}`")
            cursor.execute(f"USE `{cls.schema}`")
            root = RENDER.parent
            connect = (root / "FlowConnect/migrations/001_flow_connect_core.sql").read_text(encoding="utf-8")
            migration = (root / "sql/2026-11-16_deadline_diagnosis.sql").read_text(encoding="utf-8")
            for statement in re.sub(r"^--.*$", "", connect + "\n" + migration, flags=re.M).split(";"):
                if statement.strip():
                    cursor.execute(statement)

    @classmethod
    def tearDownClass(cls):
        with cls.pymysql.connect(**cls.options, autocommit=True) as conn, conn.cursor() as cursor:
            cursor.execute(f"DROP DATABASE `{cls.schema}`")

    def worker(self):
        from deadline_db import Database

        class TestDatabase(Database):
            def connect(inner):
                if inner.connection is None:
                    inner.connection = self.pymysql.connect(**self.options, database=self.schema)
                return inner.connection

        settings = SimpleNamespace(deadline_command="deadlinecommand", db_name=self.schema)
        logger = logging.getLogger("test.deadline.diagnosis")
        return SimpleNamespace(settings=settings, database=TestDatabase(settings, logger), logger=logger)

    def setUp(self):
        self.worker_a = self.worker()
        self.worker_b = self.worker()
        self.addCleanup(self.worker_a.database.close)
        self.addCleanup(self.worker_b.database.close)
        with patch.dict(os.environ, {"DEADLINE_DIAGNOSTIC_ENABLED": "1", "DEADLINE_DIAGNOSTIC_NOTIFY_MODE": "active",
                                    "DEADLINE_DIAGNOSTIC_ALERT_FROM_UTC": "2000-01-01T00:00:00Z"}):
            self.collector = DiagnosticCollector(self.worker_a)
            self.peer = DiagnosticCollector(self.worker_b)
        with self.worker_a.database.transaction() as cursor:
            for table in ("flow_connect_deliveries", "flow_connect_notifications", "flow_connect_events",
                          "deadline_diagnostic_history", "deadline_diagnostic_recovery",
                          "deadline_diagnostic_occurrences", "deadline_diagnostic_incidents", "deadline_diagnostic_jobs"):
                cursor.execute(f"DELETE FROM {table}")
            self.now = read(cursor, "SELECT UTC_TIMESTAMP(6) AS stamp")[0]["stamp"]
        self.collector.discover([JOB_A, JOB_B])

    def query(self, sql):
        with self.worker_a.database.transaction() as cursor:
            return read(cursor, sql)

    def response(self, job, reports, recovery=()):
        return {"job_id": job, "reports": reports, "backlog": False, "recovery": list(recovery)}

    def record(self, job, *reports, recovery=()):
        with self.worker_a.database.transaction() as cursor:
            self.collector._finish(cursor, job, self.response(job, reports, recovery), self.now)

    def report(self, body, number, minutes, job=JOB_A):
        return make_report(body, number, job=job, stamp=self.now - timedelta(minutes=minutes))

    def events(self):
        return self.query("SELECT event_type,payload_json,metadata_json FROM flow_connect_events ORDER BY id")

    def test_three_reports_across_jobs_and_idempotent_replay(self):
        self.record(JOB_A, self.report(ASSET, 1, 8))
        self.record(JOB_B, self.report(ASSET, 1, 5, JOB_B))
        self.assertEqual((), self.events())
        self.record(JOB_A, self.report(ASSET, 2, 1))
        self.record(JOB_A, self.report(ASSET, 2, 1))
        events = self.events()
        self.assertEqual(["deadline.incidente.aberto"], [row["event_type"] for row in events])
        self.assertEqual(3, json.loads(events[0]["payload_json"])["occurrences"])
        self.assertEqual(2, json.loads(events[0]["payload_json"])["jobs_total"])
        self.assertEqual(1, len(self.query("SELECT id FROM deadline_diagnostic_incidents")))
        self.assertEqual(3, len(self.query("SELECT id FROM deadline_diagnostic_occurrences")))

    def test_different_signatures_and_critical_immediate(self):
        self.record(JOB_A, self.report(ASSET, 1, 3), self.report(NETWORK, 2, 3), self.report(CRITICAL, 3, 2))
        self.assertEqual(3, len(self.query("SELECT id FROM deadline_diagnostic_incidents")))
        self.assertEqual(["deadline.incidente.aberto_critico"], [row["event_type"] for row in self.events()])

    def test_new_undated_critical_report_uses_observation_without_inventing_time(self):
        self.record(JOB_A, make_report(CRITICAL, 1))
        self.assertEqual((), self.events())
        self.record(JOB_A, make_report(CRITICAL, 2))
        events = self.events()
        self.assertEqual(["deadline.incidente.aberto_critico"], [row["event_type"] for row in events])
        self.assertEqual(0, json.loads(events[0]["payload_json"])["window_count"])
        self.assertTrue(all(row["occurred_at"] is None for row in self.query("SELECT occurred_at FROM deadline_diagnostic_occurrences")))

    def test_common_alerts_require_window_and_updates_require_change(self):
        self.record(JOB_A, self.report(ASSET, 1, 35), self.report(ASSET, 2, 19), self.report(ASSET, 3, 3))
        self.assertEqual((), self.events())
        self.record(JOB_A, self.report(ASSET, 4, 2), self.report(ASSET, 5, 1))
        self.assertEqual(3, json.loads(self.events()[0]["payload_json"])["window_count"])
        self.record(JOB_A, self.report(ASSET, 6, 0))
        self.assertEqual(1, len(self.events()))
        with self.worker_a.database.transaction() as cursor:
            cursor.execute("""UPDATE deadline_diagnostic_history SET recorded_at=UTC_TIMESTAMP(6)-INTERVAL 61 MINUTE
                WHERE kind='alert_active'""")
        self.record(JOB_A)
        self.record(JOB_A)
        self.assertEqual(["deadline.incidente.aberto", "deadline.incidente.atualizado"], [row["event_type"] for row in self.events()])

    def test_linked_task_error_prioritizes_job_without_resetting_unchanged_hint(self):
        self.collector.observe_task_errors(JOB_B, False, {"TaskErrorCount": ["0", "1"]})
        with self.worker_a.database.transaction() as cursor:
            job, request = self.collector._prepare(cursor)
            self.assertEqual(JOB_B, job)
            self.collector._finish(cursor, job, self.response(job, []), self.now, request["failure_hint"])
        self.collector.observe_task_errors(JOB_B, False, {"TaskErrorCount": ["0", "1"]})
        with self.worker_a.database.transaction() as cursor:
            self.assertEqual(JOB_A, self.collector._prepare(cursor)[0])

    def test_multijob_recovery_stability_and_reopen(self):
        self.record(JOB_A, self.report(CRITICAL, 1, 59))
        self.record(JOB_B, self.report(CRITICAL, 2, 58, JOB_B))
        completed = {"task_id": "0", "worker": "imp-frm-004", "report_id": "success-A",
                     "started_at": self.now - timedelta(minutes=50), "completed_at": self.now - timedelta(minutes=40)}
        self.record(JOB_A, recovery=[completed])
        self.assertEqual("open", self.query("SELECT state FROM deadline_diagnostic_incidents")[0]["state"])
        self.record(JOB_B, recovery=[dict(completed, report_id="success-B", completed_at=self.now - timedelta(minutes=1))])
        self.assertEqual("monitoring", self.query("SELECT state FROM deadline_diagnostic_incidents")[0]["state"])
        with self.worker_a.database.transaction() as cursor:
            cursor.execute("UPDATE deadline_diagnostic_recovery SET completed_at=%s", (self.now - timedelta(minutes=40),))
        self.record(JOB_B)
        self.record(JOB_B)
        self.assertEqual("resolved", self.query("SELECT state FROM deadline_diagnostic_incidents")[0]["state"])
        self.record(JOB_B, self.report(CRITICAL, 3, 2, JOB_B))  # Delayed report from previous episode.
        self.assertEqual("resolved", self.query("SELECT state FROM deadline_diagnostic_incidents")[0]["state"])
        self.now = self.query("SELECT UTC_TIMESTAMP(6) AS stamp")[0]["stamp"]
        self.record(JOB_A, self.report(CRITICAL, 4, 0))
        self.assertEqual({"state": "open", "episode": 2}, self.query("SELECT state,episode FROM deadline_diagnostic_incidents")[0])
        self.assertEqual(["deadline.incidente.aberto_critico", "deadline.incidente.resolvido_critico",
                          "deadline.incidente.aberto_critico"], [row["event_type"] for row in self.events()])

    def test_shadow_and_active_are_independent(self):
        from dataclasses import replace
        active = self.collector.settings
        self.collector.settings = replace(active, mode="shadow")
        self.record(JOB_A, self.report(CRITICAL, 1, 1))
        self.collector.settings = active
        self.record(JOB_A)
        self.assertEqual(["shadow", "active"], [json.loads(row["metadata_json"])["flow_connect_mode"] for row in self.events()])

    def test_failed_deadline_query_and_concurrent_poll(self):
        from threading import Event
        entered, release = Event(), Event()

        def request(job_id, _):
            entered.set()
            if not release.wait(10):
                raise TimeoutError("test did not release query")
            return None

        with patch.object(self.collector, "_request", side_effect=request), patch.object(self.peer, "_request") as peer_request:
            with ThreadPoolExecutor() as pool:
                first = pool.submit(self.collector.tick)
                try:
                    self.assertTrue(entered.wait(10))
                    pool.submit(self.peer.tick).result(timeout=10)
                    peer_request.assert_not_called()
                finally:
                    release.set()
                first.result(timeout=10)
        self.assertEqual("report_query_failed", self.query(f"SELECT last_error FROM deadline_diagnostic_jobs WHERE job_id='{JOB_A}'")[0]["last_error"])
        with self.worker_a.database.transaction() as cursor:
            cursor.execute("UPDATE deadline_diagnostic_jobs SET next_poll_at=UTC_TIMESTAMP(6)")
        with patch.object(self.collector, "_request", return_value=self.response(JOB_A, [self.report(CRITICAL, 1, 5)])):
            self.collector.tick()
        self.assertEqual(1, len(self.events()))

    def test_outbox_failure_rolls_back_and_retry_keeps_event(self):
        with self.worker_a.database.transaction() as cursor:
            cursor.execute("""CREATE TRIGGER test_event_failure BEFORE INSERT ON flow_connect_events
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='transient outbox failure'""")
        try:
            with self.assertRaises(self.pymysql.MySQLError):
                self.record(JOB_A, self.report(CRITICAL, 1, 1))
            self.assertEqual((), self.query("SELECT id FROM deadline_diagnostic_occurrences"))
        finally:
            with self.worker_a.database.transaction() as cursor:
                cursor.execute("DROP TRIGGER test_event_failure")
        self.record(JOB_A, self.report(CRITICAL, 1, 1))
        self.assertEqual(1, len(self.events()))

    def test_rule_upgrade_preserves_prior_classification_and_incident(self):
        from deadline_diagnostic_reclassify import reclassify
        self.record(JOB_A, self.report(ASSET, 1, 1))
        incident = self.query("SELECT incident_id FROM deadline_diagnostic_occurrences")[0]
        with self.worker_a.database.transaction() as cursor:
            cursor.execute("UPDATE deadline_diagnostic_occurrences SET catalog_version=0")
        self.assertEqual(1, reclassify(self.worker_a.database))
        self.assertEqual(0, reclassify(self.worker_a.database))
        self.assertEqual(incident, self.query("SELECT incident_id FROM deadline_diagnostic_occurrences")[0])
        self.assertEqual(2, len(self.query("SELECT id FROM deadline_diagnostic_history WHERE occurrence_id IS NOT NULL")))

    def test_report_body_unavailable_retries_without_duplicate_occurrences(self):
        self.record(JOB_A, self.report("", 1, 1))
        self.assertEqual("pending", self.query("SELECT body_state FROM deadline_diagnostic_occurrences")[0]["body_state"])
        self.assertEqual((), self.events())
        self.record(JOB_A, self.report(CRITICAL, 1, 1))
        self.assertEqual(["available"], [row["body_state"] for row in self.query("SELECT body_state FROM deadline_diagnostic_occurrences")])
        self.assertEqual(1, len(self.events()))

    def test_flow_connect_plans_collected_events_and_render_reads_incident(self):
        from dataclasses import replace
        active = self.collector.settings
        self.collector.settings = replace(active, mode="shadow")
        self.record(JOB_A, self.report(CRITICAL, 1, 1))
        self.collector.settings = active
        self.record(JOB_A)
        script = r"""<?php
require 'FlowConnect/bootstrap.php';
require 'Render/deadline_diagnostic_flow.php';
$conn = new mysqli('127.0.0.1', getenv('FLOW_TEST_DIAGNOSTICS_MYSQL_USER') ?: 'root',
    getenv('FLOW_TEST_DIAGNOSTICS_MYSQL_PASSWORD') ?: '', getenv('FLOW_TEST_DIAGNOSTICS_SCHEMA'),
    (int) getenv('FLOW_TEST_DIAGNOSTICS_MYSQL_PORT'));
$conn->set_charset('utf8mb4');
putenv('FLOW_CONNECT_DEADLINE_WEBHOOK_URL=https://example.invalid/diagnostics-test');
putenv('FLOW_RENDER_DEADLINE_REPORT_ORIGIN=https://example.invalid');
$planner = new \FlowConnect\Application\EventPlanner($conn, []);
$result = $conn->query('SELECT * FROM flow_connect_events ORDER BY id');
$modes = [];
foreach ($result as $event) {
    $event['payload'] = json_decode($event['payload_json'], true);
    $event['metadata'] = json_decode($event['metadata_json'], true);
    \FlowConnect\Contracts\EventValidator::validate($event);
    $plan = $planner->plan($event);
    $modes[] = $plan['delivery_mode'];
}
$incident = render_diagnostic_page($conn, [], true)['incidents'][0];
$detail = render_diagnostic_page($conn, ['incident_id' => $incident['id']], true);
$occurrence = $detail['occurrences'][0];
echo json_encode(['modes' => $modes, 'report' => $occurrence['original_url'],
    'category' => $occurrence['diagnosis']['category'], 'count' => (int) $incident['occurrences'],
    'denied' => render_diagnostic_page($conn, ['incident_id' => $incident['id']], false) === null]);
"""
        result = subprocess.run(["php"], input=script, capture_output=True, encoding="utf-8", check=True,
                                cwd=RENDER.parent, env={**os.environ, "FLOW_TEST_DIAGNOSTICS_SCHEMA": self.schema})
        response = json.loads(result.stdout)
        self.assertEqual(["SHADOW", "CHANNEL"], response["modes"])
        self.assertEqual("license", response["category"])
        self.assertEqual(1, response["count"])
        self.assertTrue(response["denied"])
        self.assertIn(f"JobID={JOB_A}&Data=errorcontents", response["report"])


if __name__ == "__main__":
    unittest.main()
