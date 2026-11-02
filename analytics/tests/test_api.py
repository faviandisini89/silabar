from fastapi.testclient import TestClient


def client(monkeypatch, token="rahasia"):
    monkeypatch.setenv("ANALYTICS_TOKEN", token)
    from silabar_analytics.api import app
    return TestClient(app)


def test_health_is_public(db_url, monkeypatch):
    r = client(monkeypatch).get("/health")
    assert r.status_code == 200 and r.json()["status"] == "ok"


def test_run_requires_token(db_url, monkeypatch):
    c = client(monkeypatch)
    assert c.post("/run", json={"tasks": ["assoc"]}).status_code == 401
    r = c.post("/run", json={"tasks": ["assoc"]}, headers={"X-Analytics-Token": "rahasia"})
    assert r.status_code == 200 and r.json()["tasks"]["assoc"]["status"] == "ok"


def test_unknown_task_is_rejected_and_status_reports_last_run(db_url, monkeypatch):
    c = client(monkeypatch)
    h = {"X-Analytics-Token": "rahasia"}
    assert c.post("/run", json={"tasks": ["nope"]}, headers=h).status_code == 422
    c.post("/run", json={"tasks": ["assoc"]}, headers=h)
    assert c.get("/status", headers=h).json()["last_run"]["status"] == "ok"
