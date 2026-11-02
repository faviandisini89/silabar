<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user || ! $user->is_active) {
            auth()->logout();

            return redirect()->route('login')->withErrors(['email' => 'Akun tidak aktif atau belum masuk.']);
        }
        if (! in_array($user->role->value, $roles, true)) {
            abort(403, 'Peranmu tidak punya akses ke halaman ini.');
        }

        return $next($request);
    }
}
