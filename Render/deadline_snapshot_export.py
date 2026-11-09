"""Read-only Deadline SDK export. Never restores, submits or modifies a Job."""
import json
from Deadline.Scripting import RepositoryUtils


def __main__(*args):
    if len(args) != 4:
        raise RuntimeError('Expected Job ID and three local export paths')
    job = RepositoryUtils.GetJob(args[0], True)
    origin = 'DEADLINE_ATIVO'
    if job is None:
        job = RepositoryUtils.GetDeletedJob(args[0])
        origin = 'DEADLINE_EXCLUIDO'
    if job is None or str(job.JobId) != args[0]:
        raise RuntimeError('Job original não disponível no Deadline, inclusive excluídos.')
    RepositoryUtils.CreateJobSubmissionFiles(job, args[1], args[2])
    with open(args[3], 'w', encoding='utf-8') as out:
        json.dump({'job_id':str(job.JobId), 'origin':origin,
                   'auxiliary_path':RepositoryUtils.GetJobAuxiliaryPath(job),
                   'auxiliary_names':list(job.JobAuxiliarySubmissionFileNames)},out)
