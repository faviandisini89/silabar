<?php

namespace App\Providers;

use App\Http\Middleware\EnsureRole;
use App\Models\Loan;
use App\Policies\LoanPolicy;
use App\Services\SettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }

    public function boot(): void
    {
        if (request()->header('x-forwarded-proto') === 'https' || app()->environment('production')) {
            URL::forceScheme('https');
        }

        Route::aliasMiddleware('role', EnsureRole::class);
        Gate::policy(Loan::class, LoanPolicy::class);
        Carbon::setLocale('id');
    }
}
