@extends('layouts.app')
@section('title', $item->name)
@section('subtitle', $item->sku.', '.$item->category->name)
@section('content')
<div class="g">
    <section class="panel c5">
        <div class="ph"><h3>Data barang</h3><span class="sp"></span><a class="btn sm" href="{{ route('staff.inventory') }}">Kembali</a></div>
        @if ($item->photo_path)
            <div style="margin-bottom:16px;border-radius:var(--rad);overflow:hidden;border:1px solid var(--border)">
                <img src="{{ asset('storage/'.$item->photo_path) }}" alt="{{ $item->name }}" style="width:100%;height:180px;object-fit:cover;display:block">
            </div>
        @endif
        @if (auth()->user()->isAdmin())
            <form method="POST" action="{{ route('staff.inventory.update', $item) }}" enctype="multipart/form-data">@csrf @method('PUT')
                <div class="field"><label>Nama</label><input class="in" name="name" value="{{ old('name', $item->name) }}" required></div>
                <div class="two">
                    <div class="field"><label>Kategori</label><select class="sel" name="category_id">@foreach ($categories as $c)<option value="{{ $c->id }}" {{ $item->category_id === $c->id ? 'selected' : '' }}>{{ $c->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Lokasi</label><select class="sel" name="location_id">@foreach ($locations as $c)<option value="{{ $c->id }}" {{ $item->location_id === $c->id ? 'selected' : '' }}>{{ $c->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Maks hari</label><input class="in" type="number" min="1" name="max_loan_days" value="{{ $item->max_loan_days }}"></div>
                    <div class="field"><label>Nilai ganti (Rp)</label><input class="in" type="number" min="0" name="replacement_value" value="{{ (int) $item->replacement_value }}"></div>
                </div>
                @unless ($item->isUnit())<div class="field" style="max-width:220px"><label>Jumlah stok</label><input class="in" type="number" min="0" name="total_stock" value="{{ $item->total_stock }}"></div>@endunless
                <div class="field"><label>Ganti Foto (Opsional)</label><input class="in" type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div>
                <div class="field"><label>Deskripsi</label><textarea class="ta" name="description" rows="2">{{ old('description', $item->description) }}</textarea></div>
                <label class="check-row" style="margin-bottom:12px"><input type="checkbox" name="requires_approval" value="1" {{ $item->requires_approval ? 'checked' : '' }}> Perlu persetujuan Petugas</label>
                <button class="btn pri">Simpan Perubahan</button>
            </form>
        @else
            <div class="stack" style="gap:12px">
                <div><div class="xs muted">Kategori</div><div class="b">{{ $item->category->name }}</div></div>
                <div><div class="xs muted">Lokasi Penyimpanan</div><div class="b">{{ $item->location->name }}</div></div>
                <div><div class="xs muted">Metode Pelacakan</div><div>{{ $item->isUnit() ? 'Per unit (dengan kode aset unik)' : 'Jumlah massal' }}</div></div>
                <div><div class="xs muted">Maksimal Hari Pinjam</div><div>{{ $item->max_loan_days }} hari</div></div>
                <div><div class="xs muted">Nilai Penggantian</div><div>Rp{{ number_format($item->replacement_value, 0, ',', '.') }}</div></div>
                <div><div class="xs muted">Persetujuan</div><div>{!! $item->requires_approval ? '<span class="tag sun">Perlu persetujuan Petugas</span>' : '<span class="tag moss">Peminjaman langsung</span>' !!}</div></div>
                @if ($item->description)<div><div class="xs muted">Deskripsi</div><div class="sm">{{ $item->description }}</div></div>@endif
            </div>
        @endif
    </section>
    <section class="panel c7">
        @if ($item->isUnit())
            <div class="ph"><div><h3>Unit ({{ $item->units->count() }})</h3><small>Setiap unit punya label QR sendiri</small></div><span class="sp"></span><a class="btn sm" target="_blank" href="{{ route('staff.inventory.labels', $item) }}">{!! \App\Support\Icon::svg('qr', 15) !!}Cetak semua label</a></div>
            <div class="tw" style="max-height:340px;overflow-y:auto"><table class="t"><tbody>
                @foreach ($item->units as $u)
                    <tr><td class="mono-s">{{ $u->asset_code }}</td><td>{{ $u->condition->label() }}</td>
                        <td>@if (in_array($u->id, $busy)) <span class="tag pri">Dipinjam</span> @else <span class="tag {{ ['available' => 'moss', 'maintenance' => 'sun', 'lost' => 'brick', 'loaned' => 'pri'][$u->status->value] }}">{{ $u->status->label() }}</span>@endif</td>
                        <td class="right"><a class="btn sm ghost" target="_blank" href="{{ route('staff.inventory.labels', [$item, 'unit' => $u->id]) }}">QR</a>
                            @if ($u->status->value === 'available' && ! in_array($u->id, $busy))
                                <button type="button" class="btn sm ghost" data-open-modal="rep-{{ $u->id }}">Lapor rusak</button>
                                <div id="rep-{{ $u->id }}" class="hidden" data-cls="narrow"><div class="mh"><div style="flex:1"><h3>Laporkan kerusakan</h3><div class="muted sm">{{ $u->asset_code }}</div></div><button class="ibtn" data-close>{!! \App\Support\Icon::svg('x') !!}</button></div>
                                    <form method="POST" action="{{ route('staff.units.repair', $u) }}">@csrf<div class="field"><label>Masalah</label><textarea class="ta" name="issue" required></textarea></div><div class="row" style="justify-content:flex-end"><button type="button" class="btn" data-close>Batal</button><button class="btn pri">Simpan laporan</button></div></form></div>
                            @endif</td></tr>
                @endforeach
            </tbody></table></div>
            @if (auth()->user()->isAdmin())
                <form method="POST" action="{{ route('staff.inventory.units', $item) }}" class="row" style="margin-top:10px">@csrf<input class="in" type="number" min="1" name="qty" value="1" style="width:90px"><button class="btn sm">Tambah unit</button></form>
            @endif
        @else
            <div class="ph"><h3>Barang massal</h3></div><p class="muted sm">Barang ini dihitung per jumlah, bukan per unit. Stok sekarang: <b>{{ $item->total_stock }}</b>.@if (auth()->user()->isAdmin()) Ubah lewat kolom jumlah stok di samping.@endif</p>
        @endif
    </section>
</div>
@endsection
