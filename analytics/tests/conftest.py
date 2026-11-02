import os
import sys
from pathlib import Path
import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
sys.path.insert(0, str(Path(__file__).resolve().parent))
import synth  # noqa: E402


@pytest.fixture(scope="session")
def now():
    import pandas as pd
    return pd.Timestamp("2026-10-13 10:00:00")


@pytest.fixture()
def db_url(tmp_path, now, monkeypatch):
    path = tmp_path / "t.sqlite"
    synth.build(str(path), now=now)
    url = f"sqlite:///{path}"
    monkeypatch.setenv("DATABASE_URL", url)
    from silabar_analytics import db
    db.reset_engines()
    yield url
    db.reset_engines()
