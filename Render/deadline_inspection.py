"""Legacy inspection-job identification only. No inspection execution exists."""
import re


def is_inspection(data):
    raw=data.get('ExtraInfoDictionary','')
    if isinstance(raw,dict):
        return raw.get('FlowJobType')=='SceneInspection' or 'FlowInspectionUUID' in raw
    if isinstance(raw,list):raw=','.join(raw)
    return bool(re.search(r'(?:^|[,\s])(?:FlowJobType=SceneInspection(?:$|[,\s])|FlowInspectionUUID=)',str(raw)))
