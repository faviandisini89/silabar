<?php

namespace App\Console\Commands;

use App\Services\StatsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AggregateStats extends Command
{
    protected $signature = 'stats:aggregate {--from= : tanggal mulai (Y-m-d)} {--to= : tanggal akhir (Y-m-d)}';

    protected $description = 'Agregasi statistik harian per barang ke daily_item_stats';

    public function handle(StatsService $stats): int
    {
        $from = $this->option('from') ? Carbon::parse($this->option('from')) : now()->subDay();
        $to = $this->option('to') ? Carbon::parse($this->option('to')) : now();
        $n = $stats->aggregateRange($from, $to);
        $this->info("{$n} baris statistik diperbarui.");

        return self::SUCCESS;
    }
}
