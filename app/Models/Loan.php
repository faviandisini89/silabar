<?php

namespace App\Models;

use App\Enums\LoanStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $fillable = ['loan_code', 'borrower_id', 'approved_by', 'handed_over_by', 'status', 'purpose', 'start_at', 'due_at', 'approved_at', 'handed_over_at', 'returned_at', 'late_days', 'reject_reason', 'cancel_note', 'qr_token', 'reminder_sent_at'];

    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'start_at' => 'datetime',
            'due_at' => 'datetime',
            'approved_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'returned_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'late_days' => 'integer',
        ];
    }

    public function borrower()
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function handedOverBy()
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }

    public function details()
    {
        return $this->hasMany(LoanDetail::class);
    }

    public function returns()
    {
        return $this->hasMany(LoanReturn::class);
    }

    public function extensions()
    {
        return $this->hasMany(LoanExtension::class);
    }

    public function pendingExtension()
    {
        return $this->hasOne(LoanExtension::class)->where('status', 'pending')->latestOfMany();
    }

    public function riskScore()
    {
        return $this->hasOne(OverdueRiskScore::class);
    }

    public function penalties()
    {
        return $this->hasMany(Penalty::class);
    }

    /** The moment this loan stops holding stock. */
    public function occupiedUntil(?Carbon $now = null): Carbon
    {
        $now ??= now();

        return match ($this->status) {
            LoanStatus::Returned => $this->returned_at ?? $this->due_at,
            LoanStatus::Overdue => $this->due_at->greaterThan($now) ? $this->due_at : $now,
            default => $this->due_at,
        };
    }

    public function itemsSummary(): string
    {
        return $this->details->groupBy('item_id')->map(function ($rows) {
            $q = $rows->sum('qty');

            return $rows->first()->item->name.($q > 1 ? ' ×'.$q : '');
        })->implode(', ');
    }

    public function scopeStatus($q, LoanStatus ...$s)
    {
        return $q->whereIn('status', LoanStatus::values($s));
    }
}
