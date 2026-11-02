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
    public function showLogin(AvailabilityService $availability)
    {
        $stat = [
            'running' => Loan::whereIn('status', ['borrowed', 'overdue'])->count(),
            'units' => Item::all()->sum(fn ($i) => $availability->availability($i, now(), now()->addHour())['avail']),
            'pending' => Loan::where('status', 'pending')->count(),
        ];
        $tomorrow = now()->addDay()->setTime(8, 0);
        $board = Item::orderBy('id')->limit(6)->get()->map(fn ($i) => [$i->name, $availability->availability($i, $tomorrow, $tomorrow->copy()->addDay())['avail']]);
        $demo = app()->environment('local') ? User::whereIn('email', ['rina@mhs.silabar.id', 'agus@silabar.id', 'admin@silabar.id'])->get() : collect();

        $tickets = Loan::with(['details.item', 'borrower'])->whereIn('status', ['borrowed', 'approved'])->latest('id')->limit(3)->get();

        return view('auth.login', compact('stat', 'board', 'demo', 'tickets'));
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
