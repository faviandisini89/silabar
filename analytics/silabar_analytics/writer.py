"""Write-side: persist task results. Everything here runs inside a transaction."""
from __future__ import annotations
import json
import pandas as pd
from sqlalchemy import text
from .db import get_engine

FMT = "%Y-%m-%d %H:%M:%S"


def ts(v) -> str | None:
    return None if v is None or pd.isna(v) else pd.Timestamp(v).strftime(FMT)


def write_forecasts(df: pd.DataFrame, now, eng=None) -> int:
    eng = eng or get_engine()
    if df.empty:
        return 0
    with eng.begin() as c:
        ids = sorted(set(int(i) for i in df["item_id"]))
        c.execute(text(f"DELETE FROM demand_forecasts WHERE item_id IN ({','.join(str(i) for i in ids)})"))
        c.execute(text("INSERT INTO demand_forecasts (item_id, forecast_date, predicted_qty, lower_bound, upper_bound, model_name, generated_at) "
                       "VALUES (:i, :d, :p, :l, :u, :m, :g)"),
                  [{"i": int(r.item_id), "d": ts(r.forecast_date), "p": round(float(r.predicted), 3), "l": round(float(r.lower), 3),
                    "u": round(float(r.upper), 3), "m": r.model, "g": ts(now)} for r in df.itertuples()])
    return len(df)


def write_risk(df: pd.DataFrame, now, eng=None) -> int:
    eng = eng or get_engine()
    with eng.begin() as c:
        c.execute(text("DELETE FROM overdue_risk_scores"))
        if df.empty:
            return 0
        c.execute(text("INSERT INTO overdue_risk_scores (loan_id, risk_probability, model_version, scored_at) VALUES (:l, :p, :m, :s)"),
                  [{"l": int(r.loan_id), "p": round(float(r.probability), 4), "m": r.model_version, "s": ts(now)} for r in df.itertuples()])
    return len(df)


def write_output(kind: str, payload, now, item_id=None, eng=None) -> None:
    eng = eng or get_engine()
    with eng.begin() as c:
        c.execute(text("DELETE FROM analysis_outputs WHERE kind = :k"), {"k": kind})
        c.execute(text("INSERT INTO analysis_outputs (kind, item_id, payload, generated_at) VALUES (:k, :i, :p, :g)"),
                  {"k": kind, "i": item_id, "p": json.dumps(payload, ensure_ascii=False, default=str), "g": ts(now)})


def upsert_recommendations(recs: list[dict], now, eng=None) -> dict:
    """Keep decided recommendations intact; refresh open ones; add new ones unless recently decided."""
    eng = eng or get_engine()
    now = pd.Timestamp(now)
    created = updated = removed = 0
    with eng.begin() as c:
        rows = c.execute(text("SELECT id, item_id, status, reviewed_at FROM procurement_recommendations")).mappings().all()
        triggered = {r["item_id"] for r in recs}
        for r in rows:
            if r["status"] == "open" and r["item_id"] not in triggered:
                c.execute(text("DELETE FROM procurement_recommendations WHERE id = :i"), {"i": r["id"]})
                removed += 1
        rows = [r for r in rows if not (r["status"] == "open" and r["item_id"] not in triggered)]
        for rec in recs:
            params = {"i": rec["item_id"], "ps": ts(rec["period_start"]), "pe": ts(rec["period_end"]), "u": rec["unmet_count"], "a": rec["affected_users"],
                      "f": rec["fill_rate"], "pk": rec["peak_shortage_qty"], "ad": rec["recommended_add_qty"], "ca": rec["cause"],
                      "pr": rec["priority_score"], "ra": rec["rationale"], "w": rec["window_text"], "t": rec["trend"], "g": ts(now)}
            open_row = next((r for r in rows if r["item_id"] == rec["item_id"] and r["status"] == "open"), None)
            if open_row:
                c.execute(text("UPDATE procurement_recommendations SET period_start=:ps, period_end=:pe, unmet_count=:u, affected_users=:a, fill_rate=:f, "
                               "peak_shortage_qty=:pk, recommended_add_qty=:ad, cause=:ca, priority_score=:pr, rationale=:ra, window_text=:w, trend=:t, "
                               "generated_at=:g, updated_at=:g WHERE id=:id"), {**params, "id": open_row["id"]})
                updated += 1
                continue
            blocked = False
            for r in rows:
                if r["item_id"] != rec["item_id"]:
                    continue
                if r["status"] == "approved":
                    blocked = True
                elif r["status"] != "open" and r["reviewed_at"] and pd.Timestamp(r["reviewed_at"]) > now - pd.Timedelta(days=30):
                    blocked = True
            if blocked:
                continue
            c.execute(text("INSERT INTO procurement_recommendations (item_id, period_start, period_end, unmet_count, affected_users, fill_rate, "
                           "peak_shortage_qty, recommended_add_qty, cause, priority_score, status, rationale, window_text, trend, generated_at, created_at, updated_at) "
                           "VALUES (:i,:ps,:pe,:u,:a,:f,:pk,:ad,:ca,:pr,'open',:ra,:w,:t,:g,:g,:g)"), params)
            created += 1
    return {"created": created, "updated": updated, "removed": removed}
