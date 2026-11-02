"""Orchestration: load once, run the requested tasks, persist, summarise."""
from __future__ import annotations
import time
import traceback
import pandas as pd
from . import anomaly, assoc, forecast, procurement, repository as repo, risk, writer
from .config import get_config
from .db import get_engine

ALL_TASKS = ["forecast", "risk", "assoc", "anomaly", "procurement"]


def run(tasks: list[str] | None = None, now=None, eng=None) -> dict:
    cfg = get_config()
    eng = eng or get_engine()
    now = pd.Timestamp(now) if now is not None else pd.Timestamp.now().floor("s")
    tasks = [t for t in ALL_TASKS if (not tasks or "all" in tasks or t in tasks)]
    t0 = time.time()
    summary = {"started_at": writer.ts(now), "tasks": {}, "created_recommendations": 0}
    items, units, loans, details = repo.items(eng), repo.units(eng), repo.loans(eng), repo.details(eng)
    lines = repo.lines(eng)
    settings = repo.settings(eng)

    def step(name, fn):
        s = time.time()
        try:
            res = fn() or {}
            summary["tasks"][name] = {"status": "ok", "seconds": round(time.time() - s, 2), **res}
        except Exception as e:  # keep the other tasks running
            summary["tasks"][name] = {"status": "error", "error": f"{type(e).__name__}: {e}", "trace": traceback.format_exc(limit=3)}

    if "forecast" in tasks:
        def f():
            df, rep = forecast.forecast_all(items, lines, now, cfg.horizon_weeks)
            n = writer.write_forecasts(df, now, eng)
            writer.write_output("forecast_report", {str(k): v for k, v in rep.items()}, now, eng=eng)
            return {"rows": n, "models": {m: sum(1 for v in rep.values() if v["model"] == m) for m in {v["model"] for v in rep.values()}}}
        step("forecast", f)
    if "risk" in tasks:
        def f():
            scores, rep = risk.train_and_score(loans, details, items, cfg.min_risk_samples, cfg.min_risk_positives)
            n = writer.write_risk(scores, now, eng)
            writer.write_output("risk_report", rep, now, eng=eng)
            return {"scored": n, **rep}
        step("risk", f)
    if "assoc" in tasks:
        def f():
            bs = assoc.baskets(lines)
            rules = assoc.rules(bs)
            writer.write_output("association_rules", rules, now, eng=eng)
            return {"baskets": len(bs), "rules": len(rules)}
        step("assoc", f)
    if "anomaly" in tasks:
        def f():
            res = anomaly.run(items, lines, units, repo.maintenance(eng), now)
            writer.write_output("anomalies", res, now, eng=eng)
            return {"found": len(res)}
        step("anomaly", f)
    if "procurement" in tasks:
        def f():
            recs = procurement.compute(items, units, lines, repo.unmet(eng), settings, repo.forecasts(eng), now)
            res = writer.upsert_recommendations(recs, now, eng)
            summary["created_recommendations"] = res["created"]
            return {"triggered": len(recs), **res}
        step("procurement", f)
    summary["seconds"] = round(time.time() - t0, 2)
    summary["status"] = "ok" if all(v["status"] == "ok" for v in summary["tasks"].values()) else "partial"
    writer.write_output("run_summary", summary, now, eng=eng)
    return summary
