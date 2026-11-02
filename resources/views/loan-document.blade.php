@extends('layouts.app')
@section('title', 'Berita acara')
@section('subtitle', $loan->loan_code)
@section('content')
<div class="doc" style="max-width:780px">
    <h4>BERITA ACARA PEMINJAMAN BARANG</h4>
    <div>Nomor: {{ $loan->loan_code }}</div>
    <p>Pada {{ $loan->handed_over_at->translatedFormat('j F Y, H.i') }}, Petugas {{ $loan->handedOverBy?->name ?? '-' }} menyerahkan barang berikut kepada <b>{{ $loan->borrower->name }}</b> ({{ $loan->borrower->nim ?: '-' }}, {{ $loan->borrower->organization ?: '-' }}).</p>
    <table><tr><th>Barang</th><th>Jumlah</th><th>Unit</th></tr>
        @foreach ($loan->details as $d)<tr><td>{{ $d->item->name }}</td><td>{{ $d->qty }}</td><td>{{ $d->unit?->asset_code ?? '-' }}</td></tr>@endforeach</table>
    <div>Keperluan: {{ $loan->purpose }}</div>
    <div>Batas pengembalian: <b>{{ $loan->due_at->translatedFormat('j F Y, H.i') }}</b></div>
    @if ($loan->returned_at)<div>Dikembalikan: {{ $loan->returned_at->translatedFormat('j F Y, H.i') }}{{ $loan->late_days ? ' (terlambat '.$loan->late_days.' hari)' : ' (tepat waktu)' }}</div>@endif
    <div class="row" style="justify-content:space-between;margin-top:18px;align-items:flex-end">
        <div class="b sm">Peminjam<br><br><br>{{ $loan->borrower->name }}</div>
        <div class="big-qr" style="padding:4px" data-qr="{{ $loan->qr_token }}" data-size="84"></div>
        <div class="b sm">Petugas<br><br><br>{{ $loan->handedOverBy?->name ?? '-' }}</div>
    </div>
</div>
<div class="row no-print" style="margin-top:12px"><button class="btn pri" data-print>{!! \App\Support\Icon::svg('printer', 16) !!}Cetak atau simpan PDF</button></div>
@endsection
