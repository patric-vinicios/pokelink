<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * The evaluator is expected to watch the catalog sync (F06) run during first
     * boot, which happens before there is any reason to have logged in — so the
     * dashboard is open in `local`. Anywhere else it requires an authenticated
     * user, which is the second line of defence behind not exposing the port.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            if ($this->app->environment('local')) {
                return true;
            }

            return $user !== null;
        });
    }
}
