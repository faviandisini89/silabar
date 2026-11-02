@extends('layouts.app')
@section('title', 'Loket')
@section('subtitle', 'Serah terima dan pengembalian dengan scan QR')
@php use App\Support\Fmt; $conds = ['good' => 'Baik', 'fair' => 'Cukup, ada tanda pakai', 'damaged' => 'Rusak', 'lost' => 'Hilang']; @endphp
@section('content')
<div class="split">
    <aside>
        <div class="tabs">
            <a href="{{ route('staff.counter', ['tab' => 'handover']) }}" style="text-decoration:none"><button type="button" class="{{ $tab === 'handover' ? 'on' : '' }}">Serah terima<span class="n">{{ $handoverCount }}</span></button></a>
            <a href="{{ route('staff.counter', ['tab' => 'return']) }}" style="text-decoration:none"><button type="button" class="{{ $tab === 'return' ? 'on' : '' }}">Pengembalian<span class="n">{{ $returnCount }}</span></button></a>
        </div>
        <form method="GET" action="{{ route('staff.counter') }}" id="codeform" class="row" style="margin-bottom:8px">
            <input class="in" id="code" name="code" placeholder="Kode atau QR tiket" autocomplete="off">
            <button type="button" class="btn sm" data-scan data-target="#code" data-submit="#codeform" aria-label="Pindai dengan kamera">{!! \App\Support\Icon::svg('scan', 16) !!}</button>
        </form>
        <form method="GET" action="{{ route('staff.counter') }}" style="margin-bottom:10px"><input type="hidden" name="tab" value="{{ $tab }}"><input class="in" name="q" value="{{ $q }}" placeholder="Cari nama peminjam atau kode"></form>
        <div class="stack" style="max-height:60vh;overflow-y:auto;padding-right:3px">
            @forelse ($list as $l)
                <a class="qitem {{ $sel && $sel->id === $l->id ? 'on' : '' }}" href="{{ route('staff.counter', ['tab' => $tab, 'loan' => $l->id]) }}" style="text-decoration:none;color:inherit">
                    <div class="row"><span class="mono-s">{{ $l->loan_code }}</span><span class="sp"></span><span class="tag {{ $l->status->tone() }}">{{ $l->status->label() }}</span></div>
                    <div class="b sm">{{ $l->borrower->name }}</div><div class="xs muted">{{ $tab === 'handover' ? 'Ambil '.Fmt::dt($l->start_at) : Fmt::until($l->due_at) }}</div></a>
            @empty
                <div class="empty">Tidak ada tiket.</div>
            @endforelse
        </div>
    </aside>

    <section>
    @if (! $sel)
        <div class="panel"><div class="scanbox"><span class="corner c1"></span><span class="corner c2"></span><span class="corner c3"></span><span class="corner c4"></span><span class="line"></span><span class="b" style="background:var(--panel);padding:2px 10px;border-radius:6px">Arahkan kamera ke QR tiket</span></div>
            <p class="muted sm" style="margin:12px 0 0">Pilih tiket di kiri, ketik kodenya, atau tekan ikon kamera untuk memindai.</p></div>
        <div class="g" style="margin-top:16px">
            <div class="c7 panel"><h3 style="margin-bottom:8px">Aktivitas loket</h3><ul class="feed">@forelse ($activity as $a)<li><span class="dt"></span><div><div class="b sm">{{ $a->action }} <span class="mono-s">{{ $a->subject_type }}</span></div><div class="xs muted">{{ $a->causer?->name }}, {{ $a->created_at->diffForHumans() }}</div></div></li>@empty<li class="muted">Belum ada aktivitas.</li>@endforelse</ul></div>
            <div class="c5 panel tint"><h3 style="margin-bottom:8px">Urutan di loket</h3><ol class="sm" style="margin:0;padding-left:18px"><li>Pindai QR tiket peminjam.</li><li>Pindai tiap unit barang (atau hitung jumlah untuk barang massal).</li><li>Serahkan. Saat kembali, cek kondisi per barang.</li><li>Denda dihitung otomatis jika telat, rusak, atau hilang.</li></ol></div>
        </div>
    @elseif ($tab === 'handover')
        <div class="panel">
            <div class="row wrap"><h3 style="flex:1">Serah terima {{ $sel->loan_code }}</h3><span class="stamp st-approved">Disetujui</span></div>
            <div class="muted sm">{{ $sel->borrower->name }}, {{ $sel->borrower->organization }}. Ambil {{ Fmt::dt($sel->start_at) }}, kembali {{ Fmt::dt($sel->due_at) }}.</div>
            <form method="POST" action="{{ route('staff.counter.handover', $sel) }}" style="margin-top:12px">@csrf
                @foreach ($sel->details as $d)
                    <div class="panel flat" style="margin-top:10px">
                        <div class="row wrap"><b>{{ $d->item->name }} ×{{ $d->qty }}</b>
                            @if ($d->item->isUnit())<span class="tag sun">pindai {{ $d->qty }} unit</span>@endif</div>
                        @if ($d->item->isUnit())
                            <div class="field" style="margin:8px 0 0"><label for="u{{ $d->id }}">Kode unit (satu per baris, contoh {{ $d->item->sku }}-001)</label>
                                <div class="row" style="align-items:flex-start"><textarea class="ta" id="u{{ $d->id }}" name="units[{{ $d->id }}]" rows="{{ min(4, max(2, $d->qty)) }}" style="min-height:60px">{{ old('units.'.$d->id) }}</textarea>
                                    <button type="button" class="btn sm" data-scan data-target="#u{{ $d->id }}" data-mode="append" aria-label="Pindai unit">{!! \App\Support\Icon::svg('scan', 16) !!}</button></div></div>
                        @else
                            <label class="check-row" style="margin-top:8px"><input type="checkbox" name="bulk[{{ $d->id }}]" value="1" {{ old('bulk.'.$d->id) ? 'checked' : '' }}> Jumlah sudah dihitung ({{ $d->qty }})</label>
                        @endif
                    </div>
                @endforeach
                <div class="row" style="margin-top:14px"><span class="hint sp">Sistem memeriksa bahwa setiap unit sesuai barangnya dan sedang tersedia.</span><button class="btn pri">Serahkan barang</button></div>
            </form>
        </div>
    @else
        @php $late = now()->gt($sel->due_at) ? (int) ceil($sel->due_at->diffInHours(now()) / 24) : 0; @endphp
        <div class="panel">
            <div class="row wrap"><h3 style="flex:1">Pengembalian {{ $sel->loan_code }}</h3><span class="stamp st-{{ $sel->status->value }}">{{ $sel->status->label() }}</span></div>
            <div class="muted sm">{{ $sel->borrower->name }}. Jatuh tempo {{ Fmt::dt($sel->due_at) }}, {{ Fmt::until($sel->due_at) }}.</div>
            @if ($late)<div class="alert bad" style="margin-top:10px">Terlambat {{ $late }} hari. Denda keterlambatan dihitung otomatis saat disimpan.</div>@endif
            <form method="POST" action="{{ route('staff.counter.return', $sel) }}" style="margin-top:6px">@csrf
                @foreach ($sel->details as $d)
                    @php $out = $d->outstandingQty(); @endphp
                    <div class="panel flat" style="margin-top:10px">
                        <div class="row wrap"><b style="flex:1">{{ $d->item->name }}@if ($d->unit) <span class="unitchip">{{ $d->unit->asset_code }}</span>@endif</b>
                            @if ($out)<span class="xs muted">belum kembali: {{ $out }}</span>@else<span class="tag moss">Sudah kembali</span>@endif</div>
                        @if ($out)
                            <div class="two" style="margin-top:6px">
                                <div class="field" style="margin:0"><label for="q{{ $d->id }}">Jumlah kembali</label><input class="in" type="number" min="0" max="{{ $out }}" id="q{{ $d->id }}" name="rows[{{ $d->id }}][qty]" value="{{ old('rows.'.$d->id.'.qty', $out) }}"></div>
                                <div class="field" style="margin:0"><label for="c{{ $d->id }}">Kondisi</label><select class="sel" id="c{{ $d->id }}" name="rows[{{ $d->id }}][condition]">@foreach ($conds as $k => $v)<option value="{{ $k }}" {{ old('rows.'.$d->id.'.condition') === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach</select></div>
                            </div>
                        @endif
                    </div>
                @endforeach
                <div class="field" style="margin-top:10px"><label for="notes">Catatan (opsional)</label><input class="in" id="notes" name="notes"></div>
                <div class="row" style="justify-content:flex-end;margin-top:6px"><button class="btn pri">Terima pengembalian</button></div>
            </form>
        </div>
    @endif
    </section>
</div>
@endsection
