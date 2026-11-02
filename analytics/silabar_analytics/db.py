from __future__ import annotations
import pandas as pd
from sqlalchemy import create_engine, text
from sqlalchemy.engine import Engine
from .config import get_config

_engines: dict[str, Engine] = {}


def get_engine(url: str | None = None) -> Engine:
    url = url or get_config().database_url
    if url not in _engines:
        _engines[url] = create_engine(url, future=True, pool_pre_ping=True)
    return _engines[url]


def reset_engines() -> None:
    for e in _engines.values():
        e.dispose()
    _engines.clear()


def read(sql: str, params: dict | None = None, eng: Engine | None = None, dates: tuple[str, ...] = ()) -> pd.DataFrame:
    eng = eng or get_engine()
    with eng.connect() as c:
        df = pd.read_sql(text(sql), c, params=params or {})
    for col in dates:
        if col in df.columns:
            df[col] = pd.to_datetime(df[col], errors="coerce")
    return df


def ping(eng: Engine | None = None) -> bool:
    try:
        with (eng or get_engine()).connect() as c:
            c.execute(text("SELECT 1"))
        return True
    except Exception:
        return False
