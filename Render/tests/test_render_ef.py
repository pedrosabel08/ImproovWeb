import sys
import unittest
import tempfile
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from deadline_client import CommandResult, DeadlineClient
from deadline_render_ef import RenderEF, ef_resolution, plugin_info, read_info, snapshot


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

        service = RenderEF(
            SimpleNamespace(database=None, client=SimpleNamespace(run=run))
        )
        service.context = lambda request: {}
        service.storage = lambda *args: {"destination": destination}

        def update(request, stage, message, status="PROCESSANDO", **fields):
            request.update(etapa=stage, status=status, **fields)

        service.update = update
        with tempfile.TemporaryDirectory() as temp:
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


if __name__ == "__main__":
    unittest.main()
