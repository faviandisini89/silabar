<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $action = $request->query('action');
        $logs = ActivityLog::with('causer')->when($action, fn ($x, $a) => $x->where('action', $a))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('subject_type', 'like', "%{$q}%")->orWhere('properties', 'like', "%{$q}%")->orWhereHas('causer', fn ($c) => $c->where('name', 'like', "%{$q}%"))))
            ->latest('id')->paginate(40)->withQueryString();

        return view('admin.audit', ['logs' => $logs, 'q' => $q, 'action' => $action, 'actions' => ActivityLog::distinct()->orderBy('action')->pluck('action')]);
    }
}
