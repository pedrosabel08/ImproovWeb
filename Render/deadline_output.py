"""Validate a completed Job's files before any approval/POS/upload side effect."""
from __future__ import annotations
import datetime
import json
import ntpath
import os
import re
import struct


class OutputValidationError(RuntimeError):
    pass


def exr_header_valid(path):
    with open(path,'rb') as handle:
        data=handle.read(4*1024*1024)
    if data[:4] != b'\x76\x2f\x31\x01' or len(data)<16:
        return False
    offset=8; attributes={}
    try:
        while data[offset] != 0:
            end=data.index(b'\0',offset);name=data[offset:end];offset=end+1
            end=data.index(b'\0',offset);kind=data[offset:end];offset=end+1
            length=struct.unpack_from('<I',data,offset)[0];offset+=4
            value=data[offset:offset+length]
            if len(value)!=length or length>4*1024*1024:return False
            attributes[name]=(kind,value);offset+=length
        window=attributes[b'dataWindow'];channels=attributes[b'channels']
        if window[0]!=b'box2i' or len(window[1])!=16 or channels[0]!=b'chlist' or len(channels[1])<18:return False
        xmin,ymin,xmax,ymax=struct.unpack('<4i',window[1])
        return xmax>=xmin and ymax>=ymin and os.path.getsize(path)>offset+9
    except (KeyError,IndexError,ValueError,struct.error):
        return False


def expand_frames(value):
    result = set()
    for part in str(value or "").split(","):
        match = re.fullmatch(r"(-?\d+)(?:-(-?\d+)(?:x([1-9]\d*))?)?", part.strip())
        if not match:
            raise RuntimeError("Não foi possível identificar os frames esperados do Job.")
        start = int(match[1]); end = int(match[2] or start); step = int(match[3] or 1)
        if end < start or (end-start)//step > 10000:
            raise RuntimeError("Intervalo de saída inválido ou grande demais para validar.")
        result.update(range(start, end+1, step))
        if len(result) > 10000:
            raise RuntimeError("Quantidade de frames excede o limite de validação.")
    return sorted(result)


def file_candidates(template, frame, padding=4):
    number = str(abs(frame)).zfill(padding)
    if frame < 0: number = "-" + number
    folder, filename = os.path.split(template)
    if re.search(r"#+|%0?\d*d|<FRAME>|\$F\d*", filename, re.I):
        def replacement(match):
            token = match[0]
            digits = re.search(r"\d+", token)
            width = len(token) if token.startswith("#") else int(digits[0]) if digits else padding
            return ("-" if frame < 0 else "") + str(abs(frame)).zfill(width)
        return [os.path.join(folder, re.sub(r"#+|%0?\d*d|<FRAME>|\$F\d*", replacement, filename, flags=re.I))]
    stem, ext = os.path.splitext(filename)
    if stem.endswith("."):
        return [os.path.join(folder, stem + number + ext)]
    return list(dict.fromkeys([template, os.path.join(folder, stem + number + ext), os.path.join(folder, stem + "." + number + ext)]))


def valid_image(path):
    size = os.path.getsize(path)
    if size < 16:
        return False
    ext = os.path.splitext(path)[1].lower()
    with open(path, "rb") as handle:
        head = handle.read(32)
        handle.seek(max(0, size-12)); tail = handle.read()
    if ext in {".jpg", ".jpeg"}: return head.startswith(b"\xff\xd8\xff") and tail.endswith(b"\xff\xd9")
    if ext == ".png": return head.startswith(b"\x89PNG\r\n\x1a\n") and b"IEND" in tail
    if ext in {".tif", ".tiff"}: return head[:4] in {b"II*\0", b"MM\0*", b"II+\0", b"MM\0+"}
    if ext in {".exr", ".cxr"}: return exr_header_valid(path)
    if ext == ".bmp": return head.startswith(b"BM") and struct.unpack("<I", head[2:6])[0] <= size
    if ext == ".tga": return head[2] in {1,2,3,9,10,11} and struct.unpack("<HH",head[12:16])[0] > 0 and struct.unpack("<HH",head[12:16])[1] > 0
    return False


def validate_outputs(outputs, required, frame_spec, folder=None, earliest=None, padding=4):
    frames = expand_frames(frame_spec)
    checked = []
    for key in required:
        template = outputs.get(key)
        if not template:
            raise RuntimeError("O Job concluído não informa a saída obrigatória " + key)
        if folder and ntpath.normcase(ntpath.dirname(template)) != ntpath.normcase(folder):
            raise RuntimeError("A saída do Job não pertence à pasta desta tentativa.")
        per_frame = []
        for frame in frames:
            matches = [p for p in file_candidates(template, frame, padding) if os.path.isfile(p)]
            if len(matches) != 1:
                raise RuntimeError("Saída ausente ou ambígua para o frame " + str(frame) + ": " + ntpath.basename(template))
            path = matches[0]
            if earliest is not None and os.path.getmtime(path) < earliest - 300:
                raise RuntimeError("O arquivo de saída é anterior à submissão atual.")
            if not valid_image(path):
                raise RuntimeError("Arquivo de saída vazio, inválido ou incompleto: " + ntpath.basename(path))
            per_frame.append(path)
        if len(set(per_frame)) != len(frames):
            raise RuntimeError("O mesmo arquivo não pode comprovar vários frames da tentativa.")
        checked.extend(per_frame)
    return checked


def validate_completed(cursor, job_id, job_data, convert_path):
    raw = job_data.get("PluginInfoDictionary", "")
    if isinstance(raw, list): raw = ",".join(raw)
    plugin = dict(p.split("=",1) for p in re.split(r",(?=[A-Za-z_][A-Za-z0-9_]*=)", str(raw)) if "=" in p)
    cursor.execute("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='render_ef_solicitacoes'")
    installed = cursor.fetchone()
    row = None
    if installed:
        cursor.execute("SELECT plano_json FROM render_ef_solicitacoes WHERE novo_job_id=%s LIMIT 1", (job_id,))
        row = cursor.fetchone()
    plan = json.loads(row[0]) if row and row[0] else {}
    outputs = plan.get("outputs") or {"RenderOutput": plugin.get("RenderOutput", "")}
    # Convert drive letters using the same mapping as the existing monitor.
    normalized = {}
    for key, path in outputs.items():
        mapped_folder = convert_path(path)
        if not mapped_folder:
            raise RuntimeError("O Job concluído não possui um destino de saída válido.")
        normalized[key] = os.path.join(mapped_folder, ntpath.basename(path))
    earliest = None
    submitted = job_data.get("SubmitDateTime") or job_data.get("SubmitDate")
    if submitted:
        try: earliest = datetime.datetime.fromisoformat(str(submitted).replace("Z", "+00:00")).timestamp()
        except ValueError: pass
    return validate_outputs(normalized, plan.get("required_outputs", ["RenderOutput"]),
                            plan.get("frames") or job_data.get("Frames") or job_data.get("JobFrames"),
                            folder=plan.get("destination"), earliest=earliest,
                            padding=0 if str(plugin.get('RemovePadding','0')) == '1' else int(plugin.get("Padding", "4")))
