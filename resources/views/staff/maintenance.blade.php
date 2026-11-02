@extends('layouts.app')
@section('title', 'Perawatan')
@section('subtitle', 'Unit rusak dan perbaikannya')
@php use App\Support\Fmt; @endphp
@section('content')
<div class="g">
    <section class="panel c8">
        <div class="ph"><div class="tabs" style="margin:0;flex:1">
            <a href="{{ route('staff.maint', ['tab' => 'open']) }}" style="text-decoration:none"><button type="button" class="{{ $tab === 'open' ? 'on' : '' }}">Sedang diperbaiki<span class="n">{{ $open->count() }}</span></button></a>
            <a href="{{ route('staff.maint', ['tab' => 'done']) }}" style="text-decoration:none"><button type="button" class="{{ $tab === 'done' ? 'on' : '' }}">Selesai<span class="n">{{ $done->count() }}</span></button></a></div>
            <button type="button" class="btn pri" data-open-modal="new-repair">{!! \App\Support\Icon::svg('plus', 16) !!}Laporkan kerusakan</button></div>
        <div class="tw"><table class="t"><thead><tr><th>Unit</th><th>Masalah</th><th>Sejak</th><th class="right">Biaya</th><th></th></tr></thead><tbody>
            @forelse ($list as $m)
                <tr><td><div class="mono-s">{{ $m->unit->asset_code }}</div><div class="xs muted">{{ $m->unit->item->name }}</div></td><td>{{ $m->issue }}</td><td>{{ $m->started_at->translatedFormat('j M') }}@if ($m->finished_at)<div class="xs muted">selesai {{ $m->finished_at->translatedFormat('j M') }}</div>@endif</td><td class="right">{{ $m->cost ? Fmt::rp($m->cost) : '-' }}</td>
                    <td class="right">@if ($m->status === 'open')<button type="button" class="btn sm" data-open-modal="fin-{{ $m->id }}">Tandai selesai</button>
                        <div id="fin-{{ $m->id }}" class="hidden" data-cls="narrow"><div class="mh"><div style="flex:1"><h3>Selesai diperbaiki</h3></div><button class="ibtn" data-close>{!! \App\Support\Icon::svg('x') !!}</button></div>
                            <form method="POST" action="{{ route('staff.maint.finish', $m) }}">@csrf<div class="field"><label>Biaya perbaikan (Rp)</label><input class="in" type="number" min="0" name="cost" value="0"></div><div class="row" style="justify-content:flex-end"><button type="button" class="btn" data-close>Batal</button><button class="btn pri">Unit siap dipakai</button></div></form></div>@endif</td></tr>
            @empty
                <tr><td colspan="5" class="muted">Tidak ada data.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
    <section class="panel c4 tint"><h3>Mengapa ini penting</h3>
        <p class="sm" style="margin:8px 0">Unit yang sedang diperbaiki otomatis keluar dari stok tersedia, jadi tidak ada yang meminjam barang rusak.</p>
        <p class="sm" style="margin:0 0 10px">Jika banyak unit mengendap di perawatan, analisis menandainya sebagai penyebab kehabisan: perbaiki dulu, jangan beli baru.</p>
        <div class="mgrid"><div><b>{{ $inRepair }}</b><span>unit di perawatan</span></div><div><b>{{ Fmt::rp($cost12m) }}</b><span>biaya 12 bulan</span></div></div></section>
</div>
<div id="new-repair" class="hidden" data-cls="narrow"><div class="mh"><div style="flex:1"><h3>Laporkan kerusakan</h3><div class="muted sm">Unit keluar dari stok tersedia sampai selesai diperbaiki.</div></div><button class="ibtn" data-close>{!! \App\Support\Icon::svg('x') !!}</button></div>
    <form method="POST" action="{{ route('staff.maint.store') }}">@csrf
        <div class="field"><label>Unit</label><select class="sel" name="unit_id">@foreach ($available as $u)<option value="{{ $u->id }}">{{ $u->asset_code }} ({{ $u->item->name }})</option>@endforeach</select></div>
        <div class="field"><label>Masalah</label><textarea class="ta" name="issue" required placeholder="Contoh: lampu redup, kipas berisik"></textarea></div>
        <div class="row" style="justify-content:flex-end"><button type="button" class="btn" data-close>Batal</button><button class="btn pri">Simpan laporan</button></div></form></div>
@endsection
