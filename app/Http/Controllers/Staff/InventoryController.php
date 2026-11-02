<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Location;
use App\Services\AuditLogger;
use App\Services\AvailabilityService;
use App\Services\ItemService;
use App\Services\LoanService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request, AvailabilityService $availability)
    {
        $q = trim((string) $request->query('q'));
        $items = Item::with(['category', 'location'])->when($request->query('cat'), fn ($x, $c) => $x->where('category_id', $c))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")))->orderBy('name')->get()
            ->map(fn ($i) => ['item' => $i, 'owned' => $i->ownedCount(), 'a' => $availability->availability($i, now(), now()->addHour())]);

        return view('staff.inventory.index', ['rows' => $items, 'categories' => Category::orderBy('name')->get(), 'locations' => Location::orderBy('name')->get(), 'q' => $q, 'cat' => $request->query('cat')]);
    }

    public function store(Request $request, ItemService $svc, AuditLogger $audit)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Hanya Admin yang berhak menambahkan barang baru.');

        $d = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:20', 'unique:items,sku'],
            'category_id' => ['required', 'exists:categories,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'tracking_type' => ['required', 'in:unit,bulk'],
            'qty' => ['required', 'integer', 'min:1', 'max:5000'],
            'max_loan_days' => ['required', 'integer', 'min:1', 'max:30'],
            'replacement_value' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'requires_approval' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ], [
            'name.required' => 'Nama barang wajib diisi.',
            'category_id.required' => 'Pilih kategori barang.',
            'location_id.required' => 'Pilih lokasi barang.',
            'tracking_type.required' => 'Pilih metode pelacakan barang.',
            'qty.required' => 'Jumlah barang wajib diisi minimal 1.',
            'max_loan_days.required' => 'Maksimal hari peminjaman wajib diisi.',
            'sku.unique' => 'Kode SKU sudah digunakan oleh barang lain.',
            'photo.image' => 'File foto harus berupa gambar valid (JPG, PNG, WebP).',
            'photo.max' => 'Ukuran file foto maksimal 2 MB.',
        ]);

        $payload = [
            'name' => $d['name'],
            'sku' => !empty($d['sku']) ? strtoupper(trim($d['sku'])) : null,
            'category_id' => $d['category_id'],
            'location_id' => $d['location_id'],
            'tracking_type' => $d['tracking_type'],
            'max_loan_days' => (int) $d['max_loan_days'],
            'replacement_value' => $d['replacement_value'] ?? 0,
            'requires_approval' => $request->boolean('requires_approval'),
            'description' => $d['description'] ?? null,
        ];

        if ($request->hasFile('photo')) {
            $payload['photo_path'] = $request->file('photo')->store('items', 'public');
        }

        $item = $svc->createItem($payload, (int) $d['qty']);
        $audit->log('Menambah barang', $item->name, $d['qty'].' '.$d['tracking_type']);

        return redirect()->route('staff.inventory.show', $item)->with('ok', 'Barang berhasil ditambahkan ke inventaris.');
    }

    public function show(Item $item)
    {
        $busy = \App\Models\LoanDetail::whereIn('item_unit_id', $item->units()->pluck('id'))->whereHas('loan', fn ($q) => $q->whereIn('status', ['borrowed', 'overdue']))->pluck('item_unit_id')->all();

        return view('staff.inventory.show', ['item' => $item->load(['category', 'location', 'units']), 'busy' => $busy, 'categories' => Category::orderBy('name')->get(), 'locations' => Location::orderBy('name')->get()]);
    }

    public function update(Request $request, Item $item, AuditLogger $audit)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Hanya Admin yang dapat mengubah data barang.');

        $d = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'max_loan_days' => ['required', 'integer', 'min:1', 'max:30'],
            'replacement_value' => ['nullable', 'numeric', 'min:0'],
            'total_stock' => ['nullable', 'integer', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        $item->fill([
            'name' => $d['name'],
            'max_loan_days' => $d['max_loan_days'],
            'replacement_value' => $d['replacement_value'] ?? 0,
            'category_id' => $d['category_id'],
            'location_id' => $d['location_id'],
            'description' => $d['description'] ?? $item->description,
            'requires_approval' => $request->boolean('requires_approval'),
        ]);

        if ($request->hasFile('photo')) {
            $item->photo_path = $request->file('photo')->store('items', 'public');
        }

        if (! $item->isUnit() && isset($d['total_stock'])) {
            $item->total_stock = $d['total_stock'];
        }
        $item->save();
        $audit->log('Mengubah data barang', $item->name);

        return back()->with('ok', 'Perubahan disimpan.');
    }

    public function addUnits(Request $request, Item $item, ItemService $svc, AuditLogger $audit)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Hanya Admin yang dapat menambah unit barang.');
        abort_unless($item->isUnit(), 422, 'Barang massal diubah lewat jumlah stok.');
        $n = (int) $request->validate(['qty' => ['required', 'integer', 'min:1', 'max:500']])['qty'];
        $svc->addUnits($item, $n);
        $audit->log('Menambah unit', $item->name, '+'.$n);

        return back()->with('ok', "{$n} unit ditambahkan.");
    }

    public function labels(Request $request, Item $item)
    {
        $units = $request->query('unit') ? $item->units()->where('id', $request->query('unit'))->get() : $item->units;

        return view('staff.inventory.labels', ['item' => $item, 'units' => $units]);
    }

    public function repair(Request $request, ItemUnit $unit, LoanService $loans, AuditLogger $audit)
    {
        $issue = $request->validate(['issue' => ['required', 'string', 'max:300']], ['issue.required' => 'Tulis masalahnya.'])['issue'];
        abort_unless($unit->status->value === 'available', 422, 'Hanya unit yang tersedia yang bisa dilaporkan rusak.');
        $loans->sendToRepair($unit, $request->user(), $issue);
        $audit->log('Melaporkan kerusakan', $unit->asset_code, $issue);

        return back()->with('ok', 'Unit masuk perawatan dan keluar dari stok tersedia.');
    }
}
