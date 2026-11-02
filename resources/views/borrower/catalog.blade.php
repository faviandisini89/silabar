@extends('layouts.app')
@section('title', 'Katalog')
@section('subtitle', 'Pilih barang sesuai jadwalmu')
@php use App\Support\Fmt; @endphp
@section('content')
<div class="cat-wrap">
    <div>
        <section class="panel" style="margin-bottom:16px">
            <form method="GET" action="{{ route('catalog') }}">
                <div class="fbar">
                    <div class="field"><label for="start">Mulai</label><input class="in" id="start" name="start" type="datetime-local" value="{{ $start->format('Y-m-d\TH:i') }}"></div>
                    <div class="field"><label for="end">Selesai</label><input class="in" id="end" name="end" type="datetime-local" value="{{ $end->format('Y-m-d\TH:i') }}"></div>
                    <div class="field"><label for="q">Cari barang</label><input class="in" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Ketik nama barang"></div>
                </div>
                <div class="row wrap">
                    <div class="chips sp">
                        <a class="chip {{ ! $filters['cat'] ? 'on' : '' }}" href="{{ route('catalog', array_filter(['q' => $filters['q'], 'only' => $filters['only'] ?: null])) }}">Semua</a>
                        @foreach ($categories as $c)<a class="chip {{ $filters['cat'] == $c->id ? 'on' : '' }}" href="{{ route('catalog', array_filter(['cat' => $c->id, 'q' => $filters['q'], 'only' => $filters['only'] ?: null])) }}" style="text-decoration:none">{{ $c->name }}</a>@endforeach
                    </div>
                    <label class="check-row"><input type="checkbox" name="only" value="1" {{ $filters['only'] ? 'checked' : '' }}> Hanya yang tersedia</label>
                    @if ($filters['cat'])<input type="hidden" name="cat" value="{{ $filters['cat'] }}">@endif
                    <button class="btn sm pri">Terapkan</button>
                </div>
                <div class="hint" style="margin-top:8px">{{ $validWindow ? "Durasi {$days} hari. Angka ketersediaan di setiap kartu mengikuti rentang waktu ini." : 'Waktu selesai harus setelah waktu mulai.' }}</div>
            </form>
        </section>

        <div class="igrid">
            @forelse ($rows as $r)
                @php($it = $r['item'])@php($a = $r['a'])
                <article class="item">
                    <div class="tile" style="background:linear-gradient(135deg,{{ $it->tileColor() }},{{ $it->tileColor() }}D0)" data-calendar="{{ route('catalog.calendar', $it) }}" data-title="{{ $it->name }}">
                        @if ($it->photo_path)
                            <img src="{{ asset('storage/'.$it->photo_path) }}" alt="{{ $it->name }}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit">
                        @else
                            <span class="bc">@for ($k = 0; $k < 16; $k++)<i style="height:{{ 5 + (($k * 7 + $it->tile * 5) % 11) }}px"></i>@endfor</span>
                            <span class="mono">{{ substr($it->sku, 0, 3) }}</span><span class="cat">{{ $it->category->name }}</span>
                        @endif
                    </div>
                    <div class="ib">
                        <h4>{{ $it->name }}</h4>
                        <div class="row wrap" style="gap:5px">{!! $it->requires_approval ? '<span class="tag sun">Perlu persetujuan</span>' : '<span class="tag moss">Langsung disetujui</span>' !!}<span class="tag slate">Maks {{ $it->max_loan_days }} hari</span></div>
                        <div class="avl">{!! \App\Support\Charts::ring($a['usable'] ? $a['avail'] / $a['usable'] : 0) !!}<b>{{ $a['avail'] }}</b><span class="muted">dari {{ $a['usable'] }}</span></div>
                        <form method="POST" action="{{ route('cart.add') }}" class="row" style="margin-top:auto">
                            @csrf <input type="hidden" name="item_id" value="{{ $it->id }}">
                            @if ($a['avail'] > 0)
                                <div class="qty"><button type="button" data-step="-1" data-target="#q-{{ $it->id }}" aria-label="Kurangi">−</button><input id="q-{{ $it->id }}" name="qty" type="number" min="1" value="{{ $cartQty[$it->id] ?? 1 }}" aria-label="Jumlah"><button type="button" data-step="1" data-target="#q-{{ $it->id }}" aria-label="Tambah">+</button></div>
                                <button class="btn pri sm sp">{{ isset($cartQty[$it->id]) ? 'Perbarui' : 'Tambah' }}</button>
                            @else
                                <input type="hidden" name="qty" value="1">
                                <button class="btn sm sp" title="Tetap bisa diajukan; kebutuhan akan dicatat">Ajukan juga</button>
                            @endif
                        </form>
                    </div>
                </article>
            @empty
                <div class="panel" style="grid-column:1/-1"><div class="empty"><b>Tidak ada barang yang cocok</b>Ubah kata kunci atau matikan filter ketersediaan.</div></div>
            @endforelse
        </div>
    </div>

    <aside class="sticky cartcol stack">
        <section class="panel cart">
            <h3 style="margin-bottom:6px">Keranjang peminjaman</h3>
            @if ($cartItems->count())
                @foreach ($cartItems as $it)
                    <div class="ln">
                        <div style="flex:1;min-width:0"><div class="b sm">{{ $it->name }}</div><div class="xs muted">{{ $it->max_loan_days }} hari maks</div></div>
                        <form method="POST" action="{{ route('cart.update', $it) }}" class="row" style="gap:4px">@csrf @method('PATCH')
                            <div class="qty"><input name="qty" type="number" min="1" value="{{ $cartQty[$it->id] }}" aria-label="Jumlah" onchange="this.form.submit()"></div></form>
                        <form method="POST" action="{{ route('cart.remove', $it) }}">@csrf @method('DELETE')<button class="ibtn" style="width:30px;height:30px" aria-label="Hapus">{!! \App\Support\Icon::svg('x', 15) !!}</button></form>
                    </div>
                @endforeach
                <form method="POST" action="{{ route('loans.store') }}">
                    @csrf
                    <div class="field" style="margin-top:10px"><label for="purpose">Tujuan peminjaman</label><textarea class="ta" id="purpose" name="purpose" placeholder="Contoh: Seminar himpunan tanggal ..." required>{{ old('purpose') }}</textarea></div>
                    <div class="sm" style="margin-bottom:10px">{{ Fmt::dt($start) }} sampai {{ Fmt::dt($end) }}</div>
                    <div class="tag {{ $needsApproval ? 'sun' : 'moss' }}" style="margin-bottom:10px">{{ $needsApproval ? 'Akan menunggu persetujuan Petugas' : 'Akan disetujui otomatis' }}</div>
                    <button class="btn pri" style="width:100%">Periksa dan ajukan</button>
                </form>
            @else
                <div class="empty" style="min-height:130px">{!! \App\Support\Icon::svg('ticket', 28) !!}<b>Keranjang kosong</b>Tambahkan barang dari daftar, lalu ajukan sekaligus.</div>
            @endif
        </section>
        <div class="sticky-note"><b>Gagal dapat barang?</b><br>Permintaan yang tidak terpenuhi dicatat otomatis, jadi Admin tahu barang apa yang perlu ditambah.</div>
        <section class="panel flat"><h3 style="font-size:15px;margin-bottom:6px">Sebelum mengajukan</h3><ul class="sm" style="margin:0;padding-left:18px"><li>Sistem memeriksa stok, jadwal, denda, dan durasi.</li><li>Barang bertanda kuning menunggu Petugas.</li><li>Kamu mendapat tiket QR untuk mengambil barang.</li></ul></section>
    </aside>
</div>
@endsection
