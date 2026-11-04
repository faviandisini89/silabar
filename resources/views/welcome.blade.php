<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>SILABAR — Sistem Informasi Peminjaman Inventaris Kampus</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cpath d='M4 9l12-6 12 6v14l-12 6-12-6z' fill='%23F2B544'/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/silabar.css') }}">
    <script>try{var t=localStorage.getItem('silabar-theme');if(t)document.documentElement.dataset.theme=t;}catch(e){}</script>
</head>
<body class="landing-shell">

    <!-- Bar Pengumuman Operasional -->
    <div class="landing-top-notice">
        <div class="container-inner" style="display:flex; align-items:center; justify-content:space-between; gap:12px; font-size:12.5px; padding:8px 24px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <span class="pulse-dot"></span>
                <span><b>Loket Pelayanan Sarpras:</b> Gedung Fasilitas Bersama Lt. 1 | Buka: Senin – Jumat (08.00 – 16.00 WIB)</span>
            </div>
            <div style="color:var(--muted); font-size:12px;">
                Layanan Terpadu Sarana & Prasarana
            </div>
        </div>
    </div>

    <!-- Navigasi Utama -->
    <header class="landing-navbar">
        <div class="landing-nav-inner">
            <a href="{{ route('root') }}" class="landing-brand">
                {!! \App\Support\Icon::logo(32) !!}
                <div style="display:flex; flex-direction:column; line-height:1.2;">
                    <span>SILABAR</span>
                    <span style="font-size:10.5px; font-weight:600; color:var(--muted); letter-spacing:0.02em; font-family:'Plus Jakarta Sans', sans-serif;">Inventaris Sarpras Kampus</span>
                </div>
            </a>

            <nav class="landing-menu">
                <a href="#katalog">Katalog Barang</a>
                <a href="#prosedur">Ketentuan Peminjaman</a>
                <a href="#lokasi">Informasi Loket</a>
            </nav>

            <div class="landing-nav-actions">
                <button class="ibtn" data-theme-toggle aria-label="Ganti tema">{!! \App\Support\Icon::svg('moon') !!}</button>
                <a href="{{ route('login') }}" class="btn ghost">Masuk Akun</a>
                <a href="{{ route('register') }}" class="btn pri">Daftar Peminjam</a>
            </div>
        </div>
    </header>

    <!-- Header / Pencarian Hero -->
    <section class="landing-hero-institutional">
        <div class="container-inner">
            <div class="hero-badge-tag">Portal Layanan Inventaris Akademik</div>
            <h1>Peminjaman Peralatan & Sarana Kampus</h1>
            <p>
                Akses katalog barang praktikum, audio-visual, perangkat keras, dan perlengkapan kegiatan mahasiswa secara transparan dengan ketersediaan stok fisik yang diperbarui setiap saat.
            </p>

            <!-- Search Bar Fungsional -->
            <form action="{{ route('root') }}#katalog" method="GET" class="hero-search-box">
                <div class="search-input-wrap">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--muted); flex-shrink:0;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Cari nama barang, kode SKU, atau proyektor, kamera, mikrofon..." class="search-field">
                </div>
                @if (!empty($categoryFilter))
                    <input type="hidden" name="kategori" value="{{ $categoryFilter }}">
                @endif
                <button type="submit" class="btn pri" style="padding:10px 22px; font-weight:700;">Cari Barang</button>
            </form>
        </div>
    </section>

    <!-- Ringkasan Data Operasional -->
    <section class="landing-stats-wrap">
        <div class="landing-stats-grid">
            <div class="landing-stat-item">
                <b>{{ $stat['total_items'] }}</b>
                <span>Jenis Barang Terdaftar</span>
            </div>
            <div class="landing-stat-item">
                <b>{{ $stat['ready_units'] }}</b>
                <span>Unit Siap Dipinjam Hari Ini</span>
            </div>
            <div class="landing-stat-item">
                <b>{{ $stat['running_loans'] }}</b>
                <span>Peminjaman Aktif</span>
            </div>
            <div class="landing-stat-item">
                <b>{{ $stat['returned_loans'] }}</b>
                <span>Transaksi Sukses Selesai</span>
            </div>
        </div>
    </section>

    <!-- Section Katalog Barang -->
    <section id="katalog" class="landing-section">
        <div class="section-head" style="text-align:left; max-width:none; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:16px;">
            <div>
                <span class="section-tag">Katalog Peralatan Kampus</span>
                <h2>Daftar Barang & Ketersediaan Stok</h2>
                <p>Status ketersediaan unit dihitung langsung dari jadwal reservasi dan unit fisik yang sedang siap pakai.</p>
            </div>
            @if (!empty($search) || !empty($categoryFilter))
                <a href="{{ route('root') }}#katalog" class="btn sm ghost" style="color:var(--muted);">
                    ✕ Reset Pencarian & Filter
                </a>
            @endif
        </div>

        <!-- Filter Kategori Tabs -->
        @if ($categories->isNotEmpty())
            <div class="category-filter-bar" style="justify-content:flex-start;">
                <a href="{{ route('root', array_filter(['q' => $search])) }}#katalog" class="filter-pill {{ empty($categoryFilter) ? 'active' : '' }}">
                    Semua Kategori ({{ $categories->sum('items_count') }})
                </a>
                @foreach ($categories as $cat)
                    <a href="{{ route('root', array_filter(['kategori' => $cat->slug ?? $cat->name, 'q' => $search])) }}#katalog" class="filter-pill {{ $categoryFilter === ($cat->slug ?? $cat->name) ? 'active' : '' }}">
                        {{ $cat->name }} ({{ $cat->items_count }})
                    </a>
                @endforeach
            </div>
        @endif

        <!-- Grid Barang -->
        @if ($items->isEmpty())
            <div class="panel" style="text-align:center; padding:54px 20px; border-radius:28px;">
                <p style="font-size:16px; font-weight:700; color:var(--ink); margin-bottom:6px;">Tidak ada barang yang cocok</p>
                <p style="font-size:13.5px; color:var(--muted);">Coba ubah kata kunci pencarian atau pilih kategori lain.</p>
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
                                        <span class="dot-status on"></span>
                                        Tersedia ({{ $item['avail_now'] }})
                                    </span>
                                @else
                                    <span class="status-badge busy">
                                        <span class="dot-status off"></span>
                                        Stok Kosong
                                    </span>
                                @endif
                            </div>

                            <div style="font-size:11.5px; font-family:monospace; color:var(--muted); margin-bottom:4px;">SKU: {{ $item['sku'] }}</div>
                            <h3 class="item-title">{{ $item['name'] }}</h3>
                            <p class="item-desc">{{ $item['description'] ?: 'Peralatan resmi sarpras kampus untuk kebutuhan kegiatan dan praktikum.' }}</p>

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
                                    <span class="meta-val">{{ $item['total_stock'] }} unit</span>
                                </div>
                                <div class="meta-block">
                                    <span class="meta-label">Ruang Simpan</span>
                                    <span class="meta-val">{{ $item['location'] }}</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center; font-size:12px; color:var(--muted); margin-bottom:12px; padding:0 2px;">
                                <span>Maks. Pinjam: <b>{{ $item['max_loan_days'] }} Hari</b></span>
                                <span>{{ $item['requires_approval'] ? 'Verifikasi Petugas' : 'Persetujuan Cepat' }}</span>
                            </div>
                            <a href="{{ route('login') }}" class="btn pri btn-block" style="padding:10px 16px; font-size:13.5px; text-align:center;">
                                Ajukan Peminjaman
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <!-- Ketentuan & Prosedur Peminjaman (SOP Resmi) -->
    <section id="prosedur" class="landing-section" style="border-top:1px solid var(--line);">
        <div class="section-head" style="text-align:left; max-width:none; margin-bottom:32px;">
            <span class="section-tag">Tata Tertib & Regulasi</span>
            <h2>Prosedur & Ketentuan Peminjaman</h2>
            <p>Patuhi ketentuan berikut demi kelancaran inventarisasi dan pemerataan pemanfaatan fasilitas kampus.</p>
        </div>

        <div class="sop-grid">
            <div class="sop-card">
                <div class="sop-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <h4>1. Hak & Identitas Peminjam</h4>
                <p>Peminjaman hanya dapat diajukan oleh mahasiswa, dosen, atau tenaga kependidikan aktif. Akun harus bebas dari tanggungan denda keterlambatan atau unit rusak sebelumnya.</p>
            </div>

            <div class="sop-card">
                <div class="sop-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                </div>
                <h4>2. Batas Jadwal & Reservasi</h4>
                <p>Pengajuan dilakukan melalui sistem dengan memilih rentang tanggal pemakaian. Untuk barang praktikum berstatus verifikasi, petugas loket akan meninjau keperluan akademik Anda.</p>
            </div>

            <div class="sop-card">
                <div class="sop-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/></svg>
                </div>
                <h4>3. Verifikasi Tiket QR di Loket</h4>
                <p>Saat pengambilan barang, tunjukkan tiket digital QR Code dari ponsel Anda ke petugas loket sarpras. Petugas akan memindai barcode unit fisik dan mencatat serah terima.</p>
            </div>

            <div class="sop-card">
                <div class="sop-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <h4>4. Pengembalian & Kondisi Fisik</h4>
                <p>Barang wajib dikembalikan sesuai tenggat waktu yang ditentukan. Kerusakan atau kehilangan komponen akan dikenakan biaya perbaikan atau penggantian sesuai tarif inventaris.</p>
            </div>
        </div>
    </section>

    <!-- Informasi Loket & Bantuan -->
    <section id="lokasi" class="landing-section" style="border-top:1px solid var(--line); padding-bottom:70px;">
        <div class="panel" style="padding:36px; border-radius:30px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:24px;">
            <div style="max-width:600px;">
                <h3 style="font-size:20px; font-weight:700; color:var(--ink); margin-bottom:8px;">Butuh Bantuan atau Pengajuan Khusus?</h3>
                <p style="font-size:14px; color:var(--muted); line-height:1.6; margin:0;">
                    Untuk peminjaman dalam jumlah besar atau keperluan acara resmi fakultas/universitas, silakan berkoordinasi langsung dengan bagian Tata Usaha dan Pengelolaan Sarana Prasarana.
                </p>
            </div>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <a href="{{ route('login') }}" class="btn pri" style="padding:11px 24px;">Masuk ke Portal</a>
                <a href="{{ route('register') }}" class="btn" style="padding:11px 24px;">Registrasi Akun</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="footer-inner">
            <div style="display:flex; align-items:center; gap:10px;">
                {!! \App\Support\Icon::logo(22) !!}
                <b style="font-family:'Plus Jakarta Sans', sans-serif; font-size:15px; color:var(--ink);">SILABAR</b>
                <span style="font-size:12px; color:var(--muted);">| Sistem Informasi Layanan Peminjaman Barang</span>
            </div>
            <p>© {{ date('Y') }} Bagian Sarana & Prasarana Kampus. Seluruh hak cipta dilindungi.</p>
        </div>
    </footer>

    <script src="{{ asset('js/silabar.js') }}"></script>
</body>
</html>
