@extends('layouts.app')
@section('title', 'Laporan harian')
@section('subtitle', now()->translatedFormat('j F Y'))
@section('content')
<div class="doc" style="max-width:780px">
    <h4>LAPORAN OPERASIONAL HARIAN</h4>
    <div>{{ now()->translatedFormat('j F Y') }}. Disusun oleh {{ $by->name }}.</div>
    <table><tr><th>Kegiatan</th><th>Jumlah</th></tr>
        @foreach ($daily as $label => $n)<tr><td>{{ $label }}</td><td>{{ $n }}</td></tr>@endforeach
        <tr><td>Denda belum lunas</td><td>{{ \App\Support\Fmt::rp($unpaid) }}</td></tr><tr><td>Unit dalam perawatan</td><td>{{ $repairs }}</td></tr></table>
</div>
<div class="row no-print" style="margin-top:12px"><button class="btn pri" data-print>{!! \App\Support\Icon::svg('printer', 16) !!}Cetak</button></div>
@endsection
