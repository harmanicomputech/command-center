<?php

namespace App\Providers;

use App\Support\Settings;
use Illuminate\Auth\SessionGuard;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // "Keep me signed in" lasts 30 days, so agents can work offline.
        $guard = Auth::guard();
        if ($guard instanceof SessionGuard) {
            $guard->setRememberDuration(60 * 24 * (int) config('campaign.remember_days'));
        }

        Paginator::defaultView('components.pagination');

        View::composer('*', fn ($view) => $view->with('appName', Settings::get('campaign.name') ?: config('app.name')));
    }
}
