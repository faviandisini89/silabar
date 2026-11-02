"""Anomalies: demand spikes/drops (Isolation Forest + robust z-score) and repeatedly failing units."""
from __future__ import annotations
import numpy as np
import pandas as pd
from sklearn.ensemble import IsolationForest
from .forecast import weekly_series


def demand_anomalies(items: pd.DataFrame, lines: pd.DataFrame, now, lookback: int = 40) -> list[dict]:
    rows = []
    for _, it in items.iterrows():
        s = weekly_series(lines, int(it["id"]), now, weeks=lookback)
        if len(s) < 14:
            continue
        y = s.to_numpy(float)
        for i in range(10, len(y)):
            prev = y[i - 8:i]
            med = np.median(prev)
            mad = np.median(np.abs(prev - med)) * 1.4826 or max(prev.std(), 0.8)
            rows.append({"item_id": int(it["id"]), "name": it["name"], "week": s.index[i], "y": y[i], "z": (y[i] - med) / mad, "median": med, "last": i == len(y) - 1})
    if len(rows) < 30:
        return []
    df = pd.DataFrame(rows)
    X = df[["y", "z", "median"]].to_numpy(float)
    iso = IsolationForest(n_estimators=150, contamination=0.04, random_state=0).fit(X)
    df["iso"] = -iso.score_samples(X)
    cur = df[df["last"]]
    out = []
    thr = np.quantile(df["iso"], 0.94)
    for _, r in cur.iterrows():
        if r["iso"] >= thr and abs(r["z"]) >= 1.5 and (r["y"] >= 3 or r["median"] >= 3):
            kind = "demand_spike" if r["z"] > 0 else "demand_drop"
            word = "Lonjakan permintaan" if r["z"] > 0 else "Permintaan anjlok"
            out.append({"kind": kind, "item_id": int(r["item_id"]), "score": round(float(r["iso"]), 3), "z": round(float(r["z"]), 2),
                        "text": f"{word}: {r['name']} {int(r['y'])} peminjaman pekan lalu, biasanya {r['median']:.1f}"})
    return sorted(out, key=lambda a: -abs(a["z"]))


def unit_anomalies(units: pd.DataFrame, maint: pd.DataFrame, now, days: int = 120, min_events: int = 2) -> list[dict]:
    if maint.empty:
        return []
    recent = maint[maint["started_at"] >= pd.Timestamp(now) - pd.Timedelta(days=days)]
    cnt = recent.groupby("item_unit_id").size()
    out = []
    for uid, n in cnt.items():
        if n >= min_events:
            u = units[units["id"] == uid]
            if u.empty:
                continue
            out.append({"kind": "unit_repeat_damage", "item_id": int(u.iloc[0]["item_id"]), "unit_id": int(uid), "score": float(n), "z": float(n),
                        "text": f"{u.iloc[0]['asset_code']} masuk perawatan {int(n)} kali dalam {days} hari"})
    return sorted(out, key=lambda a: -a["score"])


def run(items, lines, units, maint, now) -> list[dict]:
    return (demand_anomalies(items, lines, now) + unit_anomalies(units, maint, now))[:12]
