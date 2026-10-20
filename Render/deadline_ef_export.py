"""Read-only export for deadlinecommand -ExecuteScriptNoGui.

RepositoryUtils accepts the Deleted Job object directly. This avoids recovering
an old Job merely to export its original submission and auxiliary directory.
"""

import json
from Deadline.Scripting import RepositoryUtils


def __main__(*args):
    if len(args) != 4:
        raise RuntimeError(
            "Expected Job ID, Job Info, Plugin Info and metadata filenames"
        )
    job = RepositoryUtils.GetDeletedJob(args[0])
    if job is None:
        raise RuntimeError("Deleted source Job is unavailable")
    RepositoryUtils.CreateJobSubmissionFiles(job, args[1], args[2])
    with open(args[3], "w", encoding="utf-8") as output:
        json.dump(
            {
                "auxiliary_path": RepositoryUtils.GetJobAuxiliaryPath(job),
                "auxiliary_names": list(job.JobAuxiliarySubmissionFileNames),
            },
            output,
        )
