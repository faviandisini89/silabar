@extends('layouts.app')
@section('title', 'Denda')
@section('subtitle', 'Tagihan keterlambatan, kerusakan, kehilangan')
@php use App\Support\Fmt; @endphp
@section('content')
<div class="g">
    <div class="c4 panel kpi {{ $unpaid->count() ? 'bad' : '' }}"><span class="l">Belum lunas</span><span class="v" data-count="{{ $unpaid->sum('amount') }}" data-fmt="rp" style="font-size:28px">0</span><span class="xs muted">{{ $unpaid->count() }} tagihan dari {{ $unpaid->pluck('user_id')->unique()->count() }} peminjam</span></div>
    <div class="c4 panel kpi"><span class="l">Lunas 30 hari terakhir</span><span class="v" data-count="{{ $paid30 }}" data-fmt="rp" style="font-size:28px">0</span></div>
    <div class="c4 panel kpi warn"><span class="l">Tarif keterlambatan</span><span class="v" style="font-size:28px">{{ Fmt::rp($rates['penalty.late_per_day']) }}</span><span class="xs muted">per unit per hari. Rusak {{ $rates['penalty.damaged_pct'] }}% nilai ganti, hilang 100%.</span></div>
    <section class="panel c12">
        <div class="tabs">@foreach ([['unpaid', 'Belum lunas', $unpaid->count()], ['paid', 'Selesai', $countAll - $unpaid->count()], ['all', 'Semua', $countAll]] as [$k, $label, $n])<a href="{{ route('staff.penalties', ['tab' => $k]) }}" style="text-decoration:none"><button type="button" class="{{ $tab === $k ? 'on' : '' }}">{{ $label }}<span class="n">{{ $n }}</span></button></a>@endforeach</div>
        <div class="tw"><table class="t"><thead><tr><th>Tanggal</th><th>Peminjam</th><th>Tiket</th><th>Jenis</th><th>Catatan</th><th class="right">Jumlah</th><th></th></tr></thead><tbody>
            @forelse ($list as $p)
                <tr><td>{{ Fmt::d($p->created_at) }}</td><td>{{ $p->user->name }}</td><td class="mono-s">{{ $p->loan?->loan_code ?? '-' }}</td><td><span class="tag {{ $p->type->value === 'late' ? 'sun' : 'brick' }}">{{ $p->type->label() }}</span></td><td>{{ $p->note }}</td><td class="right b">{{ Fmt::rp($p->amount) }}</td>
                    <td class="right">@if ($p->status->value === 'unpaid')
                        <form class="inline" method="POST" action="{{ route('staff.penalties.paid', $p) }}">@csrf<button class="btn sm pri">Tandai lunas</button></form>
                        <form class="inline" method="POST" action="{{ route('staff.penalties.waive', $p) }}" data-confirm="Hapuskan denda ini?">@csrf<button class="btn sm ghost">Hapuskan</button></form>
                    @else<span class="tag {{ $p->status->value === 'paid' ? 'moss' : 'slate' }}">{{ $p->status->label() }}</span>@endif</td></tr>
            @empty
                <tr><td colspan="7" class="muted">Tidak ada tagihan.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
</div>
@endsection
