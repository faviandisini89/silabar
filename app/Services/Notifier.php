<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Support\Facades\Notification;

class Notifier
{
    public function send(User|iterable $users, string $text, ?string $route = null): void
    {
        Notification::send($users instanceof User ? [$users] : $users, new GenericNotification($text, $route));
    }

    public function toRole(Role $role, string $text, ?string $route = null): void
    {
        $this->send(User::where('role', $role->value)->where('is_active', true)->get(), $text, $route);
    }

    /** Petugas and Admin both work the counter. */
    public function toStaff(string $text, ?string $route = null): void
    {
        $this->send(User::whereIn('role', ['petugas', 'admin'])->where('is_active', true)->get(), $text, $route);
    }
}
