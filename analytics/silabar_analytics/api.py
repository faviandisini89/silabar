from __future__ import annotations
import json
from fastapi import Depends, FastAPI, Header, HTTPException
from pydantic import BaseModel
from sqlalchemy import text
from . import __version__, pipeline
from .config import get_config
from .db import get_engine, ping

app = FastAPI(title="SILABAR Analytics", version=__version__)


def auth(x_analytics_token: str | None = Header(default=None)):
    token = get_config().token
    if token and x_analytics_token != token:
        raise HTTPException(status_code=401, detail="Token tidak valid")


class RunRequest(BaseModel):
    tasks: list[str] = ["all"]


@app.get("/health")
def health():
    return {"status": "ok" if ping() else "db_unreachable", "version": __version__}


@app.post("/run", dependencies=[Depends(auth)])
def run(req: RunRequest):
    bad = [t for t in req.tasks if t not in pipeline.ALL_TASKS + ["all"]]
    if bad:
        raise HTTPException(status_code=422, detail=f"Task tidak dikenal: {bad}")
    return pipeline.run(req.tasks)


@app.get("/status", dependencies=[Depends(auth)])
def status():
    with get_engine().connect() as c:
        row = c.execute(text("SELECT payload, generated_at FROM analysis_outputs WHERE kind='run_summary'")).first()
    return {"last_run": json.loads(row[0]) if row else None}
