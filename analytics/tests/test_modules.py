import numpy as np
import pandas as pd
from silabar_analytics import assoc, forecast, risk


def test_apriori_rules_find_obvious_bundle():
    bs = [frozenset({1, 2, 3})] * 12 + [frozenset({1})] * 10 + [frozenset({4})] * 20 + [frozenset({2, 3})] * 3
    rules = assoc.rules(bs, min_count=8)
    pairs = {(tuple(r["antecedent"]), r["consequent"]) for r in rules}
    assert ((1,), 2) in pairs or ((1,), 3) in pairs
    top = rules[0]
    assert top["lift"] > 1.2 and 0 < top["support"] <= 1 and top["confidence"] <= 1


def test_apriori_respects_min_count():
    bs = [frozenset({1, 2})] * 3 + [frozenset({3})] * 20
    assert assoc.rules(bs, min_count=8) == []


def _series(n=70, seed=1):
    rng = np.random.default_rng(seed)
    idx = pd.date_range("2025-01-06", periods=n, freq="7D")
    base = 6 + 3 * np.sin(np.arange(n) * 2 * np.pi / 26)
    return pd.Series(np.maximum(0, rng.poisson(base)).astype(float), index=idx)


def test_forecast_intervals_are_ordered_and_non_negative():
    res, info = forecast.forecast_item(_series(), 4)
    assert len(res) == 4 and info["model"] in ("moving_avg", "gbr_lag")
    for r in res:
        assert 0 <= r["lower"] <= r["predicted"] <= r["upper"]
    assert res[0]["forecast_date"] > _series().index[-1]


def test_forecast_short_history_uses_baseline():
    res, info = forecast.forecast_item(_series(12), 4)
    assert info["model"] == "moving_avg" and all(r["model"] == "moving_avg" for r in res)


def test_forecast_tracks_a_clear_level_shift():
    s = pd.Series([2.0] * 30 + [9.0] * 10, index=pd.date_range("2025-03-03", periods=40, freq="7D"))
    res, _ = forecast.forecast_item(s, 4)
    assert res[0]["predicted"] > 5


def test_risk_features_have_no_future_leakage(db_url):
    from silabar_analytics import repository as repo
    L = risk.build_features(repo.loans(), repo.details(), repo.items())
    first = L.sort_values("created_at").iloc[0]
    assert first["prior_n"] == 0 and abs(first["late_rate"] - 0.25) < 1e-9


def test_risk_model_learns_signal(db_url):
    from silabar_analytics import repository as repo
    scores, rep = risk.train_and_score(repo.loans(), repo.details(), repo.items(), 200, 30)
    assert rep["model"] in ("logreg", "gbm") and rep["auc"] > 0.55
    assert scores["probability"].between(0, 1).all() and len(scores) > 0
    assert scores["model_version"].iloc[0].startswith(rep["model"])


def test_risk_falls_back_to_heuristic_when_data_is_thin(db_url):
    from silabar_analytics import repository as repo
    scores, rep = risk.train_and_score(repo.loans(), repo.details(), repo.items(), 10 ** 6, 10 ** 6)
    assert rep["model"] == "heuristic-v1" and set(scores["model_version"]) == {"heuristic-v1"}
