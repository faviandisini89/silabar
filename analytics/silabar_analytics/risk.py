"""Overdue-risk model: probability that a loan is returned late, learned from past returns."""
from __future__ import annotations
import numpy as np
import pandas as pd
from sklearn.ensemble import GradientBoostingClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import roc_auc_score
from sklearn.pipeline import make_pipeline
from sklearn.preprocessing import StandardScaler

FEATURES = ["late_rate", "prior_n", "days", "lead_days", "due_weekend", "start_hour", "n_lines", "total_qty", "log_value", "any_approval"]
ACTIVE = ("pending", "approved", "borrowed")


def build_features(loans: pd.DataFrame, details: pd.DataFrame, items: pd.DataFrame) -> pd.DataFrame:
    d = details.merge(items[["id", "replacement_value", "requires_approval"]], left_on="item_id", right_on="id", suffixes=("", "_item"))
    agg = d.groupby("loan_id").agg(n_lines=("item_id", "nunique"), total_qty=("qty", "sum"),
                                   max_value=("replacement_value", "max"), any_approval=("requires_approval", "max"))
    L = loans.merge(agg, left_on="id", right_index=True, how="left").copy()
    for c in ("n_lines", "total_qty", "max_value", "any_approval"):
        L[c] = L[c].fillna(0).astype(float)
    L["days"] = np.ceil((L["due_at"] - L["start_at"]).dt.total_seconds() / 86400).clip(lower=1)
    L["lead_days"] = ((L["start_at"] - L["created_at"]).dt.total_seconds() / 86400).clip(lower=0, upper=30)
    L["due_weekend"] = (L["due_at"].dt.weekday >= 5).astype(float)
    L["start_hour"] = L["start_at"].dt.hour.astype(float)
    L["log_value"] = np.log1p(L["max_value"])
    # borrower history that was known when the request was made (no leakage)
    L["prior_n"] = 0.0
    L["late_rate"] = 0.25
    ret = L[(L["status"] == "returned") & L["returned_at"].notna()]
    for b, grp in L.groupby("borrower_id"):
        r = ret[ret["borrower_id"] == b].sort_values("returned_at")
        if r.empty:
            L.loc[grp.index, "late_rate"] = 0.25
            continue
        times = r["returned_at"].to_numpy(dtype="datetime64[ns]")
        late = np.cumsum((r["late_days"].fillna(0) > 0).to_numpy(float))
        k = np.searchsorted(times, grp["created_at"].to_numpy(dtype="datetime64[ns]"), side="left")
        n = k.astype(float)
        lates = np.where(k > 0, late[np.maximum(k - 1, 0)], 0.0)
        L.loc[grp.index, "prior_n"] = n
        L.loc[grp.index, "late_rate"] = (lates + 0.5) / (n + 2)
    return L


def heuristic(L: pd.DataFrame) -> np.ndarray:
    z = (-2.4 + 3.2 * L["late_rate"] + 0.35 * (L["days"] - 1) + 0.5 * (L["lead_days"] < 1)
         + 0.45 * L["due_weekend"] + 0.3 * (L["n_lines"] > 2))
    return 1 / (1 + np.exp(-z.to_numpy(float)))


def train_and_score(loans, details, items, min_samples=200, min_positives=30):
    L = build_features(loans, details, items)
    train = L[(L["status"] == "returned") & L["returned_at"].notna()].sort_values("created_at")
    y = (train["late_days"].fillna(0) > 0).astype(int)
    active = L[L["status"].isin(ACTIVE)]
    report = {"n_train": int(len(train)), "n_late": int(y.sum()), "model": "heuristic-v1", "auc": None}
    if active.empty:
        return pd.DataFrame(columns=["loan_id", "probability", "model_version"]), report
    if len(train) >= min_samples and y.sum() >= min_positives and (len(y) - y.sum()) >= min_positives:
        cut = int(len(train) * 0.75)
        Xtr, Xte, ytr, yte = train[FEATURES].iloc[:cut], train[FEATURES].iloc[cut:], y.iloc[:cut], y.iloc[cut:]
        cands = {
            "logreg": make_pipeline(StandardScaler(), LogisticRegression(class_weight="balanced", max_iter=500)),
            "gbm": GradientBoostingClassifier(n_estimators=80, max_depth=2, learning_rate=0.08, random_state=0),
        }
        aucs = {}
        for name, m in cands.items():
            m.fit(Xtr, ytr)
            aucs[name] = float(roc_auc_score(yte, m.predict_proba(Xte)[:, 1])) if yte.nunique() == 2 else 0.5
        best = "logreg" if aucs["gbm"] - aucs["logreg"] < 0.01 else "gbm"
        model = cands[best].fit(train[FEATURES], y)
        p = model.predict_proba(active[FEATURES])[:, 1]
        if best == "logreg":  # class_weight='balanced' inflates probabilities; recalibrate to the base rate
            base = float(y.mean())
            odds = p / np.clip(1 - p, 1e-6, None)
            shift = (base / (1 - base)) / 1.0
            odds = odds * shift
            p = odds / (1 + odds)
        report.update({"model": best, "auc": round(aucs[best], 3), "auc_logreg": round(aucs["logreg"], 3), "auc_gbm": round(aucs["gbm"], 3), "base_rate": round(float(y.mean()), 3)})
        version = f"{best}-v1-auc{aucs[best]:.2f}"
    else:
        p = heuristic(active)
        version = "heuristic-v1"
    out = pd.DataFrame({"loan_id": active["id"].to_numpy(), "probability": np.clip(p, 0.001, 0.999), "model_version": version})
    return out, report
