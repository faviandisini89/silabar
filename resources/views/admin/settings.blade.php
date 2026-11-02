@extends('layouts.app')
@section('title', 'Pengaturan')
@section('subtitle', 'Aturan peminjaman dan ambang analisis')
@php
    $num = fn ($key, $label, $step = '1', $hint = '') => '<div class="field"><label for="'.str_replace('.', '_', $key).'">'.$label.'</label><input class="in" type="number" step="'.$step.'" id="'.str_replace('.', '_', $key).'" name="'.str_replace('.', '_', $key).'" value="'.old(str_replace('.', '_', $key), $s[$key]).'">'.($hint ? '<span class="hint">'.$hint.'</span>' : '').'</div>';
@endphp
@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" class="g">
    @csrf
    <section class="panel c4"><h3 style="margin-bottom:10px">Aturan peminjaman</h3>
        <label class="check-row" style="margin-bottom:10px"><input type="checkbox" name="loan_auto_approve" value="1" {{ $s['loan.auto_approve'] ? 'checked' : '' }}> Setujui otomatis peminjam tepercaya</label>
        {!! $num('loan.auto_approve_trust', 'Trust score minimal', '1', 'Berlaku untuk barang yang biasanya perlu persetujuan.') !!}
        {!! $num('loan.grace_hours', 'Masa tenggang tidak diambil (jam)', '1', 'Tiket disetujui yang tidak diambil dibatalkan otomatis.') !!}</section>
    <section class="panel c4"><h3 style="margin-bottom:10px">Tarif denda</h3>
        {!! $num('penalty.late_per_day', 'Terlambat (Rp per unit per hari)', '500') !!}{!! $num('penalty.damaged_pct', 'Rusak (% nilai ganti)') !!}<p class="hint">Hilang dikenai 100% nilai ganti.</p></section>
    <section class="panel c4"><h3 style="margin-bottom:10px">Ambang rekomendasi pengadaan</h3>
        {!! $num('procure.min_unmet', 'Minimal permintaan gagal') !!}{!! $num('procure.min_users', 'Minimal peminjam berbeda') !!}{!! $num('procure.fill_below', 'Atau fill rate di bawah (%)') !!}{!! $num('procure.window_days', 'Jendela analisis (hari)') !!}{!! $num('procure.maint_share', 'Ambang unit rusak (%)') !!}</section>
    <section class="panel c8"><h3 style="margin-bottom:10px">Bobot skor prioritas</h3>
        <div class="two" style="grid-template-columns:repeat(4,1fr)">{!! $num('procure.w_unmet', 'Permintaan gagal', '0.05') !!}{!! $num('procure.w_users', 'Peminjam terdampak', '0.05') !!}{!! $num('procure.w_util', 'Utilisasi', '0.05') !!}{!! $num('procure.w_trend', 'Tren', '0.05') !!}</div>
        <div class="row" style="margin-top:6px"><span class="hint sp">Idealnya jumlah bobot 1,0. Layanan Python membaca nilai ini saat analisis berikutnya.</span><button class="btn pri">Simpan pengaturan</button></div></section>
</form>
<div class="g" style="margin-top:16px">
    <section class="panel c6"><h3 style="margin-bottom:8px">Kategori</h3><div class="chips" style="margin:6px 0">@foreach ($categories as $c)<span class="chip">{{ $c->name }}</span>@endforeach</div>
        <form method="POST" action="{{ route('admin.settings.master') }}" class="row">@csrf<input type="hidden" name="type" value="category"><input class="in" name="name" placeholder="Kategori baru" required><button class="btn sm">Tambah</button></form></section>
    <section class="panel c6"><h3 style="margin-bottom:8px">Lokasi</h3><div class="chips" style="margin:6px 0">@foreach ($locations as $c)<span class="chip">{{ $c->name }}</span>@endforeach</div>
        <form method="POST" action="{{ route('admin.settings.master') }}" class="row">@csrf<input type="hidden" name="type" value="location"><input class="in" name="name" placeholder="Lokasi baru" required><button class="btn sm">Tambah</button></form></section>
</div>
@endsection
