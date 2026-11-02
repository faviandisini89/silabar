<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    private const NUMERIC = [
        'loan.auto_approve_trust' => [0, 100], 'loan.grace_hours' => [1, 240], 'penalty.late_per_day' => [0, 10000000], 'penalty.damaged_pct' => [0, 100],
        'procure.min_unmet' => [1, 1000], 'procure.min_users' => [1, 1000], 'procure.fill_below' => [1, 100], 'procure.window_days' => [7, 365], 'procure.maint_share' => [1, 100],
        'procure.w_unmet' => [0, 1], 'procure.w_users' => [0, 1], 'procure.w_util' => [0, 1], 'procure.w_trend' => [0, 1],
    ];

    public function index(SettingsService $settings)
    {
        return view('admin.settings', ['s' => $settings->all(), 'categories' => Category::orderBy('name')->get(), 'locations' => Location::orderBy('name')->get()]);
    }

    public function update(Request $request, SettingsService $settings, AuditLogger $audit)
    {
        $rules = [];
        foreach (self::NUMERIC as $key => [$min, $max]) {
            $rules[str_replace('.', '_', $key)] = ['required', 'numeric', "min:{$min}", "max:{$max}"];
        }
        $d = $request->validate($rules);
        foreach (self::NUMERIC as $key => $_) {
            $v = $d[str_replace('.', '_', $key)] + 0;
            $settings->set($key, $v);
        }
        $settings->set('loan.auto_approve', $request->boolean('loan_auto_approve'));
        $audit->log('Mengubah pengaturan', 'Sistem');

        return back()->with('ok', 'Pengaturan disimpan.');
    }

    public function master(Request $request, AuditLogger $audit)
    {
        $d = $request->validate(['type' => ['required', 'in:category,location'], 'name' => ['required', 'string', 'max:120']]);
        if ($d['type'] === 'category') {
            Category::firstOrCreate(['slug' => Str::slug($d['name'])], ['name' => $d['name']]);
        } else {
            Location::firstOrCreate(['name' => $d['name']]);
        }
        $audit->log('Menambah master data', $d['name'], $d['type']);

        return back()->with('ok', 'Master data ditambahkan.');
    }
}
