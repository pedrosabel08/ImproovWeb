from __future__ import annotations

import sys
import unittest
from pathlib import Path
from unittest.mock import patch

RENDER_DIR = Path(__file__).resolve().parents[1]
if str(RENDER_DIR) not in sys.path:
    sys.path.insert(0, str(RENDER_DIR))

from deadline_client import CommandResult, DeadlineClient
from deadline_repository import build_observation_plan
from deadline_worker import DeadlineWorker, first_submission_plan
from deadline_domain import (
    AGUARDANDO_JOB,
    EM_ANDAMENTO,
    EM_APROVACAO,
    ENCERRADA,
    ERRO,
    EXCLUSAO_PENDENTE,
    REPROVADA,
    backoff_seconds,
    choose_unambiguous_candidates,
    delete_output_means_not_found,
    state_from_deadline,
    transition_allowed,
)

JOB_A = "a" * 24
JOB_B = "b" * 24


class FlowModel:
    """Modelo em memória para validar invariantes transacionais do desenho."""

    def __init__(self):
        self.attempts = [
            {"id": 1, "number": 1, "job": JOB_A, "active": True, "status": EM_ANDAMENTO}
        ]
        self.render_cache = JOB_A
        self.commands = []

    def reprove(self):
        old = next(item for item in self.attempts if item["active"])
        old["active"] = False
        old["status"] = EXCLUSAO_PENDENTE if old["job"] else REPROVADA
        if old["job"] and not any(
            command["job"] == old["job"] for command in self.commands
        ):
            self.commands.append(
                {"job": old["job"], "status": "PENDENTE", "locked": None}
            )
        new = {
            "id": old["id"] + 1,
            "number": old["number"] + 1,
            "job": None,
            "active": True,
            "status": AGUARDANDO_JOB,
        }
        self.attempts.append(new)
        return old, new

    def bind(self, job):
        if any(item["job"] == job for item in self.attempts):
            return False
        waiting = [
            item
            for item in self.attempts
            if item["active"] and item["status"] == AGUARDANDO_JOB and not item["job"]
        ]
        if len(waiting) != 1:
            return False
        waiting[0]["job"] = job
        waiting[0]["status"] = EM_ANDAMENTO
        self.render_cache = job
        return True

    def reserve(self, worker):
        for command in self.commands:
            if command["status"] == "PENDENTE":
                command["status"] = "PROCESSANDO"
                command["locked"] = worker
                return command
        return None

    def recover(self):
        for command in self.commands:
            if command["status"] == "PROCESSANDO":
                command["status"] = "PENDENTE"
                command["locked"] = None

    def finish_delete(self, job):
        command = next(command for command in self.commands if command["job"] == job)
        command["status"] = "CONCLUIDO"
        attempt = next(item for item in self.attempts if item["job"] == job)
        attempt["status"] = ENCERRADA
        if self.render_cache == job:
            self.render_cache = None


class FakeP00Cursor:
    def __init__(self, current_status="Em andamento"):
        self.current_status = current_status
        self.executed = []

    def execute(self, sql, params=()):
        self.executed.append((" ".join(sql.split()), params))

    def fetchone(self):
        return (10, self.current_status)


class FakeDiscoveryLogger:
    def __init__(self):
        self.records = []

    def warning(self, message, extra=None):
        self.records.append(("warning", message, extra or {}))

    def debug(self, message, extra=None):
        self.records.append(("debug", message, extra or {}))

    def info(self, message, extra=None):
        self.records.append(("info", message, extra or {}))


class DiscoveryModel:
    """Modelo de contratos da descoberta: primeiro envio e reenvio."""

    def __init__(self):
        self.renders = {}
        self.attempts = []
        self.jobs = set()
        self.next_render = 100
        self.next_attempt = 1000

    def add_waiting_attempt(self, image_id, status_id):
        render_id = self.next_render
        self.next_render += 1
        self.renders[(image_id, status_id)] = render_id
        self.attempts.append(
            {
                "id": self.next_attempt,
                "render_id": render_id,
                "image_id": image_id,
                "status_id": status_id,
                "status": AGUARDANDO_JOB,
                "active": True,
                "job": None,
            }
        )
        self.next_attempt += 1

    def discover(self, job_id, image_ids, image_id=2075, status_id=2, target=EM_ANDAMENTO):
        if job_id in self.jobs:
            return "JOB_ALREADY_KNOWN", None
        if not image_ids:
            return "NO_IMAGE_MATCH", None
        if len(image_ids) != 1:
            return "AMBIGUOUS_IMAGE", None
        render_id = self.renders.get((image_id, status_id))
        if render_id is None:
            render_id = self.next_render
            self.next_render += 1
            self.renders[(image_id, status_id)] = render_id
            attempt = {
                "id": self.next_attempt,
                "render_id": render_id,
                "image_id": image_id,
                "status_id": status_id,
                "numero_tentativa": 1,
                "status": target,
                "active": True,
                "job": job_id,
            }
            self.next_attempt += 1
            self.attempts.append(attempt)
            self.jobs.add(job_id)
            return "FIRST_RENDER_CREATED", attempt
        waiting = [
            item
            for item in self.attempts
            if item["render_id"] == render_id
            and item["active"]
            and item["status"] == AGUARDANDO_JOB
            and item["job"] is None
        ]
        if len(waiting) != 1:
            return "NO_WAITING_ATTEMPT", None
        waiting[0]["job"] = job_id
        waiting[0]["status"] = "VINCULADA"
        self.jobs.add(job_id)
        return "RESEND", waiting[0]


class DeadlineWorkerAcceptanceTests(unittest.TestCase):
    def test_case_01_normal_rejection_creates_history_command_and_new_attempt(self):
        model = FlowModel()
        old, new = model.reprove()
        self.assertEqual(old["job"], JOB_A)
        self.assertEqual(old["status"], EXCLUSAO_PENDENTE)
        self.assertFalse(old["active"])
        self.assertEqual(
            model.commands, [{"job": JOB_A, "status": "PENDENTE", "locked": None}]
        )
        self.assertEqual(new["status"], AGUARDANDO_JOB)
        self.assertIsNone(new["job"])
        self.assertEqual(model.render_cache, JOB_A)

    def test_case_02_new_job_before_old_delete_does_not_clear_new_cache(self):
        model = FlowModel()
        old, new = model.reprove()
        self.assertTrue(model.bind(JOB_B))
        model.finish_delete(JOB_A)
        self.assertEqual(old["job"], JOB_A)
        self.assertEqual(new["job"], JOB_B)
        self.assertEqual(model.render_cache, JOB_B)

    def test_case_03_worker_offline_keeps_pending_command_and_new_attempt(self):
        model = FlowModel()
        _old, new = model.reprove()
        self.assertEqual(model.commands[0]["status"], "PENDENTE")
        self.assertTrue(new["active"])
        self.assertEqual(new["status"], AGUARDANDO_JOB)

    def test_case_04_job_not_found_is_idempotent_success(self):
        client = DeadlineClient("deadlinecommand")
        with patch.object(
            client,
            "run",
            return_value=CommandResult(
                False, "Job could not be found", returncode=1, not_found=True
            ),
        ):
            result = client.delete_job(JOB_A)
        self.assertTrue(result.success)
        self.assertTrue(result.not_found)
        self.assertFalse(
            delete_output_means_not_found(
                "The specified repository path does not exist (DeadlineConfigException)"
            )
        )
        self.assertTrue(delete_output_means_not_found("The specified Job does not exist"))
        with patch.object(
            client,
            "run",
            return_value=CommandResult(True, "", returncode=0),
        ):
            missing_result, data = client.get_job(JOB_A)
        self.assertFalse(missing_result.success)
        self.assertTrue(missing_result.not_found)
        self.assertEqual(data, {})

    def test_case_05_deadline_failure_uses_required_backoff(self):
        self.assertEqual(
            [backoff_seconds(i) for i in range(1, 7)], [10, 30, 60, 300, 900, 900]
        )
        self.assertEqual(
            state_from_deadline({"Status": "Active"}, {"TaskErrorCount": "1"}),
            ERRO,
        )
        self.assertEqual(
            state_from_deadline({"Status": "Completed", "ErrorCount": "1"}, {}),
            EM_APROVACAO,
        )

    def test_case_06_stuck_processing_command_is_recovered(self):
        model = FlowModel()
        model.reprove()
        model.reserve("worker-a")
        model.recover()
        self.assertEqual(model.commands[0]["status"], "PENDENTE")
        self.assertIsNone(model.commands[0]["locked"])

    def test_case_07_two_workers_cannot_reserve_same_command(self):
        model = FlowModel()
        model.reprove()
        first = model.reserve("worker-a")
        second = model.reserve("worker-b")
        self.assertIsNotNone(first)
        self.assertIsNone(second)

    def test_case_08_old_job_cannot_reopen_or_replace_new_attempt(self):
        model = FlowModel()
        _old, new = model.reprove()
        self.assertFalse(model.bind(JOB_A))
        self.assertIsNone(new["job"])
        self.assertFalse(transition_allowed(REPROVADA, EM_ANDAMENTO))
        self.assertFalse(transition_allowed(ENCERRADA, EM_APROVACAO))

    def test_case_09_two_jobs_for_one_attempt_are_not_selected(self):
        selected, conflicts = choose_unambiguous_candidates(
            [
                {"tentativa_id": 2, "job_id": JOB_A},
                {"tentativa_id": 2, "job_id": JOB_B},
            ]
        )
        self.assertEqual(selected, [])
        self.assertEqual(len(conflicts[2]), 2)

    def test_case_10_render_deletion_is_logical_and_preserves_fk_history(self):
        php = (RENDER_DIR / "deadline_flow.php").read_text(encoding="utf-8")
        ajax = (RENDER_DIR / "ajax.php").read_text(encoding="utf-8")
        add_render = (RENDER_DIR.parent / "addRender.php").read_text(encoding="utf-8")
        self.assertIn("SET status = 'Arquivado', excluido_em = NOW()", php)
        self.assertIn("deadline_flow_reactivate_archived_locked", add_render)
        self.assertNotIn("DELETE FROM render_alta", ajax)

    def test_case_11_approval_uses_persistent_delete_queue(self):
        php = (RENDER_DIR / "deadline_flow.php").read_text(encoding="utf-8")
        self.assertIn("function deadline_flow_approve_locked", php)
        self.assertIn("deadline_flow_enqueue_delete", php)
        self.assertIn("APROVADA_SEM_JOB", php)

    def test_case_12_p00_rollup_updates_aggregate_without_duplicate_notification(self):
        import deadline_monitor

        cursor = FakeP00Cursor(current_status="Em andamento")
        rollup = {
            55: {
                "total_jobs": 2,
                "completed_jobs": 1,
                "any_error": False,
                "any_incomplete": True,
                "all_complete": False,
                "resp_id": 7,
                "image_name_db": "IMG_TESTE",
            }
        }
        with patch.object(deadline_monitor, "send_webhook_message") as webhook:
            deadline_monitor.finalize_p00_rollup(
                cursor, rollup, notifications_enabled=False
            )
        updates = [
            params
            for sql, params in cursor.executed
            if sql.startswith("UPDATE render_alta")
        ]
        self.assertEqual(updates, [("Em andamento", 10)])
        webhook.assert_not_called()

    def test_case_13_reconciliation_qualifies_duplicate_timestamp_column(self):
        repository = (RENDER_DIR / "deadline_repository.py").read_text(
            encoding="utf-8"
        )
        self.assertIn(
            "atualizado_em = deadline_comandos.atualizado_em", repository
        )
        self.assertNotIn(
            "ON DUPLICATE KEY UPDATE atualizado_em = atualizado_em", repository
        )

    def test_case_14_identical_observation_does_not_trigger_processing(self):
        plan = build_observation_plan(
            EM_ANDAMENTO, EM_ANDAMENTO, "same-fingerprint", "same-fingerprint"
        )
        self.assertFalse(plan["state_changed"])
        self.assertFalse(plan["process"])
        self.assertFalse(plan["process_preview"])
        self.assertFalse(plan["send_notifications"])

    def test_case_15_approval_transition_is_a_single_state_event(self):
        plan = build_observation_plan(
            EM_ANDAMENTO, EM_APROVACAO, "old-fingerprint", "new-fingerprint"
        )
        self.assertTrue(plan["state_changed"])
        self.assertTrue(plan["process"])
        self.assertTrue(plan["process_preview"])
        self.assertTrue(plan["process_post"])
        self.assertTrue(plan["send_notifications"])
        self.assertEqual(plan["state_event_key"], "EM_ANDAMENTO->EM_APROVACAO")

    def test_case_16_changed_preview_is_processed_without_post_or_slack(self):
        plan = build_observation_plan(
            EM_APROVACAO, EM_APROVACAO, "old-preview", "new-preview"
        )
        self.assertFalse(plan["state_changed"])
        self.assertTrue(plan["process"])
        self.assertTrue(plan["process_preview"])
        self.assertFalse(plan["process_post"])
        self.assertFalse(plan["send_notifications"])

    def test_case_17_first_job_creates_render_and_attempt(self):
        model = DiscoveryModel()
        outcome, attempt = model.discover(JOB_A, [2075])
        self.assertEqual(outcome, "FIRST_RENDER_CREATED")
        self.assertEqual(attempt["numero_tentativa"], 1)
        self.assertEqual(attempt["status"], EM_ANDAMENTO)
        self.assertTrue(attempt["active"])

    def test_case_18_two_instances_do_not_duplicate_first_job(self):
        model = DiscoveryModel()
        first, _attempt = model.discover(JOB_A, [2075])
        second, duplicate = model.discover(JOB_A, [2075])
        self.assertEqual(first, "FIRST_RENDER_CREATED")
        self.assertEqual(second, "JOB_ALREADY_KNOWN")
        self.assertIsNone(duplicate)
        self.assertEqual(len(model.renders), 1)
        self.assertEqual(len(model.attempts), 1)

    def test_case_19_render_created_between_read_and_insert_is_reclassified_as_resend(self):
        model = DiscoveryModel()
        model.add_waiting_attempt(2075, 2)
        outcome, attempt = model.discover(JOB_A, [2075])
        self.assertEqual(outcome, "RESEND")
        self.assertEqual(attempt["job"], JOB_A)

    def test_case_20_rejection_resend_uses_waiting_attempt(self):
        model = DiscoveryModel()
        model.add_waiting_attempt(2075, 2)
        outcome, attempt = model.discover(JOB_B, [2075])
        self.assertEqual(outcome, "RESEND")
        self.assertEqual(attempt["status"], "VINCULADA")

    def test_case_21_old_terminal_attempt_cannot_reopen(self):
        model = DiscoveryModel()
        model.renders[(2075, 2)] = 10
        model.attempts.append(
            {"id": 1, "render_id": 10, "status": ENCERRADA, "active": False, "job": JOB_A}
        )
        model.jobs.add(JOB_A)
        outcome, _attempt = model.discover(JOB_B, [2075])
        self.assertEqual(outcome, "NO_WAITING_ATTEMPT")

    def test_case_22_ambiguous_name_is_not_selected(self):
        model = DiscoveryModel()
        outcome, _attempt = model.discover(JOB_A, [2075, 2076])
        self.assertEqual(outcome, "AMBIGUOUS_IMAGE")

    def test_case_23_known_job_is_not_first_render_again(self):
        model = DiscoveryModel()
        model.discover(JOB_A, [2075])
        outcome, _attempt = model.discover(JOB_A, [2075])
        self.assertEqual(outcome, "JOB_ALREADY_KNOWN")

    def test_case_24_first_active_job_uses_em_andamento(self):
        model = DiscoveryModel()
        _outcome, attempt = model.discover(JOB_A, [2075], target=EM_ANDAMENTO)
        self.assertEqual(attempt["status"], EM_ANDAMENTO)

    def test_case_25_first_completed_job_processes_preview_post_and_slack(self):
        plan = first_submission_plan(EM_APROVACAO)
        self.assertTrue(plan["process_preview"])
        self.assertTrue(plan["process_post"])
        self.assertTrue(plan["send_notifications"])

    def test_case_26_first_render_preserves_notification_and_post_plan(self):
        plan = first_submission_plan(EM_APROVACAO)
        self.assertEqual(plan["state_event_key"], "PRIMEIRO_ENVIO->EM_APROVACAO")
        self.assertTrue(plan["state_changed"])

    def test_case_27_resend_does_not_depend_on_deadline_submission_time(self):
        worker_source = (RENDER_DIR / "deadline_worker.py").read_text(
            encoding="utf-8"
        )
        self.assertNotIn("JOB_OLDER_THAN_ATTEMPT", worker_source)
        self.assertNotIn("submitted_at <", worker_source)

    def test_case_28_multiple_unknown_jobs_remain_unlinked(self):
        selected, conflicts = choose_unambiguous_candidates(
            [
                {"tentativa_id": 4101, "job_id": JOB_A},
                {"tentativa_id": 4101, "job_id": JOB_B},
            ]
        )
        self.assertEqual(selected, [])
        self.assertEqual([item["job_id"] for item in conflicts[4101]], [JOB_A, JOB_B])

    def test_case_29_discovery_logs_multiple_unmatched_jobs_without_binding(self):
        class Client:
            def list_job_ids(self):
                return CommandResult(True, "", returncode=0), [JOB_A, JOB_B]

            def get_job(self, job_id):
                return CommandResult(True, "", returncode=0), {
                    "Name": "2.WER_RIO Fotomontagem aerea 2_003"
                }

        class Repository:
            def __init__(self):
                self.bind_calls = []

            def known_job_ids(self):
                return set()

            def discovery_target(self, _job_id, _job_name):
                return {
                    "outcome": "RESEND",
                    "tentativa_id": 4101,
                    "render_id": 356465,
                }

            def bind_job(self, *args):
                self.bind_calls.append(args)
                return True

        worker = DeadlineWorker.__new__(DeadlineWorker)
        worker.client = Client()
        worker.repository = Repository()
        worker.logger = FakeDiscoveryLogger()
        worker.unlinked_jobs_seen = set()
        worker.discover()

        self.assertEqual(worker.repository.bind_calls, [])
        warnings = [record for record in worker.logger.records if record[0] == "warning"]
        self.assertEqual(warnings[0][2]["reason"], "MULTIPLE_UNMATCHED_JOBS")

    def test_case_30_failed_render_processing_leaves_observation_retryable(self):
        repository = (RENDER_DIR / "deadline_repository.py").read_text(
            encoding="utf-8"
        )
        worker = (RENDER_DIR / "deadline_worker.py").read_text(encoding="utf-8")
        observation_start = repository.index("    def observe_deadline_state(")
        confirmation_start = repository.index(
            "    def confirm_deadline_observation("
        )
        observation = repository[observation_start:confirmation_start]

        # Planejar a observacao nao pode consumir o evento. Caso a atualizacao
        # do Render falhe (por exemplo, por queda de conexao), o job deve ser
        # processado novamente no proximo ciclo.
        self.assertNotIn("UPDATE render_tentativas", observation)
        self.assertNotIn("INSERT INTO render_tentativa_eventos", observation)
        self.assertIn("def confirm_deadline_observation", repository)
        self.assertGreater(
            worker.index("confirm_deadline_observation", worker.index("def sync_active")),
            worker.index("finalize_p00_rollup", worker.index("def sync_active")),
        )


if __name__ == "__main__":
    unittest.main(verbosity=2)
