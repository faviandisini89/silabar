@extends('layouts.app')
@section('title', 'Pemeriksaan otomatis')
@section('subtitle', 'Hasil pemeriksaan pengajuanmu')
@php
    $rows = [['stock', 'Stok cukup', 'out_of_stock'], ['conflict', 'Jadwal tidak bentrok', 'date_conflict'], ['penalty', 'Tidak ada denda tertunggak', 'outstanding_penalty'], ['duration', 'Durasi sesuai batas barang', 'max_duration_exceeded']];
@endphp
@section('content')
<div class="g">
    <section class="panel c5">
        <div class="ph"><div><h3>Pemeriksaan otomatis</h3><small>Sistem memeriksa pengajuanmu sebelum diteruskan</small></div></div>
        @foreach ($rows as [$key, $label, $code])
            @php($ok = $result['ck'][$key])
            <div class="chk {{ $ok ? 'ok' : 'no' }}"><span class="ic">{{ $ok ? '✓' : '✕' }}</span>
                <div><b>{{ $label }}</b>@foreach ($result['fails'] as $f)@if ($f['code'] === $code)<div class="xs muted">{{ $f['msg'] }}</div>@endif @endforeach</div></div>
        @endforeach
        @if (! $result['ck']['range'])<div class="chk no"><span class="ic">✕</span><div><b>Rentang waktu valid</b><div class="xs muted">Waktu selesai harus setelah waktu mulai.</div></div></div>@endif
    </section>
    <section class="c7 stack">
        @if ($loan)
            <div class="alert ok"><b>{{ $loan->status->value === 'approved' ? 'Disetujui otomatis.' : 'Diteruskan ke Petugas.' }}</b> Simpan tiket ini: kodenya dipindai saat mengambil barang.</div>
            <x-ticket :loan="$loan" :no-steps="true" />
            <div class="row" style="justify-content:flex-end"><a class="btn" href="{{ route('catalog') }}">Ajukan lagi</a><a class="btn pri" href="{{ route('loans.index') }}">Lihat tiket saya</a></div>
        @else
            @if ($logged)<div class="sticky-note">Kebutuhanmu sudah dicatat. Kalau barang ini sering kehabisan, Admin akan menerima saran untuk menambah stoknya.</div>@endif
            @if ($suggest)
                <div class="panel flat"><b>Tanggal terdekat yang tersedia</b>
                    <div class="sm">{{ \App\Support\Fmt::dt($suggest) }} sampai {{ \App\Support\Fmt::dt($suggest->copy()->addSeconds(abs($end->timestamp - $start->timestamp))) }}</div>
                    <form method="POST" action="{{ route('cart.window') }}" style="margin-top:8px">@csrf
                        <input type="hidden" name="start" value="{{ $suggest->toIso8601String() }}"><input type="hidden" name="end" value="{{ $suggest->copy()->addSeconds(abs($end->timestamp - $start->timestamp))->toIso8601String() }}">
                        <button class="btn sun sm">Pakai tanggal ini</button></form></div>
            @endif
            <div class="row" style="justify-content:flex-end"><a class="btn pri" href="{{ route('catalog') }}">Ubah pengajuan</a></div>
        @endif
    </section>
</div>
@endsection
