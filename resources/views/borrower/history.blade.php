@extends('layouts.app')
@section('title', 'Riwayat dan denda')
@section('subtitle', 'Catatan peminjaman yang sudah selesai')
@php use App\Support\Fmt; $tone = ['paid' => 'moss', 'unpaid' => 'brick', 'waived' => 'slate']; @endphp
@section('content')
<div class="g">
    <section class="panel c4 {{ $unpaid->count() ? '' : 'tint' }}" @if ($unpaid->count()) style="background:var(--brick-l)" @endif>
        <h3>Denda belum lunas</h3>
        <div class="kpi"><span class="v" data-count="{{ $unpaid->sum('amount') }}" data-fmt="rp">0</span><span class="l">{{ $unpaid->count() ? $unpaid->count().' tagihan. Bayar di loket Petugas agar bisa meminjam lagi.' : 'Tidak ada tagihan. Kamu bebas meminjam.' }}</span></div>
    </section>
    <section class="panel c8">
        <div class="ph"><div><h3>Rincian denda</h3><small>Dihitung otomatis saat pengembalian</small></div></div>
        <div class="tw"><table class="t"><thead><tr><th>Tanggal</th><th>Jenis</th><th>Tiket</th><th>Catatan</th><th class="right">Jumlah</th><th>Status</th></tr></thead><tbody>
            @forelse ($penalties as $p)
                <tr><td>{{ Fmt::d($p->created_at) }}</td><td><span class="tag {{ $p->type->value === 'late' ? 'sun' : 'brick' }}">{{ $p->type->label() }}</span></td><td>{{ $p->loan?->loan_code ?? '-' }}</td><td>{{ $p->note }}</td><td class="right b">{{ Fmt::rp($p->amount) }}</td><td><span class="tag {{ $tone[$p->status->value] }}">{{ $p->status->label() }}</span></td></tr>
            @empty
                <tr><td colspan="6" class="muted">Belum ada denda.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
    <section class="panel c12">
        <div class="ph"><div><h3>Riwayat peminjaman</h3><small>{{ $loans->count() }} tiket selesai, ditolak, atau dibatalkan</small></div></div>
        <div class="tw"><table class="t"><thead><tr><th>Tiket</th><th>Barang</th><th>Jadwal</th><th>Status</th><th></th></tr></thead><tbody>
            @foreach ($loans as $l)
                <tr><td class="mono-s">{{ $l->loan_code }}</td><td>{{ $l->itemsSummary() }}</td><td>{{ $l->start_at->translatedFormat('j M') }} sampai {{ $l->due_at->translatedFormat('j M') }}</td>
                    <td><span class="tag {{ $l->status->tone() }}">{{ $l->status->label() }}</span>@if ($l->late_days) <span class="xs muted">telat {{ $l->late_days }} hari</span>@endif</td>
                    <td>@if ($l->handed_over_at)<a class="btn sm ghost" href="{{ route('loans.doc', $l) }}" target="_blank">Berita acara</a>@endif</td></tr>
            @endforeach
        </tbody></table></div>
    </section>
</div>
@endsection
