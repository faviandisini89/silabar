<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $users = User::when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))->orderBy('name')->get();

        return view('admin.users', ['users' => $users, 'q' => $q, 'roles' => Role::cases()]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'unique:users,email'], 'role' => ['required', Rule::enum(Role::class)],
            'organization' => ['nullable', 'string', 'max:120'], 'nim' => ['nullable', 'string', 'max:40'],
        ]);
        $user = User::create($d + ['password' => config('silabar.demo_password'), 'trust_score' => 70, 'is_active' => true]);
        $audit->log('Menambah pengguna', $user->name, $user->role->value);

        return back()->with('ok', "Pengguna ditambahkan. Kata sandi awal: ".config('silabar.demo_password'));
    }

    public function update(Request $request, User $user, AuditLogger $audit)
    {
        abort_if($user->id === $request->user()->id, 422, 'Kamu tidak bisa mengubah akunmu sendiri di sini.');
        $d = $request->validate(['role' => ['nullable', Rule::enum(Role::class)], 'is_active' => ['nullable', 'boolean']]);
        if (isset($d['role'])) {
            $user->role = Role::from($d['role']);
        }
        if ($request->has('active_form')) {
            $user->is_active = $request->boolean('is_active');
        }
        $user->save();
        $audit->log('Mengubah pengguna', $user->name, $user->role->value.($user->is_active ? ', aktif' : ', nonaktif'));

        return back()->with('ok', 'Perubahan disimpan.');
    }
}
