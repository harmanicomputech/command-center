<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Issue;
use App\Models\NarrativeReport;
use App\Models\TaskReport;
use App\Models\User;
use App\Services\Ai\AnthropicModel;
use App\Services\Ai\LanguageModel;
use App\Support\Settings;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LanguageModel::class, AnthropicModel::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'event' => Event::class,
            'issue' => Issue::class,
            'narrative_report' => NarrativeReport::class,
            'task_report' => TaskReport::class,
            'user' => User::class,
        ]);

        // "Keep me signed in" lasts 30 days, so agents can work offline.
        $guard = Auth::guard();
        if ($guard instanceof SessionGuard) {
            $guard->setRememberDuration(60 * 24 * (int) config('campaign.remember_days'));
        }

        Paginator::defaultView('components.pagination');

        View::composer('*', fn ($view) => $view->with('appName', Settings::get('campaign.name') ?: config('app.name')));
    }
}
