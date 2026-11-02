"""Read-side access: every query the analytics tasks need, returned as DataFrames."""
from __future__ import annotations
import json
import pandas as pd
from .config import DEFAULT_SETTINGS
from .db import read

COUNTED = ("borrowed", "overdue", "returned")


def items(eng=None) -> pd.DataFrame:
    return read("SELECT * FROM items", eng=eng)


def units(eng=None) -> pd.DataFrame:
    return read("SELECT * FROM item_units", eng=eng, dates=("acquired_at",))


def loans(eng=None) -> pd.DataFrame:
    return read("SELECT * FROM loans", eng=eng,
                dates=("start_at", "due_at", "approved_at", "handed_over_at", "returned_at", "created_at"))


def details(eng=None) -> pd.DataFrame:
    return read("SELECT * FROM loan_details", eng=eng)


def unmet(eng=None) -> pd.DataFrame:
    return read("SELECT * FROM unmet_demand_logs", eng=eng,
                dates=("requested_start_at", "requested_end_at", "created_at"))


def maintenance(eng=None) -> pd.DataFrame:
    return read("SELECT * FROM maintenance_logs", eng=eng, dates=("started_at", "finished_at"))


def forecasts(eng=None) -> pd.DataFrame:
    return read("SELECT * FROM demand_forecasts", eng=eng, dates=("forecast_date", "generated_at"))


def recommendations(eng=None) -> pd.DataFrame:
    return read("SELECT * FROM procurement_recommendations", eng=eng, dates=("reviewed_at", "generated_at", "fulfilled_at"))


def settings(eng=None) -> dict:
    out = dict(DEFAULT_SETTINGS)
    df = read("SELECT name, value FROM settings", eng=eng)
    for _, r in df.iterrows():
        k = r["name"]
        if k in out:
            try:
                v = json.loads(r["value"]) if isinstance(r["value"], str) else r["value"]
                out[k] = float(v) if isinstance(DEFAULT_SETTINGS[k], float) else int(float(v))
            except (ValueError, TypeError):
                pass
    return out


def lines(eng=None) -> pd.DataFrame:
    """Loan lines merged with their loan header (one row per loan_detail)."""
    d, l = details(eng), loans(eng)
    return d.merge(l, left_on="loan_id", right_on="id", suffixes=("", "_loan"))
