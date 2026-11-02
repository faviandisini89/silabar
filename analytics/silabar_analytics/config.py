from __future__ import annotations
import os
from dataclasses import dataclass


@dataclass(frozen=True)
class Config:
    database_url: str
    token: str
    horizon_weeks: int
    min_risk_samples: int
    min_risk_positives: int


def get_config() -> Config:
    return Config(
        database_url=os.getenv("DATABASE_URL", "sqlite:///../database/database.sqlite"),
        token=os.getenv("ANALYTICS_TOKEN", ""),
        horizon_weeks=int(os.getenv("FORECAST_HORIZON_WEEKS", "4")),
        min_risk_samples=int(os.getenv("RISK_MIN_SAMPLES", "200")),
        min_risk_positives=int(os.getenv("RISK_MIN_POSITIVES", "30")),
    )


DEFAULT_SETTINGS = {
    "procure.min_unmet": 5,
    "procure.min_users": 3,
    "procure.fill_below": 80,
    "procure.window_days": 30,
    "procure.maint_share": 50,
    "procure.util_high": 90,
    "procure.w_unmet": 0.35,
    "procure.w_users": 0.25,
    "procure.w_util": 0.25,
    "procure.w_trend": 0.15,
}
