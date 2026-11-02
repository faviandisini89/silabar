@extends('layouts.app')
@section('title', 'Tiket saya')
@section('subtitle', 'Semua pengajuan dan statusnya')
@section('content')
<div class="tabs">
    @foreach ($tabs as $k => [$label, $list])
        <a href="{{ route('loans.index', ['tab' => $k]) }}" style="text-decoration:none"><button type="button" class="{{ $tab === $k ? 'on' : '' }}">{{ $label }}<span class="n">{{ $list->count() }}</span></button></a>
    @endforeach
</div>
<div class="g">
    @forelse ($loans as $l)
        <div class="c6">
            <x-ticket :loan="$l">
                @can('cancel', $l)
                    <form method="POST" action="{{ route('loans.cancel', $l) }}" data-confirm="Batalkan pengajuan ini? Stok yang dicadangkan dilepas." class="inline">@csrf<button class="btn sm">Batalkan</button></form>
                @endcan
                @can('extend', $l)
                    @if ($l->pendingExtension)
                        <span class="tag sun">Perpanjangan {{ $l->pendingExtension->days }} hari menunggu</span>
                    @else
                        <button type="button" class="btn sm" data-open-modal="ext-{{ $l->id }}">Minta perpanjangan</button>
                        <div id="ext-{{ $l->id }}" class="hidden" data-cls="narrow">
                            <div class="mh"><div style="flex:1"><h3>Minta perpanjangan</h3><div class="muted sm">{{ $l->loan_code }}</div></div><button class="ibtn" data-close aria-label="Tutup">{!! \App\Support\Icon::svg('x') !!}</button></div>
                            <form method="POST" action="{{ route('loans.extend', $l) }}">@csrf
                                <div class="field"><label>Tambahan hari</label><select class="sel" name="days"><option>1</option><option>2</option><option>3</option></select></div>
                                <div class="field"><label>Alasan</label><textarea class="ta" name="reason" required placeholder="Jelaskan kenapa perlu tambahan waktu"></textarea></div>
                                <div class="row" style="justify-content:flex-end"><button type="button" class="btn" data-close>Batal</button><button class="btn pri">Kirim permintaan</button></div>
                            </form>
                        </div>
                    @endif
                @endcan
                @if (in_array($l->status->value, ['approved', 'borrowed', 'overdue']))
                    <button type="button" class="btn sm" data-qr-big="{{ $l->qr_token }}" data-title="{{ $l->loan_code }}">{!! \App\Support\Icon::svg('qr', 15) !!}QR besar</button>
                @endif
                @if ($l->handed_over_at)<a class="btn sm ghost" href="{{ route('loans.doc', $l) }}" target="_blank">{!! \App\Support\Icon::svg('printer', 15) !!}Berita acara</a>@endif
            </x-ticket>
        </div>
    @empty
        <div class="c12 panel"><div class="empty"><b>Belum ada tiket di sini</b><a class="btn pri" href="{{ route('catalog') }}">Cari barang</a></div></div>
    @endforelse
</div>
@endsection
