"""Weekly demand forecasting: moving-average baseline vs. gradient-boosted lag model, chosen by backtest."""
from __future__ import annotations
import math
import numpy as np
import pandas as pd
from sklearn.ensemble import GradientBoostingRegressor
from .repository import COUNTED

MIN_WEEKS_ML = 20


def week_start(ts) -> pd.Timestamp:
    ts = pd.Timestamp(ts).normalize()
    return ts - pd.Timedelta(days=ts.weekday())


def weekly_series(lines: pd.DataFrame, item_id: int, now, weeks: int = 104) -> pd.Series:
    """Loans containing the item per week. The final point is the last *complete* week."""
    end = week_start(now) - pd.Timedelta(weeks=1)
    idx = pd.date_range(end=end, periods=weeks, freq="7D")
    df = lines[(lines["item_id"] == item_id) & lines["status"].isin(COUNTED) & lines["start_at"].notna()]
    if df.empty:
        return pd.Series(0.0, index=idx)
    ws = df["start_at"].dt.normalize() - pd.to_timedelta(df["start_at"].dt.weekday, unit="D")
    counts = df.assign(w=ws).groupby("w")["loan_id"].nunique()
    s = counts.reindex(idx, fill_value=0).astype(float)
    nz = np.flatnonzero(s.to_numpy() > 0)
    return s.iloc[nz[0]:] if len(nz) else s


def _feat_row(y: np.ndarray, i: int, ws: pd.Timestamp) -> list[float]:
    woy = ws.isocalendar().week
    return [y[i - 1], y[i - 2], y[i - 3], y[i - 4], y[i - 4:i].mean(), y[i - 8:i].mean(),
            math.sin(2 * math.pi * woy / 52), math.cos(2 * math.pi * woy / 52)]


def _dataset(s: pd.Series):
    y = s.to_numpy(float)
    X, t, idx = [], [], []
    for i in range(8, len(y)):
        X.append(_feat_row(y, i, s.index[i]))
        t.append(y[i])
        idx.append(i)
    return np.array(X), np.array(t), idx


def _gbr(alpha: float | None = None) -> GradientBoostingRegressor:
    kw = dict(n_estimators=80, max_depth=2, learning_rate=0.06, subsample=0.9, random_state=0)
    return GradientBoostingRegressor(loss="quantile", alpha=alpha, **kw) if alpha else GradientBoostingRegressor(**kw)


def _baseline_next(y: np.ndarray, k: int) -> float:
    l4 = y[-4:].mean()
    p4 = y[-8:-4].mean() if len(y) >= 8 else l4
    return max(0.0, l4 + (l4 - p4) / 4 * k * 0.5)


def backtest(s: pd.Series, folds: int = 8) -> dict:
    """Rolling-origin one-step MAE for baseline and GBR."""
    X, t, idx = _dataset(s)
    y = s.to_numpy(float)
    n = len(t)
    folds = min(folds, max(3, n - 12))
    e_base, e_ml = [], []
    for j in range(n - folds, n):
        e_base.append(abs(_baseline_next(y[:idx[j]], 1) - t[j]))
        m = _gbr().fit(X[:j], t[:j])
        e_ml.append(abs(float(m.predict(X[j:j + 1])[0]) - t[j]))
    return {"mae_baseline": float(np.mean(e_base)), "mae_ml": float(np.mean(e_ml)), "folds": folds}


def forecast_item(s: pd.Series, horizon: int = 4) -> tuple[list[dict], dict]:
    y = s.to_numpy(float)
    last_ws = s.index[-1]
    steps = [last_ws + pd.Timedelta(weeks=k) for k in range(1, horizon + 1)]
    sd = float(np.std(y[-8:])) or 1.0
    info = {"model": "moving_avg", "weeks": len(y)}
    use_ml = len(y) >= MIN_WEEKS_ML and y.sum() >= 15
    if use_ml:
        bt = backtest(s)
        info.update(bt)
        use_ml = bt["mae_ml"] <= 0.95 * bt["mae_baseline"]
    out = []
    if not use_ml:
        for k, ws in enumerate(steps, start=1):
            v = _baseline_next(y, k)
            w = 1.28 * sd * math.sqrt(1 + 0.15 * k)
            out.append({"forecast_date": ws, "predicted": v, "lower": max(0.0, v - w), "upper": v + w, "model": "moving_avg"})
        return out, info
    X, t, _ = _dataset(s)
    mid, lo_m, hi_m = _gbr().fit(X, t), _gbr(0.1).fit(X, t), _gbr(0.9).fit(X, t)
    hist = list(y)
    idx = list(s.index)
    for k, ws in enumerate(steps, start=1):
        arr = np.array(hist)
        row = np.array([_feat_row(arr, len(arr), ws)])
        p = max(0.0, float(mid.predict(row)[0]))
        lo, hi = float(lo_m.predict(row)[0]), float(hi_m.predict(row)[0])
        widen = math.sqrt(1 + 0.15 * (k - 1))
        lo = max(0.0, p - (p - lo) * widen)
        hi = max(p, p + (hi - p) * widen)
        out.append({"forecast_date": ws, "predicted": p, "lower": min(lo, p), "upper": hi, "model": "gbr_lag"})
        hist.append(p)
        idx.append(ws)
    info["model"] = "gbr_lag"
    return out, info


def forecast_all(items: pd.DataFrame, lines: pd.DataFrame, now, horizon: int = 4):
    rows, report = [], {}
    for _, it in items.iterrows():
        s = weekly_series(lines, int(it["id"]), now)
        if len(s) < 6:
            continue
        res, info = forecast_item(s, horizon)
        report[int(it["id"])] = info
        for r in res:
            rows.append({"item_id": int(it["id"]), **r})
    return pd.DataFrame(rows), report
