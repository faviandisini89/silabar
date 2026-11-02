<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function log(string $action, ?string $subject = null, ?string $detail = null, ?int $userId = null): void
    {
        ActivityLog::create([
            'causer_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject,
            'properties' => $detail,
        ]);
    }
}
