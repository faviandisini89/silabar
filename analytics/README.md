# SILABAR Analytics

Layanan Python (FastAPI) yang membaca database SILABAR, menghitung analisis machine learning, dan menulis hasilnya kembali. Penjelasan metode: [`../docs/ML.md`](../docs/ML.md).

```bash
pip install -r requirements.txt
TZ=Asia/Jakarta DATABASE_URL=sqlite:///../database/database.sqlite ANALYTICS_TOKEN=rahasia \
  uvicorn silabar_analytics.api:app --port 8001
```

| Endpoint | Fungsi |
|---|---|
| `GET /health` | status layanan dan koneksi database (tanpa token) |
| `POST /run` `{"tasks": ["all"]}` | jalankan analisis; tugas: `forecast`, `risk`, `assoc`, `anomaly`, `procurement` |
| `GET /status` | ringkasan proses terakhir |

`/run` dan `/status` butuh header `X-Analytics-Token`. CLI: `python -m silabar_analytics.cli run --tasks forecast,risk`.

`DATABASE_URL` memakai format SQLAlchemy: `sqlite:////path/db.sqlite` atau `mysql+pymysql://user:pass@host/silabar`. Variabel lain: `FORECAST_HORIZON_WEEKS` (4), `RISK_MIN_SAMPLES` (200), `RISK_MIN_POSITIVES` (30).

Tes: `pip install -r requirements-dev.txt && pytest`. Tes memakai database sintetis 18 bulan (`tests/synth.py`) yang sengaja memuat empat penyebab kehabisan berbeda, lonjakan permintaan, dan unit yang berulang rusak.
