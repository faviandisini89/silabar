<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>SILABAR — Sistem Informasi Peminjaman Inventaris Kampus</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cpath d='M4 9l12-6 12 6v14l-12 6-12-6z' fill='%23F2B544'/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/silabar.css') }}">
    <script>try{var t=localStorage.getItem('silabar-theme');if(t)document.documentElement.dataset.theme=t;}catch(e){}</script>
</head>
<body class="landing-shell">

    <!-- Navbar -->
    <header class="landing-navbar">
        <div class="landing-nav-inner">
            <a href="{{ route('root') }}" class="landing-brand">
                {!! \App\Support\Icon::logo(32) !!}
                <span>SILABAR</span>
            </a>

            <nav class="landing-menu">
                <a href="#katalog">Katalog Barang</a>
                <a href="#alur">Alur Peminjaman</a>
                <a href="#tentang">Tentang Sistem</a>
            </nav>

            <div class="landing-nav-actions">
                <button class="ibtn" data-theme-toggle aria-label="Ganti tema">{!! \App\Support\Icon::svg('moon') !!}</button>
                <a href="{{ route('login') }}" class="btn ghost">Masuk</a>
                <a href="{{ route('register') }}" class="btn pri">Daftar Peminjam</a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="landing-hero">
        <div class="landing-badge">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            Sistem Inventaris & Peminjaman Kampus Terpadu
        </div>

        <h1>Pinjam Barang Kampus <span>Tanpa Antre</span> Buku Catatan</h1>

        <p>
            Cek ketersediaan unit secara realtime, ajukan peminjaman secara mandiri, ambil barang dengan tiket QR Code resmi di loket, dan pantau status pengembalianmu dengan transparan.
        </p>

        <div class="landing-cta-group">
            <a href="#katalog" class="btn pri" style="padding: 12px 24px; font-size: 15px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                Lihat Barang Tersedia
            </a>
            <a href="{{ route('login') }}" class="btn" style="padding: 12px 24px; font-size: 15px;">
                Masuk ke Akun
            </a>
        </div>
    </section>

    <!-- Stats Highlight Section -->
    <section class="landing-stats-wrap">
        <div class="landing-stats-grid">
            <div class="landing-stat-item">
                <b>{{ $stat['total_items'] }}</b>
                <span>Jenis Barang Terdaftar</span>
            </div>
            <div class="landing-stat-item">
                <b>{{ $stat['ready_units'] }}</b>
                <span>Unit Siap Pakai Hari Ini</span>
            </div>
            <div class="landing-stat-item">
                <b>{{ $stat['running_loans'] }}</b>
                <span>Peminjaman Berjalan</span>
            </div>
            <div class="landing-stat-item">
                <b>{{ $stat['returned_loans'] }}</b>
                <span>Peminjaman Sukses Kembali</span>
            </div>
        </div>
    </section>

    <!-- Section: Katalog Barang Tersedia -->
    <section id="katalog" class="landing-section">
        <div class="section-head">
            <span class="section-tag">Katalog Inventaris Kampus</span>
            <h2>Daftar Barang & Ketersediaan Stok</h2>
            <p>Pilih peralatan praktikum, audio-visual, perangkat keras, atau perlengkapan acara yang siap dipinjam.</p>
        </div>

        <!-- Filter Kategori -->
        @if ($categories->isNotEmpty())
            <div class="category-filter-bar">
                <a href="{{ route('root') }}#katalog" class="filter-pill {{ empty($categoryFilter) ? 'active' : '' }}">
                    Semua Kategori ({{ $categories->sum('items_count') }})
                </a>
                @foreach ($categories as $cat)
                    <a href="{{ route('root', ['kategori' => $cat->slug ?? $cat->name]) }}#katalog" class="filter-pill {{ $categoryFilter === ($cat->slug ?? $cat->name) ? 'active' : '' }}">
                        {{ $cat->name }} ({{ $cat->items_count }})
                    </a>
                @endforeach
            </div>
        @endif

        <!-- Grid Barang -->
        @if ($items->isEmpty())
            <div class="panel" style="text-align: center; padding: 48px 24px; border-radius: 30px;">
                <p style="color: var(--muted); font-size: 15px;">Belum ada data barang pada kategori ini.</p>
            </div>
        @else
            <div class="landing-items-grid">
                @foreach ($items as $item)
                    <div class="item-card">
                        <div>
                            <div class="item-card-top">
                                <span class="item-category-tag">{{ $item['category'] }}</span>
                                @if ($item['is_ready'])
                                    <span class="status-badge available">
                                        <span style="display:inline-block; width:7px; height:7px; background:var(--teal); border-radius:50%;"></span>
                                        Siap Pakai
                                    </span>
                                @else
                                    <span class="status-badge busy">
                                        <span style="display:inline-block; width:7px; height:7px; background:var(--sun); border-radius:50%;"></span>
                                        Stok Menipis
                                    </span>
                                @endif
                            </div>

                            <h3 class="item-title">{{ $item['name'] }}</h3>
                            <p class="item-desc">{{ $item['description'] ?: 'Peralatan resmi sarpras kampus untuk kebutuhan belajar dan kegiatan mahasiswa.' }}</p>

                            <div class="item-meta-info">
                                <div class="meta-block">
                                    <span class="meta-label">Siap Sekarang</span>
                                    <span class="meta-val highlight">{{ $item['avail_now'] }} unit</span>
                                </div>
                                <div class="meta-block">
                                    <span class="meta-label">Tersedia Besok</span>
                                    <span class="meta-val">{{ $item['avail_tomorrow'] }} unit</span>
                                </div>
                                <div class="meta-block">
                                    <span class="meta-label">Total Unit</span>
                                    <span class="meta-val">{{ $item['total_stock'] }}</span>
                                </div>
                                <div class="meta-block">
                                    <span class="meta-label">Lokasi</span>
                                    <span class="meta-val">{{ $item['location'] }}</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; font-size: 12px; color: var(--muted);">
                                <span>Durasi Maks: <b>{{ $item['max_loan_days'] }} Hari</b></span>
                                <span>{{ $item['requires_approval'] ? '⚠️ Perlu Verifikasi' : '⚡ Langsung Approve' }}</span>
                            </div>
                            <a href="{{ route('login') }}" class="btn pri btn-block" style="padding: 10px 16px; font-size: 13.5px;">
                                Ajukan Pinjaman
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <!-- Section: Alur Peminjaman -->
    <section id="alur" class="landing-section">
        <div class="section-head">
            <span class="section-tag">Mudah & Cepat</span>
            <h2>Alur Peminjaman 4 Langkah</h2>
            <p>Proses peminjaman barang inventaris kampus dilakukan secara terstruktur dan transparan.</p>
        </div>

        <div class="steps-grid">
            <div class="step-card">
                <div class="step-num">1</div>
                <h3>Pilih Barang</h3>
                <p>Cari barang yang kamu butuhkan di katalog, periksa ketersediaan stok pada tanggal kegiatanmu.</p>
            </div>
            <div class="step-card">
                <div class="step-num">2</div>
                <h3>Ajukan Peminjaman</h3>
                <p>Masukkan keperluan, pilih jadwal mulai dan pengembalian, lalu kirim permohonan melalui sistem.</p>
            </div>
            <div class="step-card">
                <div class="step-num">3</div>
                <h3>Tunjukkan Tiket QR</h3>
                <p>Setelah disetujui, datang ke loket sarana prasarana dan tunjukkan kode QR untuk serah terima unit.</p>
            </div>
            <div class="step-card">
                <div class="step-num">4</div>
                <h3>Kembalikan Tepat Waktu</h3>
                <p>Petugas akan memverifikasi kondisi barang saat pengembalian dan transaksi otomatis selesai.</p>
            </div>
        </div>
    </section>

    <!-- Bottom CTA Banner -->
    <section id="tentang" class="landing-stats-wrap">
        <div class="landing-banner">
            <h2>Mulai Kelola Peminjaman dengan Lebih Praktis</h2>
            <p>Tinggalkan pencatatan manual yang rentan hilang dan jadwal yang bertabrakan. Gunakan akun SILABAR untuk semua kegiatan akademikmu.</p>
            <div class="landing-cta-group">
                <a href="{{ route('register') }}" class="btn" style="background:#fff; color:var(--ink); font-weight:700; padding:12px 28px;">
                    Daftar Akun Peminjam
                </a>
                <a href="{{ route('login') }}" class="btn ghost" style="color:#fff; border-color:rgba(255,255,255,0.4); padding:12px 24px;">
                    Sudah Punya Akun? Masuk
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="footer-inner">
            <div style="display:flex; align-items:center; gap:10px;">
                {!! \App\Support\Icon::logo(24) !!}
                <b style="font-family:'Bricolage Grotesque', sans-serif; font-size:16px;">SILABAR</b>
            </div>
            <p>© {{ date('Y') }} SILABAR — Sistem Informasi Pelayanan Peminjaman Barang Kampus.</p>
        </div>
    </footer>

    <script src="{{ asset('js/silabar.js') }}"></script>
</body>
</html>
