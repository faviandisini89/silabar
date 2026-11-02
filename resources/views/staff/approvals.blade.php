@extends('layouts.app')
@section('title', 'Persetujuan')
@section('subtitle', 'Pengajuan dan perpanjangan yang menunggu')
@php use App\Support\{Charts, Fmt}; @endphp
@section('content')
<div class="split">
    <aside class="stack">
        @forelse ($queue as $x)
            <a class="qitem {{ $sel && $sel['key'] === $x['key'] ? 'on' : '' }}" href="{{ route('staff.approvals', ['pilih' => $x['key']]) }}" style="text-decoration:none;color:inherit">
                <div class="row"><span class="tag {{ $x['type'] === 'Pengajuan' ? 'sun' : 'sky' }}">{{ $x['type'] }}</span><span class="sp"></span><span class="xs muted">{{ $x['at']->diffForHumans() }}</span></div>
                <div class="b">{{ $x['loan']->borrower->name }}</div><div class="sm">{{ $x['loan']->itemsSummary() }}</div>
            </a>
        @empty
            <div class="panel"><div class="empty">{!! \App\Support\Icon::svg('check', 28) !!}<b>Antrian kosong</b>Semua pengajuan sudah diputuskan.</div></div>
        @endforelse
    </aside>
    <section>
        @if ($sel)
            @php $loan = $sel['loan']; $u = $loan->borrower; @endphp
            @if ($sel['type'] === 'Perpanjangan')
                @php $ext = $sel['ext']; @endphp
                <div class="panel">
                    <h3>Perpanjangan {{ $loan->loan_code }}</h3><p class="muted sm">{{ $u->name }}, {{ $loan->itemsSummary() }}</p>
                    <dl class="kv"><dt>Jatuh tempo sekarang</dt><dd>{{ Fmt::dt($loan->due_at) }}</dd><dt>Diminta</dt><dd>+{{ $ext->days }} hari menjadi {{ Fmt::dt($loan->due_at->copy()->addDays($ext->days)) }}</dd><dt>Alasan</dt><dd>{{ $ext->reason }}</dd></dl>
                    @foreach ($detail['checks'] as $c)<div class="chk {{ $c['ok'] ? 'ok' : 'no' }}"><span class="ic">{{ $c['ok'] ? '✓' : '✕' }}</span><div><b>{{ $c['item']->name }} ×{{ $c['qty'] }}</b><div class="xs muted">Tersedia {{ $c['a']['avail'] }} dari {{ $c['a']['usable'] }} pada masa perpanjangan</div></div></div>@endforeach
                    <hr class="dash">
                    <form method="POST" action="{{ route('staff.extensions.decide', $ext) }}">@csrf
                        <div class="field"><label for="note">Alasan jika ditolak</label><input class="in" id="note" name="note" placeholder="Wajib diisi saat menolak"></div>
                        <div class="row"><button class="btn bad" name="decision" value="reject">Tolak</button><span class="sp"></span><button class="btn pri" name="decision" value="approve">Setujui perpanjangan</button></div></form>
                </div>
            @else
                <div class="panel">
                    <div class="row wrap"><h3 style="flex:1">{{ $loan->loan_code }}</h3><span class="stamp st-pending">Menunggu</span></div>
                    <div class="g" style="margin-top:12px;gap:12px">
                        <div class="c6 panel flat"><div class="b">Peminjam</div><div>{{ $u->name }}</div><div class="xs muted">{{ $u->nim }}, {{ $u->organization }}</div>
                            <div class="row" style="margin-top:8px">{!! Charts::gauge($u->trust_score, 64) !!}<div class="sm">Trust score<br><span class="muted">{{ $detail['late'] }} terlambat dari {{ $detail['returned'] }} pinjaman selesai</span></div></div>
                            @if ($detail['unpaid'] > 0)<div class="tag brick" style="margin-top:6px">Ada denda {{ Fmt::rp($detail['unpaid']) }}</div>@endif</div>
                        <div class="c6 panel flat"><div class="b">Perkiraan risiko telat</div>
                            @if ($detail['risk'] !== null)
                                <div class="row" style="margin:6px 0"><div style="flex:1">{!! Charts::ring($detail['risk'], true) !!}</div><b>{{ Fmt::pct($detail['risk']) }}</b></div>
                                <div class="xs muted">Model machine learning ({{ $detail['model'] }}). Dihitung dari riwayat telat, durasi, jeda pengajuan, dan jumlah barang.</div>
                            @else
                                <div class="xs muted" style="margin-top:6px">Belum ada skor. Skor muncul setelah analisis Python berjalan (<code>php artisan analytics:run</code>).</div>
                            @endif</div>
                    </div>
                    <div class="b" style="margin:14px 0 4px">Barang dan ketersediaan pada jadwalnya</div>
                    @foreach ($detail['checks'] as $c)<div class="chk {{ $c['ok'] ? 'ok' : 'no' }}"><span class="ic">{{ $c['ok'] ? '✓' : '✕' }}</span><div><b>{{ $c['item']->name }} ×{{ $c['qty'] }}</b><div class="xs muted">Tersedia {{ $c['a']['avail'] }} dari {{ $c['a']['usable'] }} pada {{ $loan->start_at->translatedFormat('j M') }} sampai {{ $loan->due_at->translatedFormat('j M') }}</div></div></div>@endforeach
                    <dl class="kv" style="margin-top:8px"><dt>Jadwal</dt><dd>{{ Fmt::dt($loan->start_at) }} sampai {{ Fmt::dt($loan->due_at) }}</dd><dt>Tujuan</dt><dd>{{ $loan->purpose }}</dd></dl>
                    <hr class="dash">
                    <div class="row" style="align-items:flex-end">
                        <form method="POST" action="{{ route('staff.approvals.reject', $loan) }}" style="flex:1" class="row">@csrf
                            <div class="field" style="flex:1;margin:0"><label for="reason">Alasan jika ditolak</label><input class="in" id="reason" name="reason" placeholder="Wajib diisi saat menolak"></div><button class="btn bad">Tolak</button></form>
                        <form method="POST" action="{{ route('staff.approvals.approve', $loan) }}">@csrf<button class="btn pri">Setujui dan cadangkan stok</button></form>
                    </div>
                </div>
            @endif
        @else
            <div class="panel"><div class="empty"><b>Tidak ada yang perlu diputuskan</b>Pengajuan baru akan muncul di sini dan di notifikasi.</div></div>
        @endif
    </section>
</div>
@endsection
