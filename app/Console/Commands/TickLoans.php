<?php

namespace App\Console\Commands;

use App\Services\LoanLifecycle;
use Illuminate\Console\Command;

class TickLoans extends Command
{
    protected $signature = 'loans:tick';

    protected $description = 'Tandai pinjaman terlambat, kirim pengingat H-1, batalkan tiket yang tidak diambil';

    public function handle(LoanLifecycle $lifecycle): int
    {
        $r = $lifecycle->tick();
        $this->info("terlambat: {$r['overdue']}, pengingat: {$r['reminded']}, dibatalkan: {$r['cancelled']}");

        return self::SUCCESS;
    }
}
