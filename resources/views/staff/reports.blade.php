@extends('layouts.app')
@section('title', 'Laporan')
@section('subtitle', 'Rekap harian, ekspor, dan impor data')
@php use App\Support\{Charts, Fmt}; @endphp
@section('content')
<div class="g">
    <section class="panel c7"><div class="ph"><div><h3>Rekap hari ini</h3><small>{{ Fmt::d(now()) }}</small></div><span class="sp"></span><a class="btn sm" target="_blank" href="{{ route('staff.reports.daily') }}">{!! \App\Support\Icon::svg('printer', 15) !!}Cetak laporan harian</a></div>
        <div class="mgrid">@foreach ($daily as $label => $n)<div><b data-count="{{ $n }}">0</b><span>{{ $label }}</span></div>@endforeach</div>
        <hr class="dash"><div class="b sm" style="margin-bottom:6px">Kebutuhan tak terpenuhi (30 hari)</div>
        @if ($unmet){!! Charts::hbars($unmet) !!}@else<div class="muted sm">Tidak ada permintaan yang gagal.</div>@endif
    </section>
    <section class="panel c5"><div class="ph"><div><h3>Ekspor data</h3><small>Unduh sebagai CSV, buka di Excel atau Google Sheets</small></div></div>
        <div class="stack">@foreach ([['loans', 'Peminjaman 90 hari terakhir'], ['penalties', 'Denda'], ['items', 'Daftar barang'], ['unmet', 'Kebutuhan tak terpenuhi']] as [$k, $label])
            <a class="btn" style="width:100%;justify-content:flex-start" href="{{ route('staff.reports.csv', $k) }}">{!! \App\Support\Icon::svg('dl', 16) !!}{{ $label }}</a>@endforeach</div></section>
    @if (auth()->user()->isAdmin())
        <section class="panel c12"><div class="ph"><div><h3>Impor barang dari CSV</h3><small>Kolom: name, sku, category, location, tracking (unit atau bulk), stock, max_days, requires_approval (true atau false), value</small></div></div>
            <form method="POST" action="{{ route('admin.import') }}">@csrf
                <textarea class="ta" name="csv" rows="5" style="min-height:120px" placeholder="Tempel CSV di sini">{{ old('csv', "name,sku,category,location,tracking,stock,max_days,requires_approval,value\nMicrophone Podium,POD,Audio visual,Gudang Sarpras Lt. 1,unit,2,3,false,750000") }}</textarea>
                <div class="row" style="margin-top:10px"><span class="hint sp">Pratinjau dulu untuk memeriksa jumlah baris yang valid.</span><button class="btn" name="mode" value="preview">Pratinjau</button><button class="btn pri" name="mode" value="commit">Impor</button></div></form></section>
    @endif
</div>
@endsection
