@extends('layouts.app')
@section('title', 'Label QR')
@section('subtitle', $item->name)
@section('content')
<div class="panel"><div class="ph"><div><h3>{{ $units->count() }} label</h3><small>Cetak dan tempel pada unit. QR berisi kode aset.</small></div><span class="sp"></span><button class="btn pri no-print" data-print>{!! \App\Support\Icon::svg('printer', 16) !!}Cetak</button></div>
    <div class="label-sheet">@foreach ($units as $u)<div class="label"><div data-qr="SILABAR:{{ $u->asset_code }}" data-size="110"></div><b>{{ $u->asset_code }}</b><span style="font-size:11px">{{ $item->name }}</span></div>@endforeach</div></div>
@endsection
