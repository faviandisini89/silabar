@extends('layouts.app')
@section('title', 'Beranda')
@section('subtitle', 'Ringkasan pinjamanmu')
@php
    use App\Support\{Charts, Fmt};
    use App\Services\StatsService;
    $h = now()->hour;
    $greet = $h < 11 ? 'Selamat pagi' : ($h < 15 ? 'Selamat siang' : ($h < 18 ? 'Selamat sore' : 'Selamat malam'));
    $unitWord = ['harian' => 'hari', 'mingguan' => 'pekan', 'bulanan' => 'bulan', 'tahunan' => 'tahun'][$period];
@endphp
@section('content')
<div class="g">
    <section class="hero c7" style="flex-direction:row;gap:18px;align-items:center;flex-wrap:wrap;display:flex">
        <div style="flex:1;min-width:240px;position:relative;z-index:1">
            <h2>{{ $greet }}, {{ $user->firstName() }}.</h2>
            <p style="margin:8px 0 14px;max-width:46ch">
                @if ($next)
                    Pinjaman <b>{{ $next->details->first()->item->name }}</b>@if ($next->details->count() > 1) dan {{ $next->details->count() - 1 }} barang lain{!! '' !!}@endif:
                    <b>{{ Fmt::until($next->due_at) }}</b>.
                @elseif ($active->count())
                    Kamu punya {{ $active->count() }} tiket yang sedang berjalan.
                @else
                    Belum ada tiket aktif. Cari barang yang kamu butuhkan di katalog.
                @endif
            </p>
            <div class="row wrap"><a class="btn sun" href="{{ route('catalog') }}">{!! \App\Support\Icon::svg('plus', 16) !!}Ajukan peminjaman</a><a class="btn" href="{{ route('loans.index') }}">Lihat tiket</a></div>
        </div>
        <div class="row" style="gap:12px;position:relative;z-index:1">
            {!! Charts::gauge($user->trust_score, 96) !!}
            <div style="max-width:150px"><div class="b">Trust score</div><div class="xs muted">Naik kalau kamu mengembalikan tepat waktu. @if (app(\App\Services\SettingsService::class)->bool('loan.auto_approve')) Di atas {{ app(\App\Services\SettingsService::class)->int('loan.auto_approve_trust') }}, barang berisiko rendah disetujui otomatis.@endif</div></div>
        </div>
    </section>
    <section class="c5" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="panel kpi"><span class="l">Total peminjaman</span><span class="v" data-count="{{ $kpi['total'] }}">0</span></div>
        <div class="panel kpi"><span class="l">Tepat waktu</span><span class="v" data-count="{{ round($kpi['on_time'] * 100) }}" data-fmt="pct">0</span></div>
        <div class="panel kpi warn"><span class="l">Tiket aktif</span><span class="v" data-count="{{ $active->count() }}">0</span></div>
        <div class="panel kpi {{ $kpi['unpaid'] > 0 ? 'bad' : '' }}"><span class="l">Denda berjalan</span><span class="v" style="font-size:{{ $kpi['unpaid'] > 0 ? 24 : 34 }}px">{{ $kpi['unpaid'] > 0 ? Fmt::rp($kpi['unpaid']) : 'Nol' }}</span></div>
    </section>

    <section class="panel c7">
        <div class="ph"><div><h3>Tiket aktif</h3>@if ($active->count())<small>Tunjukkan QR ke petugas saat mengambil dan mengembalikan</small>@endif</div><span class="sp"></span>@if ($active->count() > 3)<a class="btn sm" href="{{ route('loans.index') }}">Semua</a>@endif</div>
        <div class="stack grow">
            @forelse ($active->take(3) as $l)<x-ticket :loan="$l" />
            @empty
                <div class="empty">{!! \App\Support\Icon::svg('ticket', 32) !!}<b>Belum ada tiket</b>Pilih barang di katalog, tentukan jadwal, lalu ajukan.<a class="btn pri" href="{{ route('catalog') }}">Buka katalog</a></div>
            @endforelse
        </div>
    </section>

    <section class="panel c5">
        <div class="ph"><div><h3>Statistik peminjamanmu</h3><small>Jumlah peminjaman per periode</small></div><span class="sp"></span>
            <div class="seg">@foreach (StatsService::PERIODS as $k => $label)<a class="{{ $period === $k ? 'on' : '' }}" href="{{ route('home', ['p' => $k]) }}" style="padding:5px 11px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;color:{{ $period === $k ? 'var(--on-pri)' : 'var(--muted)' }};{{ $period === $k ? 'background:var(--pri)' : '' }}">{{ $label }}</a>@endforeach</div></div>
        @if ($trendTotal === 0)<div class="empty"><b>Belum ada data</b>Statistik muncul setelah peminjaman pertamamu.</div>@else {!! Charts::cols($trend) !!}@endif
        <div class="legend" style="margin:8px 0 12px"><span><i style="background:var(--sun)"></i>periode berjalan</span><span><i style="background:var(--pri)"></i>periode lalu</span></div>
        <div class="mgrid"><div><b>{{ $trendTotal }}</b><span>total periode ini</span></div><div><b>{{ number_format($trendAvg, 1, ',', '.') }}</b><span>rata-rata per {{ $unitWord }}</span></div><div><b>{{ $trendMax }}</b><span>puncak tertinggi</span></div></div>
    </section>

    <section class="panel c4"><div class="ph"><h3>Barang yang paling sering kamu pinjam</h3></div>
        @if ($favorites->count()){!! Charts::hbars($favorites->map(fn ($f) => ['name' => $f->item->name, 'v' => $f->n])->all()) !!}@else<div class="empty">Belum ada riwayat.</div>@endif
    </section>
    <section class="panel c4"><div class="ph"><div><h3>Biasanya dipinjam bersamaan</h3><small>Paket yang sering diajukan mahasiswa lain</small></div></div>
        <div class="stack grow">
            @forelse ($rules as $r)
                <div class="qitem" style="cursor:default"><div class="b sm">{{ $r['label'] }}</div><div class="xs muted">{{ round($r['conf'] * 100) }}% peminjam barang pertama juga memakai yang lain</div>
                    <form method="POST" action="{{ route('cart.bundle') }}">@csrf @foreach ($r['ids'] as $id)<input type="hidden" name="items[]" value="{{ $id }}">@endforeach<button class="btn sm" style="margin-top:4px">Masukkan ke keranjang</button></form></div>
            @empty
                <div class="empty">Saran paket muncul setelah analisis berjalan.</div>
            @endforelse
        </div>
    </section>
    <section class="panel c4"><div class="ph"><h3>Kabar terbaru</h3></div>
        <ul class="feed grow">@forelse ($feed as $n)<li><span class="dt" style="{{ $n->read_at ? 'background:var(--slate)' : '' }}"></span><div><div>{{ $n->data['text'] ?? '' }}</div><div class="xs muted">{{ $n->created_at->diffForHumans() }}</div></div></li>@empty<li class="muted">Belum ada notifikasi.</li>@endforelse</ul>
    </section>
</div>
@endsection
