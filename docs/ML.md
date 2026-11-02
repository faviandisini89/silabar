# Rincian analitik dan machine learning

Kode: `analytics/silabar_analytics/`. Titik masuk: `pipeline.run()`, dipanggil lewat API (`POST /run`) atau CLI (`python -m silabar_analytics.cli run`). Setiap tugas berjalan terpisah; satu yang gagal tidak menghentikan yang lain, dan ringkasan tiap proses disimpan di `analysis_outputs` (`run_summary`).

## 1. Perkiraan permintaan (`forecast.py`)

Deret: jumlah tiket per pekan yang memuat barang itu, hanya pekan lengkap.

- **Pembanding:** rata-rata 4 pekan terakhir dengan tren teredam. Rentang dari simpangan baku 8 pekan.
- **Model ML:** `GradientBoostingRegressor` dengan fitur lag 1–4, rata-rata 4 dan 8 pekan, serta sin/cos pekan dalam tahun. Rentang 80% dari dua model kuantil (10% dan 90%), diperlebar bertahap untuk langkah yang lebih jauh.
- **Pemilihan:** backtest rolling-origin satu langkah pada 8 titik terakhir. Model ML dipakai hanya bila MAE-nya paling sedikit 5% lebih baik dari pembanding. Riwayat kurang dari 20 pekan selalu memakai pembanding.
- Nama model terpilih disimpan per baris (`model_name`) dan ditampilkan di dasbor.

## 2. Risiko telat (`risk.py`)

Label: tiket yang sudah kembali dengan `late_days > 0`.

Fitur (semua diketahui saat pengajuan):

| Fitur | Arti |
|---|---|
| `late_rate`, `prior_n` | proporsi telat peminjam (dengan smoothing) dan jumlah pinjaman selesainya, **hanya dari tiket yang sudah kembali sebelum pengajuan ini dibuat** |
| `days`, `lead_days` | durasi pinjam dan jeda antara pengajuan dan pemakaian |
| `due_weekend`, `start_hour` | jatuh tempo di akhir pekan, jam mulai |
| `n_lines`, `total_qty`, `log_value`, `any_approval` | ukuran dan nilai paket barang |

Evaluasi memisahkan 25% tiket **terbaru** sebagai data uji (bukan acak, supaya tidak ada kebocoran waktu). Regresi logistik (dengan penskalaan) dan Gradient Boosting dibandingkan lewat AUC; regresi logistik dipilih bila selisihnya di bawah 0,01 karena lebih sederhana dan lebih mudah dijelaskan. Probabilitas regresi logistik dikalibrasi ulang ke laju dasar karena memakai `class_weight='balanced'`.

Data minimal: 200 tiket selesai dengan sedikitnya 30 telat dan 30 tepat waktu (bisa diubah lewat `RISK_MIN_SAMPLES` dan `RISK_MIN_POSITIVES`). Di bawah itu dipakai aturan heuristik `heuristic-v1`. Versi model tertulis di tiap skor (`model_version`), mis. `logreg-v1-auc0.78`.

## 3. Barang dipinjam bersamaan (`assoc.py`)

Apriori level-demi-level sampai 3 butir. Aturan disimpan bila jumlah kemunculan ≥ 8, keyakinan ≥ 0,3, dan lift ≥ 1,2; diurutkan dengan lift × log(jumlah).

## 4. Anomali (`anomaly.py`)

Untuk tiap barang, tiap pekan dihitung z-score robust (median dan MAD dari 8 pekan sebelumnya). `IsolationForest` memberi skor keanehan pada seluruh pasangan barang-pekan; pekan terakhir ditandai bila skornya termasuk 6% teratas, |z| ≥ 1,5, dan volumenya berarti. Ditambah unit yang masuk perawatan 2 kali atau lebih dalam 120 hari.

## 5. Rekomendasi pengadaan (`procurement.py`, `writer.py`)

Untuk tiap barang dengan permintaan gagal dalam jendela analisis:

1. **Pemicu:** ≥ `procure.min_unmet` gagal dari ≥ `procure.min_users` peminjam berbeda, atau fill rate < `procure.fill_below`%.
2. **Diagnosis** (urutan prioritas):
   - `maintenance`: rata-rata bagian unit rusak saat gagal ≥ `procure.maint_share`%
   - `late_returns`: ≥ 40% kegagalan terjadi saat ada unit telat
   - `peak_scheduling`: ≥ 55% kegagalan di satu hari-jam sementara utilisasi rata-rata < 50%
   - selain itu `stock_shortage`
3. **Jumlah tambahan** (hanya `stock_shortage`): `max(1, ceil(P90(permintaan serentak harian) × penguat − stok layak pakai))`. Penguat = perbandingan batas atas forecast dengan rata-rata 4 pekan terakhir (dipakai bila naik lebih dari 25%, maksimum 1,5). Penyebab lain selalu 0 unit: solusinya perbaikan atau aturan, bukan membeli.
4. **Skor prioritas:** `w_unmet × min(1, gagal/15) + w_users × min(1, peminjam/10) + w_util × utilisasi + w_trend × tren`, dengan bobot dari Pengaturan.
5. **Siklus hidup:** rekomendasi `open` diperbarui tiap analisis; yang sudah diputuskan tidak disentuh; rekomendasi baru untuk barang yang baru diputuskan dalam 30 hari tidak dibuat ulang. Setelah stok ditambah, fill rate sebelum dan sesudah dibandingkan di halaman Pengadaan.

## Catatan data

- Semua waktu disimpan sebagai waktu lokal aplikasi. Jalankan layanan Python dengan `TZ` yang sama dengan `APP_TIMEZONE`.
- Tabel `unmet_demand_logs` menyimpan konteks saat gagal (`util`, `maint_share`, `overdue_units`); diagnosis bergantung padanya, jadi jangan dihapus.
