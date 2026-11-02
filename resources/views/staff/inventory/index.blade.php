@extends('layouts.app')
@section('title', 'Barang dan unit')
@section('subtitle', 'Stok, kondisi, dan label QR')
@php use App\Support\Charts; @endphp
@section('content')
<div class="panel">
    <div class="ph">
        <form method="GET" class="row" style="flex:1;min-width:260px"><input class="in" name="q" value="{{ $q }}" placeholder="Cari nama atau kode barang">
            <select class="sel" name="cat" style="width:auto" onchange="this.form.submit()"><option value="">Semua kategori</option>@foreach ($categories as $c)<option value="{{ $c->id }}" {{ (string) $cat === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>@endforeach</select></form>
        @if (auth()->user()->isAdmin())
            <button type="button" class="btn pri" data-open-modal="new-item">{!! \App\Support\Icon::svg('plus', 16) !!}Tambah barang</button>
        @endif
    </div>
    <div class="tw"><table class="t"><thead><tr><th>Barang</th><th>Kategori</th><th>Lokasi</th><th>Pelacakan</th><th class="right">Total</th><th>Tersedia sekarang</th><th>Persetujuan</th></tr></thead><tbody>
        @forelse ($rows as $r)
            @php $it = $r['item']; @endphp
            <tr class="click" onclick="location.href='{{ route('staff.inventory.show', $it) }}'">
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        @if ($it->photo_path)
                            <img src="{{ asset('storage/'.$it->photo_path) }}" alt="{{ $it->name }}" style="width:36px;height:36px;object-fit:cover;border-radius:6px">
                        @else
                            <div style="width:36px;height:36px;border-radius:6px;background:{{ $it->tileColor() }};display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:11px">{{ substr($it->sku, 0, 3) }}</div>
                        @endif
                        <div><div class="b">{{ $it->name }}</div><div class="xs muted">{{ $it->sku }}</div></div>
                    </div>
                </td>
                <td>{{ $it->category->name }}</td>
                <td>{{ $it->location->name }}</td>
                <td>{{ $it->isUnit() ? 'Per unit' : 'Jumlah' }}</td>
                <td class="right b">{{ $r['owned'] }}</td>
                <td style="min-width:130px"><div class="avl">{!! Charts::ring($r['a']['usable'] ? $r['a']['avail'] / $r['a']['usable'] : 0) !!}<b>{{ $r['a']['avail'] }}</b></div></td>
                <td>{!! $it->requires_approval ? '<span class="tag sun">Petugas</span>' : '<span class="tag moss">Langsung</span>' !!}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="text-align:center;padding:40px 16px" class="muted">
                    <div style="font-size:15px;font-weight:600;margin-bottom:6px">Belum ada barang di inventaris</div>
                    <div>Barang yang tersedia untuk dipinjam hanya barang resmi yang ditambahkan oleh Administrator.</div>
                    @if (auth()->user()->isAdmin())
                        <div style="margin-top:16px">
                            <button type="button" class="btn pri" data-open-modal="new-item">{!! \App\Support\Icon::svg('plus', 16) !!}Tambah Barang Sekarang</button>
                        </div>
                    @endif
                </td>
            </tr>
        @endforelse
    </tbody></table></div>
</div>
@if (auth()->user()->isAdmin())
<div id="new-item" class="hidden" data-cls="">
    <div class="mh"><div style="flex:1"><h3>Tambah barang</h3><div class="muted sm">Hanya Administrator yang memiliki wewenang menambahkan barang</div></div><button class="ibtn" data-close aria-label="Tutup">{!! \App\Support\Icon::svg('x') !!}</button></div>
    <form method="POST" action="{{ route('staff.inventory.store') }}" enctype="multipart/form-data">@csrf
        <div class="field"><label>Nama barang <span style="color:var(--brick)">*</span></label><input class="in" name="name" placeholder="Contoh: Proyektor Epson EB-X06" required></div>
        <div class="two">
            <div class="field"><label>Kode SKU / Singkatan</label><input class="in" name="sku" placeholder="Contoh: PRJ (kosongkan = otomatis)"></div>
            <div class="field"><label>Kategori <span style="color:var(--brick)">*</span></label><select class="sel" name="category_id" required>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            <div class="field"><label>Lokasi Penyimpanan <span style="color:var(--brick)">*</span></label><select class="sel" name="location_id" required>@foreach ($locations as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            <div class="field"><label>Metode Pelacakan <span style="color:var(--brick)">*</span></label><select class="sel" name="tracking_type"><option value="unit">Per unit (ada barcode / QR tiap unit)</option><option value="bulk">Jumlah massal (tanpa nomor seri unik)</option></select></div>
            <div class="field"><label>Jumlah unit / Stok awal <span style="color:var(--brick)">*</span></label><input class="in" type="number" min="1" max="5000" name="qty" value="1" required></div>
            <div class="field"><label>Maksimal hari pinjam <span style="color:var(--brick)">*</span></label><input class="in" type="number" min="1" max="30" name="max_loan_days" value="3" required></div>
            <div class="field"><label>Estimasi Nilai Ganti (Rp)</label><input class="in" type="number" min="0" name="replacement_value" value="500000" placeholder="0"></div>
            <div class="field"><label>Foto Barang (Opsional)</label><input class="in" type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div>
        </div>
        <div class="field"><label>Deskripsi / Spesifikasi Barang</label><textarea class="ta" name="description" rows="2" placeholder="Tuliskan keterangan detail atau kondisi spesifik barang..."></textarea></div>
        <label class="check-row" style="margin-bottom:16px"><input type="checkbox" name="requires_approval" value="1" checked> Perlu persetujuan Petugas sebelum dapat diambil</label>
        <div class="row" style="justify-content:flex-end"><button type="button" class="btn" data-close>Batal</button><button class="btn pri">{!! \App\Support\Icon::svg('plus', 16) !!} Simpan Barang</button></div>
    </form>
</div>
@endif
@endsection
