"""Reclassify historical report evidence with the current rule catalog."""
from __future__ import annotations

import argparse
import json
import logging

from deadline_config import SETTINGS
from deadline_db import Database
from deadline_diagnosis import CATALOG_VERSION, classify
from deadline_diagnostic_store import encode, history, read


def reclassify(database, job_id=None, batch_size=200):
    latest_id = updated = 0
    while True:
        with database.transaction() as cursor:
            rows = read(cursor, """SELECT * FROM deadline_diagnostic_occurrences
                WHERE id>%s AND catalog_version<%s AND (%s IS NULL OR job_id=%s)
                ORDER BY id LIMIT %s FOR UPDATE""", (latest_id, CATALOG_VERSION, job_id, job_id, batch_size))
            if not rows:
                return updated
            now = read(cursor, "SELECT UTC_TIMESTAMP(6) AS stamp")[0]["stamp"]
            for row in rows:
                diagnosis = classify(json.loads(row["evidence_json"]), json.loads(row["report_json"]))
                cursor.execute("""UPDATE deadline_diagnostic_occurrences
                    SET diagnosis_json=%s,signature=%s,catalog_version=%s WHERE id=%s""",
                    (encode(diagnosis), diagnosis["signature"], CATALOG_VERSION, row["id"]))
                history(cursor, now, "reclassified", diagnosis, row["incident_id"], row["episode"], row["id"])
                latest_id = row["id"]
                updated += 1


def main():
    from deadline_domain import valid_job_id
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--job", help="Restrict to a single Deadline Job ID.")
    args = parser.parse_args()
    if args.job and not valid_job_id(args.job):
        parser.error("Invalid Deadline Job ID")
    database = Database(SETTINGS, logging.getLogger(__name__))
    try:
        print(f"Ocorrências reclassificadas: {reclassify(database, args.job.lower() if args.job else None)}")
    finally:
        database.close()


if __name__ == "__main__":
    main()
