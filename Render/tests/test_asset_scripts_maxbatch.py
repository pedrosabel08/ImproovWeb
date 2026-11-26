"""Optional real MAXScript contract test; no scene is opened and no job is sent."""
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from deadline_render_ef import asset_base_script


@unittest.skipUnless(os.getenv('FLOW_TEST_MAXBATCH'), 'Set FLOW_TEST_MAXBATCH to 3dsmaxbatch.exe')
class AssetScriptsMaxBatchTests(unittest.TestCase):
    def test_both_phases_and_original_results(self):
        with tempfile.TemporaryDirectory(prefix='flow_mxs_test_') as temp:
            folder = Path(temp)
            def mxs_path(path):
                return '"' + str(path).replace('\\', '\\\\').replace('"', '\\"') + '"'
            lines = ['(', 'local out = createFile ' + mxs_path(folder / 'results.txt')]
            expected = []
            cases = [('none', None), ('true', 'true'), ('false', 'false'),
                     ('exception', 'throw "flow test exception"'), ('invalid', '"not boolean"')]
            for phase in ('PreLoadScript', 'PostLoadScript'):
                for label, original in cases:
                    name = phase + '_' + label
                    source = {}
                    if original is not None:
                        original_name = name + '_original.ms'
                        (folder / original_name).write_text(original + '\n', encoding='utf-8')
                        source[phase] = original_name
                    script = folder / (name + '.ms')
                    script.write_text(asset_base_script({'scene_path': str(folder / 'scene.max'), 'source_info': source}, phase), encoding='utf-8-sig')
                    lines += ['try (format "%:%\\n" ' + mxs_path(name) + ' (fileIn ' + mxs_path(script) + ') to:out)',
                              'catch (format "%:exception\\n" ' + mxs_path(name) + ' to:out)']
                    expected.append(name + ':' + ('true' if label in ('none', 'true') else 'exception' if label == 'exception' else 'false'))
            lines += ['close out', ')']
            runner = folder / 'runner.ms'
            runner.write_text('\n'.join(lines) + '\n', encoding='utf-8-sig')
            result = subprocess.run([os.environ['FLOW_TEST_MAXBATCH'], str(runner)],
                                    capture_output=True, text=True, timeout=180)
            output = folder / 'results.txt'
            diagnostics = (result.stdout + result.stderr).replace('\x00', '')[-3000:]
            self.assertTrue(output.exists(), f'3ds Max Batch exit {result.returncode} without results: {diagnostics}')
            self.assertEqual(output.read_text(encoding='utf-8-sig').splitlines(), expected)
            self.assertEqual(result.returncode, 0, result.stdout[-3000:] + result.stderr[-3000:])
