<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SILABAR') — SILABAR</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cpath d='M4 9l12-6 12 6v14l-12 6-12-6z' fill='%23F2B544'/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/silabar.css') }}">
    <script>try{var t=localStorage.getItem('silabar-theme');if(t)document.documentElement.dataset.theme=t;}catch(e){}</script>
</head>
<body>
@php
    $me = auth()->user();
    $groups = \App\Support\Nav::for($me->role);
    $badges = \App\Support\Sidebar::badges($me);
    $unread = $me->unreadNotifications()->count();
@endphp
<div class="shell">
    <aside class="side" id="side">
        <div class="brand">{!! \App\Support\Icon::logo(32) !!}<span>SILABAR</span></div>
        @foreach ($groups as [$title, $links])
            @if ($title)<div class="nav-h">{{ $title }}</div>@endif
            <nav class="nav" aria-label="{{ $title ?? 'Menu' }}">
                @foreach ($links as [$route, $label, $icon])
                    <a href="{{ route($route) }}" class="{{ request()->routeIs($route.'*') ? 'on' : '' }}">
                        {!! \App\Support\Icon::svg($icon, 19) !!}<span>{{ $label }}</span>
                        @if (($badges[$route] ?? 0) > 0)<span class="cnt">{{ $badges[$route] }}</span>@endif
                    </a>
                @endforeach
            </nav>
        @endforeach
        <div class="who"><b>{{ $me->name }}</b>{{ $me->role->label() }}@if ($me->organization), {{ $me->organization }}@endif</div>
    </aside>

    <div class="main">
        <header class="top">
            <button class="ibtn menu-btn" data-menu aria-label="Menu">{!! \App\Support\Icon::svg('menu') !!}</button>
            <div><h1>@yield('title')</h1><div class="sub">@yield('subtitle')</div></div>
            <span class="sp"></span>
            <button class="ibtn" data-theme-toggle aria-label="Ganti tema">{!! \App\Support\Icon::svg('moon') !!}</button>
            <div style="position:relative">
                <button class="ibtn" data-pop="pop-bell" aria-label="Notifikasi">{!! \App\Support\Icon::svg('bell') !!}@if ($unread > 0)<span class="dot">{{ $unread }}</span>@endif</button>
                <div class="pop hidden" id="pop-bell">
                    <div class="nt" style="font-weight:700;background:var(--panel2)"><span class="sp">Notifikasi</span>
                        <form method="POST" action="{{ route('notifications.read') }}" class="inline">@csrf<button class="btn sm ghost">Tandai dibaca</button></form></div>
                    @forelse ($me->notifications()->limit(8)->get() as $n)
                        <a class="nt {{ $n->read_at ? '' : 'un' }}" href="{{ route('notifications.open', $n->id) }}" style="text-decoration:none;color:inherit">
                            <div><div>{{ $n->data['text'] ?? '' }}</div><div class="xs muted">{{ $n->created_at->diffForHumans() }}</div></div></a>
                    @empty
                        <div class="nt muted">Belum ada notifikasi.</div>
                    @endforelse
                    <a class="nt" href="{{ route('notifications.index') }}" style="text-decoration:none;color:var(--pri);font-weight:700;justify-content:center">Lihat semua</a>
                </div>
            </div>
            <div style="position:relative">
                <button class="ibtn b" data-pop="pop-user" aria-label="Akun" style="font-family:var(--disp)">{{ $me->initials() }}</button>
                <div class="pop hidden" id="pop-user" style="width:250px">
                    <div class="nt"><div><b>{{ $me->name }}</b><div class="xs muted">{{ $me->email }}</div></div></div>
                    <form method="POST" action="{{ route('logout') }}" class="nt" style="padding:0">@csrf
                        <button class="btn ghost" style="width:100%;justify-content:flex-start;border-radius:0">{!! \App\Support\Icon::svg('logout', 16) !!} Keluar</button></form>
                </div>
            </div>
        </header>

        <main id="view">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
<div id="modal-root"></div>
<div class="toasts" id="toasts" aria-live="polite"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<script src="{{ asset('js/silabar.js') }}"></script>
@stack('scripts')
</body>
</html>