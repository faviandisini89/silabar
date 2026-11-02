<?php

namespace App\Services;

use App\Domain\Csv;
use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Penalty;
use App\Models\UnmetDemandLog;
use Illuminate\Support\Str;

class ImportExportService
{
    public function __construct(private ItemService $items)
    {
    }

    public function export(string $kind): string
    {
        $rows = match ($kind) {
            'loans' => [['kode', 'peminjam', 'status', 'mulai', 'jatuh_tempo', 'barang'], ...Loan::with(['borrower', 'details.item'])->where('created_at', '>=', now()->subDays(90))->get()
                ->map(fn ($l) => [$l->loan_code, $l->borrower->name, $l->status->value, $l->start_at->toIso8601String(), $l->due_at->toIso8601String(), $l->itemsSummary()])->all()],
            'penalties' => [['tanggal', 'peminjam', 'jenis', 'jumlah', 'status'], ...Penalty::with('user')->get()->map(fn ($p) => [$p->created_at->toDateString(), $p->user->name, $p->type->value, $p->amount, $p->status->value])->all()],
            'items' => [['name', 'sku', 'category', 'location', 'tracking', 'stock', 'max_days', 'requires_approval', 'value'], ...Item::with(['category', 'location'])->get()
                ->map(fn ($i) => [$i->name, $i->sku, $i->category->name, $i->location->name, $i->tracking_type->value, $i->ownedCount(), $i->max_loan_days, $i->requires_approval ? 'true' : 'false', $i->replacement_value])->all()],
            'unmet' => [['tanggal', 'barang', 'peminjam', 'diminta', 'tersedia', 'alasan'], ...UnmetDemandLog::with(['item', 'user'])->get()->map(fn ($u) => [$u->created_at->toDateString(), $u->item->name, $u->user->name, $u->requested_qty, $u->available_qty, $u->reason->value])->all()],
            default => throw new \InvalidArgumentException('Jenis ekspor tidak dikenal.'),
        };

        return Csv::build($rows);
    }

    /** @return array{count:int,skipped:list<string>} */
    public function importItems(string $csv, bool $commit): array
    {
        $rows = Csv::parse($csv);
        if (count($rows) < 2) {
            return ['count' => 0, 'skipped' => ['CSV harus berisi baris judul dan minimal satu barang.']];
        }
        $head = array_map(fn ($h) => strtolower(trim($h)), array_shift($rows));
        $col = fn (array $r, string $k) => trim($r[array_search($k, $head, true)] ?? '');
        $count = 0;
        $skipped = [];
        foreach ($rows as $i => $r) {
            $name = $col($r, 'name');
            if ($name === '') {
                $skipped[] = 'Baris '.($i + 2).': nama kosong';
                continue;
            }
            $count++;
            if (! $commit) {
                continue;
            }
            $cat = Category::firstOrCreate(['slug' => Str::slug($col($r, 'category') ?: 'lainnya')], ['name' => $col($r, 'category') ?: 'Lainnya']);
            $loc = Location::firstOrCreate(['name' => $col($r, 'location') ?: 'Gudang']);
            $this->items->createItem([
                'name' => $name, 'sku' => $col($r, 'sku') ?: $name, 'category_id' => $cat->id, 'location_id' => $loc->id,
                'tracking_type' => $col($r, 'tracking') === 'bulk' ? 'bulk' : 'unit', 'max_loan_days' => (int) ($col($r, 'max_days') ?: 3),
                'requires_approval' => $col($r, 'requires_approval') === 'true', 'replacement_value' => (float) $col($r, 'value'), 'description' => 'Diimpor dari CSV.',
            ], max(1, (int) $col($r, 'stock')));
        }

        return ['count' => $count, 'skipped' => $skipped];
    }
}
