<?php

namespace App\Services;

use App\Enums\Role;

class AnalyticsRunner
{
    public function __construct(private AnalyticsClient $client, private Notifier $notifier, private AuditLogger $audit)
    {
    }

    public function run(array $tasks = ['all'], ?int $userId = null): array
    {
        $summary = $this->client->run($tasks);
        $created = (int) ($summary['created_recommendations'] ?? 0);
        if ($created > 0) {
            $this->notifier->toRole(Role::Admin, "Analisis selesai: {$created} rekomendasi pengadaan baru menunggu keputusan.", 'admin.procure');
        }
        $this->audit->log('Menjalankan analisis', 'Analitik', $summary['status'] ?? 'unknown', $userId);

        return $summary;
    }
}
