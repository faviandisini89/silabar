@extends('layouts.app')
@section('title', 'Pengadaan')
@section('subtitle', 'Saran penambahan barang dari permintaan yang gagal')
@php use App\Support\Fmt; @endphp
@section('content')
<div class="g">
    <div class="c3 panel kpi warn"><span class="l">Menunggu keputusan</span><span class="v" data-count="{{ $kpi['open'] }}">0</span></div>
    <div class="c3 panel kpi"><span class="l">Fill rate {{ $window }} hari</span><span class="v" data-count="{{ round($kpi['fill'] * 100) }}" data-fmt="pct">0</span></div>
    <div class="c3 panel kpi"><span class="l">Permintaan gagal</span><span class="v" data-count="{{ $kpi['unmet'] }}">0</span></div>
    <div class="c3 panel kpi"><span class="l">Unit disarankan</span><span class="v" data-count="{{ $kpi['units'] }}">0</span></div>

    <section class="panel c8">
        <div class="tabs">@foreach ([['open', 'Menunggu'], ['dec', 'Sudah diputuskan'], ['ful', 'Terlaksana']] as [$k, $label])<a href="{{ route('admin.procure', ['tab' => $k]) }}" style="text-decoration:none"><button type="button" class="{{ $tab === $k ? 'on' : '' }}">{{ $label }}<span class="n">{{ $counts[$k] }}</span></button></a>@endforeach</div>
        <div class="stack">
            @forelse ($list as $r)
                @php $it = $r->item; $rl = $logs->get($r->item_id, collect())->take(4); @endphp
                <article class="rec {{ $r->cause->value }}">
                    <div>
                        <div class="row wrap"><h3 style="font-size:19px">{{ $it->name }}</h3><span class="tag {{ $r->cause->tone() }}">{{ $r->cause->label() }}</span></div>
                        <p class="sm" style="margin:6px 0 10px">{{ $r->rationale }}</p>
                        <div class="mgrid"><div><b>{{ $r->unmet_count }}</b><span>gagal, {{ $window }} hari</span></div><div><b>{{ $r->affected_users }}</b><span>peminjam terdampak</span></div><div><b>{{ Fmt::pct($r->fill_rate) }}</b><span>fill rate</span></div><div><b>{{ $r->peak_shortage_qty }}</b><span>kekurangan puncak</span></div><div><b>{{ $r->recommended_add_qty ? '+'.$r->recommended_add_qty : '0' }}</b><span>unit disarankan</span></div><div><b>{{ Fmt::pct($r->priority_score) }}</b><span>skor prioritas</span></div></div>
                        <details style="margin-top:10px"><summary class="sm b" style="cursor:pointer">Permintaan gagal terbaru</summary>
                            <table class="t" style="margin-top:6px"><tbody>@foreach ($rl as $u)<tr><td>{{ $u->created_at->translatedFormat('j M') }}</td><td>{{ $u->user->name }}</td><td>{{ $u->requested_qty }} diminta, {{ $u->available_qty }} tersedia</td><td><span class="pill">{{ $u->reason->label() }}</span></td></tr>@endforeach</tbody></table></details>
                    </div>
                    <div>
                        @if ($r->status->value === 'open')
                            <div class="stack">
                                @foreach ([['approved', 'Setujui', 'pri'], ['deferred', 'Tunda', ''], ['rejected', 'Tolak', 'ghost']] as [$st, $label, $cls])
                                    <button type="button" class="btn {{ $cls }}" style="width:100%" data-open-modal="d-{{ $r->id }}-{{ $st }}">{{ $label }}</button>
                                    <div id="d-{{ $r->id }}-{{ $st }}" class="hidden" data-cls="narrow"><div class="mh"><div style="flex:1"><h3>{{ $label }} rekomendasi</h3><div class="muted sm">{{ $it->name }}</div></div><button class="ibtn" data-close>{!! \App\Support\Icon::svg('x') !!}</button></div>
                                        <p class="sm" style="margin:0 0 10px">{{ $r->rationale }}</p>
                                        <form method="POST" action="{{ route('admin.procure.decide', $r) }}">@csrf<input type="hidden" name="status" value="{{ $st }}"><div class="field"><label>Catatan untuk riwayat (opsional)</label><textarea class="ta" name="note" placeholder="Contoh: menunggu anggaran semester depan"></textarea></div><div class="row" style="justify-content:flex-end"><button type="button" class="btn" data-close>Batal</button><button class="btn {{ $st === 'rejected' ? 'bad' : ($st === 'deferred' ? 'sun' : 'pri') }}">{{ $label }}</button></div></form></div>
                                @endforeach
                            </div>
                        @elseif ($r->status->value === 'approved')
                            <form method="POST" action="{{ route('admin.procure.fulfill', $r) }}" class="stack">@csrf<span class="tag sky">Disetujui</span>
                                <div class="field" style="margin:0"><label>Jumlah ditambahkan</label><input class="in" type="number" min="0" name="qty" value="{{ $r->recommended_add_qty }}"></div>
                                <button class="btn sun">Catat penambahan</button><div class="hint">Isi 0 jika tindak lanjutnya perbaikan atau aturan, bukan membeli.</div></form>
                        @elseif ($r->status->value === 'fulfilled')
                            @php $f = $after[$r->id] ?? null; $days = $r->fulfilled_at->diffInDays(now()); @endphp
                            <div class="stack"><span class="tag moss">Terlaksana</span><div class="sm">Fill rate sebelum <b>{{ Fmt::pct($r->fill_before ?? $r->fill_rate) }}</b></div><div class="sm">Sesudah: <b>{{ $f && ($days >= 7 || $f['ok'] + $f['unmet'] >= 5) ? Fmt::pct($f['fill']) : 'masih dipantau' }}</b></div></div>
                        @else
                            <div class="stack"><span class="tag {{ $r->status->value === 'deferred' ? 'sun' : 'slate' }}">{{ $r->status->value === 'deferred' ? 'Ditunda' : 'Ditolak' }}</span><div class="xs muted">{{ $r->admin_note ?: 'Tanpa catatan' }}</div></div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty">{!! \App\Support\Icon::svg('check', 28) !!}<b>Tidak ada rekomendasi di sini</b>Analisis berjalan tiap minggu, atau jalankan sekarang.</div>
            @endforelse
        </div>
    </section>

    <aside class="c4" style="display:flex;flex-direction:column;gap:16px;align-self:start;position:sticky;top:calc(env(safe-area-inset-top,0px) + 74px)">
        <section class="panel tint"><h3>Cara analisis bekerja</h3>
            <ol class="sm" style="padding-left:18px;margin:8px 0 12px"><li>Setiap permintaan yang gagal karena stok habis atau jadwal bentrok dicatat.</li><li>Tiap minggu layanan Python menghitung fill rate, peminjam terdampak, dan kekurangan puncak per barang.</li><li>Jika melewati ambang, ia mendiagnosis penyebabnya: stok kurang, unit rusak, telat kembali, atau jam padat.</li><li>Kamu memutuskan. Hasilnya dipantau setelah stok ditambah.</li></ol>
            <form method="POST" action="{{ route('admin.procure.run') }}">@csrf<button class="btn pri">{!! \App\Support\Icon::svg('refresh', 16) !!}Jalankan analisis sekarang</button></form>
            <div class="xs muted" style="margin-top:6px">Terakhir dijalankan {{ $lastRun ? $lastRun->diffForHumans() : 'belum pernah' }}</div></section>
        <section class="panel"><h3 style="margin-bottom:6px">Ambang saat ini</h3>
            <dl class="kv sm"><dt>Pemicu</dt><dd>{{ $s['procure.min_unmet'] }}+ gagal dari {{ $s['procure.min_users'] }}+ peminjam, atau fill rate di bawah {{ $s['procure.fill_below'] }}%</dd><dt>Jendela</dt><dd>{{ $window }} hari</dd><dt>Unit rusak</dt><dd>{{ $s['procure.maint_share'] }}% atau lebih</dd><dt>Bobot prioritas</dt><dd>{{ $s['procure.w_unmet'] }}, {{ $s['procure.w_users'] }}, {{ $s['procure.w_util'] }}, {{ $s['procure.w_trend'] }}</dd></dl>
            <a class="btn sm" style="margin-top:8px" href="{{ route('admin.settings') }}">Ubah di Pengaturan</a></section>
    </aside>
</div>
@endsection
