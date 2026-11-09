import struct
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import Mock, patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from deadline_output import validate_outputs

def exr_fixture():
    def attr(name,kind,value):return name+b'\0'+kind+b'\0'+struct.pack('<I',len(value))+value
    channel=b'R\0'+struct.pack('<iB3xii',1,0,1,1)+b'\0'
    return b'\x76\x2f\x31\x01'+struct.pack('<I',2)+attr(b'channels',b'chlist',channel)+attr(b'dataWindow',b'box2i',struct.pack('<4i',0,0,0,0))+b'\0'+b'\0'*24


class OutputTests(unittest.TestCase):
    def test_F_monitor_rejects_completion_before_any_business_effect(self):
        from deadline_monitor import process_deadline_job
        from deadline_output import OutputValidationError
        cursor=Mock()
        with patch('deadline_output.validate_completed',side_effect=RuntimeError('missing output')),patch('deadline_monitor.deadline_error_info') as reports:
            with self.assertRaises(OutputValidationError):
                process_deadline_job(cursor,deadline_job_id='a'*24,job_data={'Status':'Completed','Name':'Image','Frames':'0'})
        cursor.execute.assert_not_called()
        reports.assert_not_called()
    def test_F_master_required_optional_elements_not_required(self):
        with tempfile.TemporaryDirectory() as temp:
            master=Path(temp)/'master.0000.exr';master.write_bytes(exr_fixture())
            outputs={'RenderOutput':str(Path(temp)/'master..exr'),'RenderElementOutputFilename0':str(Path(temp)/'optional..jpg')}
            self.assertEqual(validate_outputs(outputs,['RenderOutput'],'0'),[str(master)])
            with self.assertRaisesRegex(RuntimeError,'ausente'):validate_outputs(outputs,list(outputs),'0')

    def test_F_missing_corrupt_zero_old_or_ambiguous_outputs_block(self):
        with tempfile.TemporaryDirectory() as temp:
            path=Path(temp)/'master.exr';outputs={'RenderOutput':str(path)}
            with self.assertRaises(RuntimeError):validate_outputs(outputs,['RenderOutput'],'0')
            for data in [b'',b'corrupt output data'*3]:
                path.write_bytes(data)
                with self.assertRaises(RuntimeError):validate_outputs(outputs,['RenderOutput'],'0')
            path.write_bytes(exr_fixture())
            with self.assertRaises(RuntimeError):validate_outputs(outputs,['RenderOutput'],'0',earliest=path.stat().st_mtime+1000)
            other=Path(temp)/'master0000.exr';other.write_bytes(path.read_bytes())
            with self.assertRaisesRegex(RuntimeError,'ambígua'):validate_outputs(outputs,['RenderOutput'],'0')

    def test_no_single_file_can_prove_multiple_frames(self):
        with tempfile.TemporaryDirectory() as temp:
            path=Path(temp)/'render.exr';path.write_bytes(exr_fixture())
            with self.assertRaises(RuntimeError):validate_outputs({'RenderOutput':str(path)},['RenderOutput'],'0-1')


if __name__=='__main__':unittest.main()
