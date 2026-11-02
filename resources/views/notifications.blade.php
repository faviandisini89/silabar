@extends('layouts.app')
@section('title', 'Notifikasi')
@section('subtitle', 'Semua kabar tentang tiket dan aktivitasmu')
@section('content')
<div class="panel">
    <div class="ph"><h3>Semua notifikasi</h3><span class="sp"></span>
        <form method="POST" action="{{ route('notifications.read') }}">@csrf<button class="btn sm">Tandai semua dibaca</button></form></div>
    <ul class="feed">
        @forelse ($items as $n)
            <li><span class="dt" style="{{ $n->read_at ? 'background:var(--slate)' : '' }}"></span>
                <div style="flex:1"><a href="{{ route('notifications.open', $n->id) }}" style="color:inherit;text-decoration:none">{{ $n->data['text'] ?? '' }}</a><div class="xs muted">{{ $n->created_at->diffForHumans() }}</div></div></li>
        @empty
            <li class="muted">Belum ada notifikasi.</li>
        @endforelse
    </ul>
    <div class="pager">{{ $items->links('pagination::simple-tailwind') }}</div>
</div>
@endsection
