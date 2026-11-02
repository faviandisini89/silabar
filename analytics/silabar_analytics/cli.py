from __future__ import annotations
import argparse
import json
from . import pipeline


def main(argv=None) -> int:
    p = argparse.ArgumentParser(prog="silabar-analytics")
    sub = p.add_subparsers(dest="cmd", required=True)
    r = sub.add_parser("run", help="Jalankan analisis")
    r.add_argument("--tasks", default="all", help="all atau daftar: " + ",".join(pipeline.ALL_TASKS))
    args = p.parse_args(argv)
    summary = pipeline.run(args.tasks.split(","))
    print(json.dumps(summary, indent=2, ensure_ascii=False, default=str))
    return 0 if summary["status"] == "ok" else 1


if __name__ == "__main__":
    raise SystemExit(main())
