import json
import pandas as pd
from sqlalchemy import text
from silabar_analytics import pipeline
from silabar_analytics.db import get_engine, read


def test_full_pipeline_writes_every_output(db_url, now):
    s = pipeline.run(["all"], now=now)
    assert s["status"] == "ok", s
    with get_engine().connect() as c:
        n_fc = c.execute(text("SELECT COUNT(*) FROM demand_forecasts")).scalar()
        n_risk = c.execute(text("SELECT COUNT(*) FROM overdue_risk_scores")).scalar()
        kinds = {r[0] for r in c.execute(text("SELECT kind FROM analysis_outputs"))}
    assert n_fc == 14 * 4 and n_risk > 0
    assert {"association_rules", "anomalies", "forecast_report", "risk_report", "run_summary"} <= kinds


def test_procurement_diagnoses_each_cause(db_url, now):
    pipeline.run(["forecast", "procurement"], now=now)
    recs = read("SELECT * FROM procurement_recommendations")
    cause = dict(zip(recs["item_id"], recs["cause"]))
    assert cause[1] == "stock_shortage" and cause[3] == "maintenance" and cause[5] == "late_returns" and cause[10] == "peak_scheduling"
    r1 = recs[recs["item_id"] == 1].iloc[0]
    assert r1["recommended_add_qty"] >= 1 and 0 < r1["priority_score"] <= 1 and r1["status"] == "open"
    assert recs[recs["item_id"] == 3].iloc[0]["recommended_add_qty"] == 0  # repair, don't buy


def test_pipeline_is_idempotent_and_keeps_decisions(db_url, now):
    pipeline.run(["procurement"], now=now)
    first = read("SELECT id, item_id FROM procurement_recommendations")
    s2 = pipeline.run(["procurement"], now=now)
    assert s2["created_recommendations"] == 0
    assert len(read("SELECT id FROM procurement_recommendations")) == len(first)
    with get_engine().begin() as c:  # admin approves one, defers another recently
        c.execute(text("UPDATE procurement_recommendations SET status='approved' WHERE item_id=1"))
        c.execute(text("UPDATE procurement_recommendations SET status='deferred', reviewed_at=:t WHERE item_id=5"), {"t": (now - pd.Timedelta(days=3)).strftime("%Y-%m-%d %H:%M:%S")})
    pipeline.run(["procurement"], now=now)
    rows = read("SELECT item_id, status FROM procurement_recommendations")
    st = dict(zip(rows["item_id"], rows["status"]))
    assert st[1] == "approved" and st[5] == "deferred"
    assert (rows["item_id"] == 1).sum() == 1 and (rows["item_id"] == 5).sum() == 1


def test_association_rules_recover_known_bundles(db_url, now):
    pipeline.run(["assoc"], now=now)
    out = read("SELECT payload FROM analysis_outputs WHERE kind='association_rules'").iloc[0, 0]
    rules = json.loads(out)
    pairs = {(tuple(r["antecedent"]), r["consequent"]) for r in rules}
    assert any(set(a) | {c} in ({3, 4}, {5, 6}, {1, 8}, {8, 9}, {10, 11}, {1, 9}) for a, c in pairs)


def test_anomalies_flag_speaker_spike_and_repeat_unit(db_url, now):
    pipeline.run(["anomaly"], now=now)
    out = json.loads(read("SELECT payload FROM analysis_outputs WHERE kind='anomalies'").iloc[0, 0])
    kinds = {(a["kind"], a["item_id"]) for a in out}
    assert ("demand_spike", 6) in kinds and ("unit_repeat_damage", 1) in kinds


def test_one_failing_task_does_not_stop_the_others(db_url, now, monkeypatch):
    from silabar_analytics import assoc
    monkeypatch.setattr(assoc, "rules", lambda *a, **k: (_ for _ in ()).throw(RuntimeError("boom")))
    s = pipeline.run(["assoc", "anomaly"], now=now)
    assert s["status"] == "partial" and s["tasks"]["assoc"]["status"] == "error" and s["tasks"]["anomaly"]["status"] == "ok"
