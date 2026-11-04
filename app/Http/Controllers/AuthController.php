<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        $demo = app()->environment('local') ? User::whereIn('email', ['rina@mhs.silabar.id', 'agus@silabar.id', 'admin@silabar.id'])->get() : collect();

        return view('auth.login', compact('demo'));
    }

    public function login(Request $request, AuditLogger $audit)
    {
        $cred = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::attempt($cred + ['is_active' => true], $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Email atau kata sandi tidak cocok, atau akun dinonaktifkan.']);
        }
        $request->session()->regenerate();
        $audit->log('Masuk', 'Sesi');

        return redirect()->intended(route(Auth::user()->role->home()));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'nim' => ['nullable', 'string', 'max:40'],
            'organization' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $user = User::create($data + ['role' => Role::Peminjam->value, 'trust_score' => 70, 'is_active' => true]);
        Auth::login($user);
        $request->session()->regenerate();
        $audit->log('Mendaftar akun', $user->email, null, $user->id);

        return redirect()->route('home')->with('ok', 'Akun dibuat. Selamat datang di SILABAR!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
