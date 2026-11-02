<?php

namespace App\Services;

use App\Enums\TrackingType;
use App\Models\Item;
use App\Models\ItemUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ItemService
{
    public function createItem(array $data, int $qty): Item
    {
        return DB::transaction(function () use ($data, $qty) {
            $tracking = ($data['tracking_type'] ?? 'unit') === 'bulk' ? TrackingType::Bulk : TrackingType::Unit;
            $sku = !empty($data['sku']) ? strtoupper(trim((string) $data['sku'])) : null;
            if ($sku) {
                if (Item::where('sku', $sku)->exists()) {
                    $sku = $this->uniqueSku($sku);
                }
            } else {
                $sku = $this->uniqueSku($data['name']);
            }
            $data['sku'] = $sku;
            $data['tracking_type'] = $tracking->value;
            $data['total_stock'] = $tracking === TrackingType::Bulk ? max(1, $qty) : 0;
            $data['tile'] ??= Item::count() % count(Item::TILES);
            $item = Item::create($data);
            if ($tracking === TrackingType::Unit) {
                $this->addUnits($item, max(1, $qty));
            }

            return $item;
        });
    }

    /** @return list<ItemUnit> */
    public function addUnits(Item $item, int $qty): array
    {
        $made = [];
        $next = ItemUnit::where('item_id', $item->id)->count() + 1;
        for ($i = 0; $i < $qty; $i++) {
            do {
                $code = sprintf('%s-%03d', $item->sku, $next++);
            } while (ItemUnit::where('asset_code', $code)->exists());
            $made[] = ItemUnit::create(['item_id' => $item->id, 'asset_code' => $code, 'condition' => 'good', 'status' => 'available', 'acquired_at' => now()->toDateString()]);
        }

        return $made;
    }

    public function uniqueSku(string $source): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', Str::ascii($source)) ?: 'BRG', 0, 3));
        $sku = $base;
        $n = 2;
        while (Item::where('sku', $sku)->exists()) {
            $sku = $base.$n++;
        }

        return $sku;
    }
}
