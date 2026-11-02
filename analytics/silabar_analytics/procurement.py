"""Stockout analysis: turns failed requests (unmet_demand_logs) into procurement recommendations."""
from __future__ import annotations
import math
import numpy as np
import pandas as pd
from .forecast import weekly_series

RESERVING = ("approved", "borrowed", "overdue", "returned")
DAYS_ID = ["Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu", "Minggu"]


def _ends(df: pd.DataFrame, now: pd.Timestamp) -> pd.Series:
    end = df["due_at"].mask(df["status"] == "overdue", df["due_at"].clip(lower=now))
    return end.mask(df["status"] == "returned", df["returned_at"])


def occupancy(lines: pd.DataFrame, item_id: int, now: pd.Timestamp, days: int, statuses) -> np.ndarray:
    df = lines[(lines["item_id"] == item_id) & lines["status"].isin(statuses) & lines["start_at"].notna()]
    out = np.zeros(days)
    if df.empty:
        return out
    end = _ends(df, now)
    for i in range(days):
        s = now.normalize() - pd.Timedelta(days=i)
        e = s + pd.Timedelta(days=1)
        m = (df["start_at"] < e) & (end > s)
        out[i] = df.loc[m, "qty"].sum()
    return out


def usable_stock(item: pd.Series, units: pd.DataFrame) -> int:
    if item["tracking_type"] == "bulk":
        return int(item["total_stock"])
    u = units[units["item_id"] == item["id"]]
    return int(u["status"].isin(["available", "loaned"]).sum())


def total_stock(item: pd.Series, units: pd.DataFrame) -> int:
    if item["tracking_type"] == "bulk":
        return int(item["total_stock"])
    u = units[units["item_id"] == item["id"]]
    return int((u["status"] != "lost").sum())


def compute(items, units, lines, unmet, settings, forecasts, now) -> list[dict]:
    now = pd.Timestamp(now)
    win = int(settings["procure.window_days"])
    W = pd.Timedelta(days=win)
    out = []
    created = lines.drop_duplicates("loan_id")
    for _, it in items.iterrows():
        iid = int(it["id"])
        um = unmet[unmet["item_id"] == iid]
        logs = um[um["created_at"] >= now - W]
        if logs.empty:
            continue
        n = len(logs)
        users = int(logs["user_id"].nunique())
        prev = int(((um["created_at"] >= now - 2 * W) & (um["created_at"] < now - W)).sum())
        has = lines[(lines["item_id"] == iid) & ~lines["status"].isin(["rejected", "cancelled"]) & (lines["created_at"] >= now - W) & (lines["created_at"] <= now)]
        ok = int(has["loan_id"].nunique())
        fill = ok / (ok + n) if (ok + n) else 1.0
        trig = (n >= settings["procure.min_unmet"] and users >= settings["procure.min_users"]) or (fill * 100 < settings["procure.fill_below"] and n > 0)
        if not trig:
            continue
        usable = usable_stock(it, units)
        # daily concurrent demand = occupied quantity + quantity of requests that failed around that day
        occ = occupancy(lines, iid, now, win, RESERVING)
        dem = occ.copy()
        for _, u in logs.iterrows():
            for i in range(win):
                s = now.normalize() - pd.Timedelta(days=i)
                if u["requested_start_at"] < s + pd.Timedelta(days=2) and u["requested_end_at"] > s:
                    dem[i] += u["requested_qty"]
        p90 = float(np.percentile(dem, 90))
        peak = max(0, math.ceil(dem.max() - usable))
        util = float(logs["util"].fillna(0).mean())
        maint_share = float(logs["maint_share"].fillna(0).mean())
        od_share = float((logs["overdue_units"].fillna(0) > 0).mean())
        total = max(1, total_stock(it, units))
        avg_occ = occupancy(lines, iid, now, 30, ("borrowed", "overdue", "returned"))
        avg_u = float(np.mean(np.minimum(1.0, avg_occ / total)))
        starts = logs["requested_start_at"]
        bins = pd.DataFrame({"d": starts.dt.weekday, "b": (starts.dt.hour // 3) * 3}).value_counts()
        (top_d, top_b), top_n = bins.index[0], int(bins.iloc[0])
        top_share = top_n / n
        day_names = [DAYS_ID[d] for d in pd.Series(starts.dt.weekday).value_counts().index[:2]]
        top_days = " dan ".join(day_names)
        S = settings
        if maint_share >= S["procure.maint_share"] / 100:
            cause = "maintenance"
        elif od_share >= 0.4:
            cause = "late_returns"
        elif top_share >= 0.55 and avg_u < 0.5:
            cause = "peak_scheduling"
        else:
            cause = "stock_shortage"
        # forecast reinforcement
        proj = 1.0
        f = forecasts[forecasts["item_id"] == iid] if forecasts is not None and len(forecasts) else None
        recent = weekly_series(lines, iid, now, weeks=8)
        if f is not None and len(f) and len(recent) >= 4:
            proj = float(f["upper_bound"].mean()) / max(1.0, float(recent.iloc[-4:].mean()))
        boost = min(max(proj, 1.0), 1.5) if proj > 1.25 else 1.0
        add = max(1, math.ceil(p90 * boost - usable)) if cause == "stock_shortage" else 0
        tr = float(np.clip(0.5 + (n - prev) / (2 * max(n, prev, 1)), 0, 1))
        pr = (S["procure.w_unmet"] * min(1, n / 15) + S["procure.w_users"] * min(1, users / 10)
              + S["procure.w_util"] * min(1, util) + S["procure.w_trend"] * tr)
        band = f"{top_b:02d}.00 sampai {top_b + 3:02d}.00"
        why = {
            "stock_shortage": f"Utilisasi saat permintaan gagal rata-rata {util:.0%}; hampir semua unit sehat sedang terpakai. Usulan menambah {add} unit.",
            "maintenance": f"{maint_share:.0%} unit sedang rusak atau dalam perawatan saat permintaan gagal. Perbaiki atau ganti unit lebih dulu, bukan beli tambahan.",
            "late_returns": f"{od_share:.0%} permintaan gagal terjadi ketika ada unit yang belum dikembalikan. Perkuat pengingat dan tegakkan denda keterlambatan.",
            "peak_scheduling": f"Kegagalan menumpuk pada {top_days}, sekitar pukul {band}, sementara utilisasi rata-rata hanya {avg_u:.0%}. Atur jadwal, batasi durasi, atau bagi per sesi.",
        }[cause]
        if boost > 1.0:
            why += f" Perkiraan permintaan 4 pekan ke depan naik sekitar {int((proj - 1) * 100)}% dari rata-rata terakhir."
        out.append({"item_id": iid, "unmet_count": int(n), "affected_users": users, "fill_rate": round(fill, 4), "peak_shortage_qty": int(peak),
                    "recommended_add_qty": int(add), "cause": cause, "priority_score": round(float(pr), 4), "rationale": why,
                    "window_text": f"{top_days} pukul {band}", "trend": int(n - prev),
                    "period_start": now - W, "period_end": now})
    return sorted(out, key=lambda r: -r["priority_score"])
