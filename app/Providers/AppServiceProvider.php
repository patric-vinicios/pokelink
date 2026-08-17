<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UpdateProfilePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // UpdateProfilePolicy doesn't follow Laravel's UserPolicy auto-discovery
        // convention, so it needs explicit registration (F11).
        Gate::policy(User::class, UpdateProfilePolicy::class);
    }
}
