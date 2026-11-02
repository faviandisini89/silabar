<?php

namespace App\Console\Commands;

use App\Services\AnalyticsRunner;
use Illuminate\Console\Command;

class RunAnalytics extends Command
{
    protected $signature = 'analytics:run {--task=* : forecast, risk, assoc, anomaly, procurement (default: semua)}';

    protected $description = 'Jalankan analisis machine learning di layanan Python';

    public function handle(AnalyticsRunner $runner): int
    {
        $tasks = $this->option('task') ?: ['all'];
        $summary = $runner->run($tasks);
        foreach ($summary['tasks'] ?? [] as $name => $t) {
            $this->line(sprintf('%-12s %s', $name, $t['status'] ?? '?'));
        }
        if (($summary['status'] ?? '') === 'ok') {
            $this->info('Analisis selesai ('.($summary['seconds'] ?? '?').' detik).');

            return self::SUCCESS;
        }
        $this->error($summary['error'] ?? 'Sebagian tugas gagal.');

        return self::FAILURE;
    }
}
