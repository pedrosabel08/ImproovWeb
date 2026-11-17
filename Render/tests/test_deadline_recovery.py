import json
import sys
import unittest
from contextlib import contextmanager
from copy import deepcopy
from pathlib import Path
from unittest.mock import Mock

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from deadline_client import CommandResult
from deadline_recovery import Recovery, Rejected, eligible


JOB = "0123456789abcdef01234567"


def worker(name, pool="fast", memory=128, state="idle"):
    return {"name": name, "pools": [pool], "groups": ["3dsmax"], "state": state, "memory": memory}


def snapshot(task="failed"):
    return {"status": "active", "pool": "fast", "secondary": "", "group": "3dsmax",
            "allow": False, "listed": [], "maximum": 1, "release": 50.0, "required_ram": 64,
            "tasks": [{"id": 1, "status": task}, {"id": 2, "status": "completed"},
                      {"id": 3, "status": "rendering"}],
            "limits": [], "workers": [worker("node-1")], "workers_complete": True,
            "pools": ["fast", "slow"], "groups": ["3dsmax"]}


class RecoveryTests(unittest.TestCase):
    def setUp(self):
        self.old = snapshot()
        self.cur = Mock()
        self.cur.fetchone.side_effect = self.row
        self.client = Mock()
        self.client.run.return_value = CommandResult(True, "Deadline response", 0)
        self.recovery = Recovery(Mock(transaction=self.transaction), self.client, Mock())
        self.recovery.save = Mock()
        self.recovery.inspect = Mock(return_value=snapshot())

    @contextmanager
    def transaction(self):
        yield self.cur

    def row(self):
        sql = self.cur.execute.call_args.args[0]
        if "deadline_comandos" in sql:
            return None
        return {"result_json": json.dumps({"state": self.old})}

    def change(self, action, **params):
        result = {"commands": []}
        self.recovery.change({"id": 9, "source_id": 4, "user_id": 7, "render_id": 8,
                              "created_at": "2026-11-17", "job_id": JOB, "action": action,
                              "params_json": json.dumps(params)}, result)
        return result

    def test_rejects_blocking_last_eligible_worker(self):
        with self.assertRaisesRegex(Rejected, "Worker elegível"):
            self.change("deny", worker="NODE-1", blocked=True)
        self.client.run.assert_not_called()

    def test_deny_list_does_not_rewrite_other_machine_limit_settings(self):
        before = self.recovery.inspect.return_value
        before["workers"].append(worker("node-2"))
        after = deepcopy(before)
        after["listed"] = ["node-1"]
        self.recovery.bridge = Mock(return_value=(after, None))
        result = self.change("deny", worker="NODE-1", blocked=True)
        self.client.run.assert_called_once_with(["-AddSlavesToJobMachineLimitList", JOB, "node-1"])
        self.assertEqual(result["observed"]["release"], 50.0)

    def test_retry_sends_only_failed_ids_and_requires_observed_state(self):
        self.recovery.bridge = Mock(side_effect=[(snapshot(), None), (snapshot("rendering"), None)])
        result = self.change("retry", tasks=[1])
        self.client.run.assert_called_once_with(["-ResumeFailedJobTasks", JOB, "1"])
        self.assertEqual(result["observed"]["tasks"][1]["status"], "completed")

    def test_retry_rejects_mixed_task_selection(self):
        self.recovery.bridge = Mock(return_value=(snapshot(), None))
        with self.assertRaises(Rejected):
            self.change("retry", tasks=[1, 2, 3])
        self.client.run.assert_not_called()

    def test_cli_success_is_not_success_when_suspend_is_unverified(self):
        self.recovery.bridge = Mock(return_value=(snapshot(), None))
        with self.assertRaisesRegex(RuntimeError, "does not match"):
            self.change("suspend")
        self.client.run.assert_called_once_with(["-SuspendJobNonRenderingTasks", JOB])

    def test_route_rejects_invalid_worker_combination(self):
        with self.assertRaisesRegex(Rejected, "Worker elegível"):
            self.change("route", pool="slow", group="3dsmax")
        self.client.run.assert_not_called()

    def test_route_confirms_both_properties_and_preserves_secondary_pool(self):
        before = self.recovery.inspect.return_value
        before["workers"].append(worker("other", "slow"))
        before["groups"].append("none")
        after = dict(before, pool="slow", group="none")
        self.recovery.bridge = Mock(side_effect=[
            ({"saved": True}, CommandResult(True, "SDK response", 0)), (after, None)
        ])
        result = self.change("route", pool="slow", group="none")
        route_args, route_values = self.recovery.bridge.call_args_list[0]
        self.assertEqual(route_args, (JOB, "route"))
        self.assertEqual(route_values["before"]["pool"], "fast")
        self.assertEqual(result["observed"]["secondary"], before["secondary"])
        self.client.run.assert_not_called()

    def test_resume_suspended_job(self):
        self.old["status"] = "suspended"
        self.recovery.bridge = Mock(side_effect=[(self.old, None), (snapshot(), None)])
        self.change("resume")
        self.client.run.assert_called_once_with(["-ResumeJob", JOB])

    def test_resubmit_links_the_new_job_before_resuming_it(self):
        new_job = "abcdef0123456789abcdef01"
        defaults = {"name": "Original", "frames": "1-10", "chunk": 2}
        self.old = {"status": "completed", "resubmit": defaults}
        self.cur.fetchone.side_effect = lambda: (
            None if "status='unverified'" in self.cur.execute.call_args.args[0] else self.row()
        )
        self.recovery.resubmit_origin = Mock(return_value=({"imagem_id": 3}, {"id": 4}))
        self.recovery.bind_resubmitted_job = Mock(return_value=2)
        self.recovery.bridge = Mock(side_effect=[
            (self.old, None),
            ({"saved": True}, CommandResult(True, "Prepared", 0)),
            ({"status": "active"}, None),
        ])

        def run(args):
            if args[0] == "-ResubmitJob":
                self.recovery.bind_resubmitted_job.assert_not_called()
                self.assertEqual(args, ["-ResubmitJob", JOB, "2-6", "2", "True"])
                return CommandResult(True, f"Result=Success\nJobID={new_job}", 0)
            self.recovery.bind_resubmitted_job.assert_called_once()
            self.assertEqual(args, ["-ResumeJob", new_job])
            return CommandResult(True, "Resumed", 0)

        self.client.run.side_effect = run
        result = self.change("resubmit", name="Nova tentativa", frames="2-6", chunk=2, suspended=False)
        self.assertEqual(result["new_job_id"], new_job)
        self.assertEqual(result["attempt"], 2)

    def test_resubmit_rejects_a_job_that_is_no_longer_completed(self):
        self.old = {"status": "completed", "resubmit": {"name": "Original", "frames": "1", "chunk": 1}}
        self.recovery.bridge = Mock(return_value=({"status": "active"}, None))
        with self.assertRaises(Rejected):
            self.change("resubmit", name="Cópia", frames="1", chunk=1, suspended=False)
        self.client.run.assert_not_called()

    def test_eligibility_requires_membership_capacity_and_live_worker(self):
        state = snapshot()
        state.update(secondary="slow", limits=[{"allow": True, "listed": ["C", "D", "E"], "excluded": ["b"]}])
        workers = [worker("a"), worker("b", "slow"), worker("c", memory=32),
                   worker("d", state="offline"), worker("e", memory=None)]
        self.assertEqual(eligible(state, workers), (["b"], ["c"]))

    def test_stale_snapshot_and_allow_list_are_rejected(self):
        self.cur.fetchone.side_effect = [None]
        with self.assertRaisesRegex(Rejected, "expirou"):
            self.change("resume")
        self.cur.fetchone.side_effect = self.row
        self.old["allow"] = self.recovery.inspect.return_value["allow"] = True
        with self.assertRaisesRegex(Rejected, "Deny List"):
            self.change("deny", worker="node-1", blocked=True)
        self.client.run.assert_not_called()


if __name__ == "__main__":
    unittest.main()
