<?php

namespace App\Models;

use App\Enums\PenaltyStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'nim', 'organization', 'phone', 'trust_score', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'trust_score' => 'integer',
        ];
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0];
    }

    public function loans()
    {
        return $this->hasMany(Loan::class, 'borrower_id');
    }

    public function penalties()
    {
        return $this->hasMany(Penalty::class);
    }

    public function unpaidAmount(): float
    {
        return (float) $this->penalties()->where('status', PenaltyStatus::Unpaid->value)->sum('amount');
    }
}
