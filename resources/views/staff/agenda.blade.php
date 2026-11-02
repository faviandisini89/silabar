@extends('layouts.app')
@section('title', 'Agenda hari ini')
@section('subtitle', 'Yang perlu diserahkan, diterima, dan dikejar')
@php use App\Support\{Charts, Fmt}; @endphp
@section('content')
@php
    $row = function ($l, $right) {
        return '<li><span class="dt"></span><div style="flex:1;min-width:0"><div class="b sm">'.e($l->borrower->name).'<span class="muted" style="font-weight:400"> '.e($l->borrower->organization).'</span></div><div class="sm">'.e($l->itemsSummary()).'</div><div class="xs muted">'.e($l->loan_code).'</div></div>'.$right.'</li>';
    };
@endphp
<div class="g">
    <div class="c3" style="display:flex"><a href="{{ route('staff.approvals') }}" class="panel kpi grow {{ $pending ? 'warn' : '' }}" style="text-decoration:none;color:inherit"><span class="l">Menunggu persetujuan</span><span class="v" data-count="{{ $pending }}">0</span></a></div>
    <div class="c3" style="display:flex"><a href="{{ route('staff.counter') }}" class="panel kpi grow" style="text-decoration:none;color:inherit"><span class="l">Serah terima 2 hari ke depan</span><span class="v" data-count="{{ $handovers->count() }}">0</span></a></div>
    <div class="c3" style="display:flex"><a href="{{ route('staff.counter', ['tab' => 'return']) }}" class="panel kpi grow" style="text-decoration:none;color:inherit"><span class="l">Jatuh tempo hari ini</span><span class="v" data-count="{{ $dueToday->count() }}">0</span></a></div>
    <div class="c3" style="display:flex"><a href="{{ route('staff.counter', ['tab' => 'return']) }}" class="panel kpi grow {{ $overdue->count() ? 'bad' : '' }}" style="text-decoration:none;color:inherit"><span class="l">Terlambat dikembalikan</span><span class="v" data-count="{{ $overdue->count() }}">0</span></a></div>

    <section class="panel c4"><div class="ph"><div><h3>Serah terima</h3><small>{{ $handovers->count() }} tiket siap diambil</small></div></div>
        <ul class="feed grow scroll-y">@forelse ($handovers as $l){!! $row($l, '<div class="right xs"><b>'.$l->start_at->format('H.i').'</b><br>'.($l->start_at->isToday() ? 'hari ini' : $l->start_at->translatedFormat('j M')).'</div>') !!}@empty<li class="muted">Tidak ada.</li>@endforelse</ul>
        <a class="btn sm" style="margin-top:8px" href="{{ route('staff.counter') }}">Buka loket</a></section>
    <section class="panel c4"><div class="ph"><h3>Jatuh tempo hari ini</h3></div>
        <ul class="feed grow scroll-y">@forelse ($dueToday as $l){!! $row($l, '<div class="right xs"><b>'.$l->due_at->format('H.i').'</b><br>'.e(Fmt::until($l->due_at)).'</div>') !!}@empty<li class="muted">Tidak ada yang jatuh tempo hari ini.</li>@endforelse</ul></section>
    <section class="panel c4" @if ($overdue->count()) style="background:var(--brick-l)" @endif><div class="ph"><h3>Terlambat dikembalikan</h3></div>
        <ul class="feed grow scroll-y">@forelse ($overdue as $l){!! $row($l, '<div class="right xs b" style="color:var(--brick)">'.max(1, (int) ceil($l->due_at->diffInHours(now()) / 24)).' hari</div>') !!}@empty<li class="muted">Semua tertib.</li>@endforelse</ul></section>

    <section class="panel c7"><div class="ph"><div><h3>Ketersediaan 24 jam ke depan</h3><small>Barang dengan sisa paling sedikit</small></div></div>
        @foreach ($critical as $c)
            <div class="row" style="margin:7px 0"><div style="width:38%" class="sm b">{{ $c['item']->name }}</div><div style="flex:1">{!! Charts::ring($c['ratio']) !!}</div><div class="sm b" style="width:56px;text-align:right">{{ $c['a']['avail'] }}/{{ $c['a']['usable'] }}</div></div>
        @endforeach
    </section>
    <section class="panel c5"><div class="ph"><h3>Sedang diperbaiki</h3></div>
        <ul class="feed grow">@forelse ($repairs as $m)<li><span class="dt" style="background:var(--sun)"></span><div><div class="b sm">{{ $m->unit->asset_code }}</div><div class="sm">{{ $m->issue }}</div><div class="xs muted">sejak {{ $m->started_at->translatedFormat('j M') }}</div></div></li>@empty<li class="muted">Tidak ada unit dalam perawatan.</li>@endforelse</ul>
        @if ($extensions)<hr class="dash"><div class="b sm">{{ $extensions }} permintaan perpanjangan menunggu</div>@endif
    </section>
</div>
@endsection
