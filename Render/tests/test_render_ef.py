import sys
import os
import unittest
import tempfile
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import Mock, patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from deadline_client import CommandResult, DeadlineClient
from deadline_render_ef import (
    RenderEF,
    ef_resolution,
    ensure_smb_destination_ready,
    plugin_info,
    read_info,
    snapshot,
)


class SmbDestinationReadinessTests(unittest.TestCase):
    def test_new_destination_is_created_and_probe_is_removed(self):
        with tempfile.TemporaryDirectory() as temp:
            destination = str(Path(temp) / "Obra" / "EF" / "Imagem_EF")

            ensure_smb_destination_ready(destination, Mock())

            self.assertTrue(Path(destination).is_dir())
            self.assertEqual(list(Path(destination).iterdir()), [])

    def test_existing_ef_and_existing_destination_are_validated(self):
        with tempfile.TemporaryDirectory() as temp:
            existing_ef = Path(temp) / "Obra" / "EF"
            existing_ef.mkdir(parents=True)
            destination = existing_ef / "Imagem_EF"

            ensure_smb_destination_ready(str(destination), Mock())
            self.assertTrue(destination.is_dir())
            self.assertEqual(list(destination.iterdir()), [])

            ensure_smb_destination_ready(str(destination), Mock())
            self.assertEqual(list(destination.iterdir()), [])

    def test_concurrent_creator_is_tolerated_by_exist_ok(self):
        with tempfile.TemporaryDirectory() as temp:
            destination = str(Path(temp) / "Obra" / "EF" / "Imagem_EF")
            real_makedirs = os.makedirs

            def create_before_call(path, exist_ok=False):
                real_makedirs(path, exist_ok=True)
                return real_makedirs(path, exist_ok=exist_ok)

            with patch(
                "deadline_render_ef.os.makedirs", side_effect=create_before_call
            ) as makedirs:
                ensure_smb_destination_ready(destination, Mock())

            self.assertTrue(
                any(call.args[0] == destination for call in makedirs.call_args_list)
            )
            self.assertTrue(
                all(call.kwargs.get("exist_ok") for call in makedirs.call_args_list)
            )
            self.assertEqual(list(Path(destination).iterdir()), [])

    def test_directory_creation_failure_preserves_original_exception(self):
        destination = r"\\server\renders2\Obra\EF\Imagem_EF"
        denied = PermissionError("access denied")
        with patch("deadline_render_ef.os.makedirs", side_effect=denied):
            with self.assertRaisesRegex(RuntimeError, "criar a pasta") as raised:
                ensure_smb_destination_ready(destination, Mock())

        self.assertIn(destination, str(raised.exception))
        self.assertIs(raised.exception.__cause__, denied)

    def test_write_probe_creation_failure_is_reported_and_cleaned(self):
        with tempfile.TemporaryDirectory() as temp:
            destination = str(Path(temp) / "dest")
            denied = PermissionError("create denied")
            with patch("builtins.open", side_effect=denied):
                with self.assertRaisesRegex(RuntimeError, "arquivo temporário") as raised:
                    ensure_smb_destination_ready(destination, Mock())

            self.assertIs(raised.exception.__cause__, denied)
            self.assertEqual(list(Path(destination).iterdir()), [])

    def test_rename_failure_is_reported_and_probe_is_cleaned(self):
        with tempfile.TemporaryDirectory() as temp:
            destination = str(Path(temp) / "dest")
            denied = PermissionError("rename denied")
            with patch("deadline_render_ef.os.rename", side_effect=denied):
                with self.assertRaisesRegex(RuntimeError, "renomear") as raised:
                    ensure_smb_destination_ready(destination, Mock())

            self.assertIs(raised.exception.__cause__, denied)
            self.assertEqual(list(Path(destination).iterdir()), [])

    def test_delete_failure_is_reported_and_cleanup_retries(self):
        with tempfile.TemporaryDirectory() as temp:
            destination = str(Path(temp) / "dest")
            real_unlink = os.unlink
            denied = PermissionError("delete denied")
            failed_once = False

            def fail_first_delete(path):
                nonlocal failed_once
                if str(path).endswith(".renamed") and not failed_once:
                    failed_once = True
                    raise denied
                return real_unlink(path)

            with patch("deadline_render_ef.os.unlink", side_effect=fail_first_delete):
                with self.assertRaisesRegex(RuntimeError, "excluir") as raised:
                    ensure_smb_destination_ready(destination, Mock())

            self.assertIs(raised.exception.__cause__, denied)
            self.assertEqual(list(Path(destination).iterdir()), [])

    def test_cleanup_failure_is_logged_without_masking_primary_error(self):
        with tempfile.TemporaryDirectory() as temp:
            destination = Path(temp) / "dest"
            destination.mkdir()
            primary = PermissionError("write validation failed")
            cleanup_error = PermissionError("cleanup denied")
            real_open = open

            class FailingProbe:
                def __init__(self, path, mode):
                    self.path = path

                def __enter__(self):
                    self.handle = real_open(self.path, "xb")
                    return self.handle

                def __exit__(self, exc_type, exc, traceback):
                    self.handle.close()
                    raise primary

            logger = Mock()
            with patch("builtins.open", side_effect=FailingProbe), patch(
                "deadline_render_ef.os.unlink", side_effect=cleanup_error
            ):
                with self.assertRaisesRegex(RuntimeError, "gravar") as raised:
                    ensure_smb_destination_ready(str(destination), logger)

            self.assertIs(raised.exception.__cause__, primary)
            self.assertGreaterEqual(logger.exception.call_count, 3)
            for leftover in destination.iterdir():
                leftover.unlink()


class RenderEFTests(unittest.TestCase):
    def test_deadline_utf8_paths_survive_windows_locale(self):
        completed = SimpleNamespace(
            returncode=0,
            stdout="RenderOutput=M:\\VEGETAÇÃO.exr".encode("utf-8"),
            stderr=b"",
        )
        with patch("deadline_client.subprocess.run", return_value=completed), patch(
            "deadline_client.locale.getpreferredencoding", return_value="cp1252"
        ):
            self.assertEqual(
                DeadlineClient().run(["-GetJob", "a" * 24]).output,
                "RenderOutput=M:\\VEGETAÇÃO.exr",
            )

    def test_deadline_oem_stdout_survives_ansi_locale(self):
        expected = "RenderElementOutputFilename15=M:\\Vegetação.exr"
        completed = SimpleNamespace(
            returncode=0, stdout=expected.encode("cp850"), stderr=b""
        )
        with patch("deadline_client.subprocess.run", return_value=completed), patch(
            "deadline_client.locale.getpreferredencoding", return_value="cp1252"
        ), patch("deadline_client._windows_oem_encoding", return_value="cp850"):
            self.assertEqual(
                DeadlineClient().run(["-GetJob", "a" * 24]).output, expected
            )

    def test_largest_dimension_and_rounding(self):
        for old, expected in [
            ((2500, 1250), (5000, 2500)),
            ((1250, 2500), (2500, 5000)),
            ((1920, 1080), (5000, 2813)),
            ((1080, 1920), (2813, 5000)),
            ((1800, 1800), (5000, 5000)),
        ]:
            with self.subTest(old=old):
                self.assertEqual(ef_resolution(*old), expected)

    def test_invalid_resolution_stops(self):
        for size in [(0, 1250), (2500, -1)]:
            with self.assertRaises(RuntimeError):
                ef_resolution(*size)

    def test_plugin_parser_preserves_commas_in_renderer_values(self):
        data = {
            "PluginInfoDictionary": "RenderWidth=2500,RenderHeight=1250,SubmittedRendererId=#(123L, 456L),Camera=Camera008,corona_colors=#([1,1,1], [0,0,0])"
        }
        info = plugin_info(data)
        self.assertEqual(info["RenderWidth"], "2500")
        self.assertEqual(info["SubmittedRendererId"], "#(123L, 456L)")
        self.assertEqual(info["corona_colors"], "#([1,1,1], [0,0,0])")
        self.assertNotIn("Width", info)

    def test_missing_deleted_job_never_mutates_deadline(self):
        calls = []

        def run(args):
            calls.append(args)
            return CommandResult(True, "", 0)

        service = RenderEF(
            SimpleNamespace(database=None, client=SimpleNamespace(run=run))
        )
        with self.assertRaisesRegex(RuntimeError, "não foi encontrado"):
            service.source({"origem_job_id": "a" * 24})
        self.assertEqual(calls, [["-GetDeletedJob", "a" * 24, "True"]])

    def test_submission_copies_settings_and_starts_suspended(self):
        original = {
            "RenderWidth": "1250",
            "RenderHeight": "2500",
            "Camera": "Camera008",
            "SubmittedRendererName": "Corona:Corona",
            "RenderOutput": r"M:\Obra\R00\Imagem\Imagem..exr",
            "RenderElementOutputFilename0": r"M:\Obra\R00\Imagem\alpha..exr",
        }
        generic = {
            "Name": "Imagem",
            "Frames": "5",
            "ChunkSize": "1",
            "Pool": "still",
            "OutputDirectory0": r"M:\Obra\R00\Imagem",
        }
        destination = r"\\192.168.0.250\renders2\Obra\EF\Imagem_EF"
        seen = []

        def run(args):
            seen.append((read_info(Path(args[0])), read_info(Path(args[1])), args[2:]))
            return CommandResult(True, "JobID=" + "b" * 24, 0)

        client = SimpleNamespace(run=run)
        logger = Mock()
        service = RenderEF(
            SimpleNamespace(database=None, client=client, logger=logger)
        )
        service.context = lambda request: {}
        service.storage = Mock(return_value={"destination": destination})

        def update(request, stage, message, status="PROCESSANDO", **fields):
            request.update(etapa=stage, status=status, **fields)

        service.update = update
        with tempfile.TemporaryDirectory() as temp, patch(
            "deadline_render_ef.ensure_smb_destination_ready"
        ) as readiness:
            scene = Path(temp) / "original.max"
            scene.write_bytes(b"unchanged original scene")
            files = [str(scene)]
            service.source = lambda request: ({"_job_info": generic}, original, files)
            plan = {
                "snapshot": snapshot(original),
                "job_snapshot": snapshot(generic),
                "files": files,
                "source_folder": r"\\192.168.0.250\renders2\Obra\R00\Imagem",
                "destination": destination,
                "source_info": original,
                "job_name": "Imagem_EF",
                "width_key": "RenderWidth",
                "height_key": "RenderHeight",
                "width": 2500,
                "height": 5000,
                "outputs": {
                    "RenderOutput": destination + r"\Imagem..exr",
                    "RenderElementOutputFilename0": destination + r"\alpha..exr",
                },
            }
            request = {"id": 7, "origem_job_id": "a" * 24}
            self.assertEqual(service.submit(request, plan, {}), "b" * 24)
            self.assertEqual(scene.read_bytes(), b"unchanged original scene")
        service.storage.assert_called_once_with({}, plan["source_folder"], "inspect")
        readiness.assert_called_once_with(destination, logger)
        self.assertEqual(len(seen), 1)
        job, plugin, auxiliaries = seen[0]
        self.assertEqual(job["InitialStatus"], "Suspended")
        self.assertEqual(job["Frames"], "5")
        self.assertEqual(job["Pool"], "still")
        self.assertEqual(job["OutputDirectory0"], destination)
        self.assertEqual(auxiliaries, files)
        self.assertEqual(plugin["RenderWidth"], "2500")
        self.assertEqual(plugin["RenderHeight"], "5000")
        self.assertEqual(plugin["Camera"], original["Camera"])
        self.assertEqual(
            plugin["SubmittedRendererName"], original["SubmittedRendererName"]
        )
        self.assertEqual(
            plugin["RenderElementOutputFilename0"],
            plan["outputs"]["RenderElementOutputFilename0"],
        )
        self.assertEqual(original["RenderHeight"], "2500")
        self.assertEqual(len(seen), 1)

    def test_smb_readiness_failure_prevents_deadline_submission(self):
        destination = r"\\192.168.0.250\renders2\Obra\EF\Imagem_EF"
        client = SimpleNamespace(run=Mock())
        service = RenderEF(
            SimpleNamespace(database=None, client=client, logger=Mock())
        )
        service.context = lambda request: {}
        service.storage = Mock(return_value={"destination": destination})
        service.update = Mock()
        with tempfile.TemporaryDirectory() as temp:
            scene = Path(temp) / "original.max"
            scene.write_bytes(b"unchanged original scene")
            files = [str(scene)]
            service.source = Mock(
                return_value=(
                    {"_job_info": {"Name": "Imagem"}},
                    {"RenderWidth": "2500", "RenderHeight": "1250"},
                    files,
                )
            )
            plan = {
                "snapshot": snapshot({"RenderWidth": "2500", "RenderHeight": "1250"}),
                "job_snapshot": snapshot({"Name": "Imagem"}),
                "files": files,
                "source_folder": r"\\192.168.0.250\renders2\Obra\R00\Imagem",
                "destination": destination,
            }
            with patch(
                "deadline_render_ef.ensure_smb_destination_ready",
                side_effect=RuntimeError("sem permissao"),
            ):
                with self.assertRaisesRegex(RuntimeError, "sem permissao"):
                    service.submit({"id": 7, "origem_job_id": "a" * 24}, plan, {})

        client.run.assert_not_called()


if __name__ == "__main__":
    unittest.main()
