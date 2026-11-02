@extends('layouts.app')
@section('title', 'Dasbor analitik')
@section('subtitle', 'Pola peminjaman dan prediksi kebutuhan')
@php
    use App\Support\{Charts, Fmt};
    use App\Services\StatsService;
    $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    $idle = $util->filter(fn ($x) => $x['u'] < .15)->pluck('name')->all();
    $trendLabel = ['harian' => '14 hari terakhir', 'mingguan' => '12 pekan terakhir', 'bulanan' => '12 bulan terakhir', 'tahunan' => 'per tahun'][$period];
@endphp
@section('content')
<div class="g">
    <div class="c3" style="display:flex"><div class="panel kpi grow"><span class="l">Peminjaman aktif</span><span class="v" data-count="{{ $kpi['active'] }}">0</span><span class="d muted">{{ $kpi['month'] }} tiket bulan ini, {{ $kpi['last_month'] }} bulan lalu</span>{!! Charts::spark($kpi['weekly']) !!}</div></div>
    <div class="c3" style="display:flex"><div class="panel kpi grow {{ $kpi['overdue'] ? 'bad' : '' }}"><span class="l">Terlambat dikembalikan</span><span class="v" data-count="{{ $kpi['overdue'] }}">0</span><span class="d" style="color:var(--brick)">{{ $kpi['overdue'] ? 'perlu dikejar hari ini' : 'semua tertib' }}</span></div></div>
    <div class="c3" style="display:flex"><div class="panel kpi grow"><span class="l">Utilisasi rata-rata</span><span class="v" data-count="{{ round($kpi['utilization'] * 100) }}" data-fmt="pct">0</span><span class="d muted">30 hari, semua barang</span></div></div>
    <div class="c3" style="display:flex"><div class="panel kpi grow"><span class="l">Pengembalian tepat waktu</span><span class="v" data-count="{{ round($kpi['on_time'] * 100) }}" data-fmt="pct">0</span><span class="d muted">90 hari terakhir</span></div></div>

    <section class="panel c7">
        <div class="ph"><div><h3>Barang paling sering dipinjam</h3><small>Peringkat tiket, {{ StatsService::WINDOW_LABEL[$period] }}</small></div><span class="sp"></span>
            <div class="seg">@foreach (StatsService::PERIODS as $k => $label)<a href="{{ route('admin.analytics', ['p' => $k, 'cat' => $cat]) }}" style="padding:5px 11px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;color:{{ $period === $k ? 'var(--on-pri)' : 'var(--muted)' }};{{ $period === $k ? 'background:var(--pri)' : '' }}">{{ $label }}</a>@endforeach</div>
            <form method="GET"><input type="hidden" name="p" value="{{ $period }}"><select class="sel" style="width:auto" name="cat" onchange="this.form.submit()" aria-label="Kategori"><option value="">Semua kategori</option>@foreach ($categories as $c)<option value="{{ $c->id }}" {{ (string) $cat === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>@endforeach</select></form></div>
        @if ($top->count()){!! Charts::hbars($top->map(fn ($r) => ['name' => $r->item->name, 'v' => $r->n])->all()) !!}@else<div class="empty"><b>Belum ada peminjaman pada periode ini</b></div>@endif
    </section>
    <section class="panel c5"><div class="ph"><div><h3>Tren peminjaman</h3><small>{{ $trendLabel }}</small></div></div>{!! Charts::cols($trend) !!}<div class="legend" style="margin-top:6px"><span><i style="background:var(--sun)"></i>periode berjalan (belum penuh)</span></div></section>

    <section class="panel c7"><div class="ph"><div><h3>Jam dan hari tersibuk</h3><small>Waktu mulai peminjaman, 6 bulan terakhir</small></div></div>{!! Charts::heat($heat) !!}
        <p class="sm" style="margin:10px 0 0">Puncaknya {{ $days[$heat['best'][0]] }} sekitar pukul {{ str_pad($heat['best'][1], 2, '0', STR_PAD_LEFT) }}.00 dengan {{ $heat['best'][2] }} peminjaman. Jadwalkan Petugas tambahan pada jam ini.</p></section>
    <section class="panel c5"><div class="ph"><div><h3>Utilisasi barang</h3><small>Rata-rata terpakai selama 30 hari terakhir</small></div></div>
        <div class="grow scroll-y" style="max-height:300px">{!! Charts::hbars($util->map(fn ($x) => ['name' => $x['name'], 'v' => round($x['u'] * 100), 'color' => $x['u'] < .15 ? 'var(--slate)' : ($x['u'] > .6 ? 'var(--brick)' : '')])->all(), '%') !!}</div>
        <p class="xs muted" style="margin:8px 0 0">{{ $idle ? 'Jarang terpakai (di bawah 15%): '.implode(', ', $idle).'. Pertimbangkan relokasi.' : 'Tidak ada barang yang menganggur.' }}</p></section>

    <section class="panel c7">
        <div class="ph"><div><h3>Perkiraan permintaan</h3><small>Jumlah tiket per pekan, 4 pekan ke depan (model machine learning)</small></div><span class="sp"></span>
            <form method="GET"><input type="hidden" name="p" value="{{ $period }}"><select class="sel" style="width:auto" name="fc" onchange="this.form.submit()" aria-label="Barang">@foreach ($items as $i)<option value="{{ $i->id }}" {{ $fcItem->id === $i->id ? 'selected' : '' }}>{{ $i->name }}</option>@endforeach</select></form></div>
        {!! Charts::line($forecast['history'], $forecast['forecast'], $forecast['labels']) !!}
        <div class="legend"><span><i style="background:var(--pri)"></i>riil</span><span><i style="background:var(--sun)"></i>perkiraan dan rentang 80%</span></div>
        <p class="xs muted" style="margin:6px 0 0">@if ($forecast['model']) Model terpilih untuk barang ini: <b>{{ $forecast['model'] === 'gbr_lag' ? 'Gradient Boosting (fitur lag dan musiman)' : 'rata-rata bergerak' }}</b>, dipilih lewat backtest. @else Belum ada perkiraan: jalankan analisis. @endif</p>
    </section>
    <section class="panel c5">
        <div class="ph"><div><h3>Rekomendasi pengadaan</h3><small>{{ $recs->count() }} menunggu keputusan</small></div><span class="sp"></span><a class="btn sm" href="{{ route('admin.procure') }}">Buka</a></div>
        <div class="stack grow">
            @forelse ($recs as $r)
                <a class="qitem" href="{{ route('admin.procure') }}" style="text-decoration:none;color:inherit"><div class="row"><b class="sp">{{ $r->item->name }}</b><span class="tag {{ $r->cause->tone() }}">{{ $r->recommended_add_qty ? '+'.$r->recommended_add_qty.' unit' : 'Tindak lanjut' }}</span></div><div class="xs muted">{{ $r->unmet_count }} permintaan gagal dari {{ $r->affected_users }} peminjam. {{ $r->cause->label() }}.</div></a>
            @empty
                <div class="empty"><b>Tidak ada rekomendasi</b>Stok sudah mencukupi.</div>
            @endforelse
        </div>
    </section>

    <section class="panel c5"><div class="ph"><div><h3>Peminjaman berisiko telat</h3><small>Peluang dari model klasifikasi</small></div></div>
        @if ($risk->count())
            <div class="tw"><table class="t"><tbody>@foreach ($risk as $r)<tr><td><div class="mono-s">{{ $r->loan->loan_code }}</div><div class="xs muted">{{ $r->loan->borrower->name }}</div></td><td style="width:42%">{!! Charts::ring($r->risk_probability, true) !!}</td><td class="right b">{{ Fmt::pct($r->risk_probability) }}</td></tr>@endforeach</tbody></table></div>
            @if ($riskReport)<p class="xs muted" style="margin:8px 0 0">Model: <b>{{ $riskReport['model'] }}</b>@if (! empty($riskReport['auc'])), AUC {{ $riskReport['auc'] }} pada data uji berurutan waktu{!! '' !!}@endif. Dilatih dari {{ $riskReport['n_train'] }} pengembalian.</p>@endif
        @else
            <div class="empty"><b>Belum ada skor</b>Jalankan analisis untuk menilai peminjaman aktif.</div>
        @endif
    </section>
    <section class="panel c3"><div class="ph"><h3>Anomali</h3></div>
        <ul class="feed grow">@forelse ($anomalies as $a)<li><span class="dt" style="background:var(--{{ $a['kind'] === 'unit_repeat_damage' ? 'brick' : ($a['kind'] === 'demand_spike' ? 'sun' : 'sky') }})"></span><div><div class="sm">{{ $a['text'] }}</div></div></li>@empty<li class="muted">Tidak ada anomali.</li>@endforelse</ul></section>
    <section class="panel c4"><div class="ph"><div><h3>Dipinjam bersamaan</h3><small>Aturan asosiasi: dukungan, keyakinan, lift</small></div></div>
        <div class="stack">@forelse ($rules as $r)<div class="sm"><b>{{ $r['label'] }}</b><div class="xs muted">dukungan {{ Fmt::pct($r['support']) }}, keyakinan {{ Fmt::pct($r['confidence']) }}, lift {{ number_format($r['lift'], 1, ',', '.') }}</div></div>@empty<div class="muted sm">Belum ada aturan. Jalankan analisis.</div>@endforelse</div></section>

    <section class="panel c12 flat">
        <div class="ph"><div><h3>Mesin analitik (Python)</h3><small>Forecast, risiko telat, aturan asosiasi, anomali, dan rekomendasi pengadaan</small></div><span class="sp"></span>
            <span class="tag {{ $online ? 'moss' : 'brick' }}">{{ $online ? 'Layanan aktif' : 'Layanan tidak terhubung' }}</span>
            <form method="POST" action="{{ route('admin.analytics.run') }}">@csrf<button class="btn pri sm">{!! \App\Support\Icon::svg('refresh', 15) !!}Jalankan analisis sekarang</button></form></div>
        @if ($summary)
            <div class="mgrid">
                @foreach ($summary['tasks'] as $name => $t)<div><b style="font-size:16px">{{ $t['status'] === 'ok' ? 'OK' : 'Gagal' }}</b><span>{{ ['forecast' => 'Forecast permintaan', 'risk' => 'Risiko telat', 'assoc' => 'Aturan asosiasi', 'anomaly' => 'Anomali', 'procurement' => 'Rekomendasi pengadaan'][$name] ?? $name }}</span></div>@endforeach
            </div>
            <p class="xs muted" style="margin:10px 0 0">Terakhir berjalan {{ $summaryAt?->diffForHumans() }} dalam {{ $summary['seconds'] ?? '?' }} detik. Jadwal otomatis: tiap Senin pukul 02.00.</p>
        @else
            <p class="sm muted" style="margin:0">Analisis belum pernah dijalankan. Nyalakan layanan Python lalu tekan tombol di atas, atau jalankan <code>php artisan analytics:run</code>.</p>
        @endif
    </section>
</div>
@endsection
