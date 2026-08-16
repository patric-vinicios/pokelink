<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// F06: weekly catalog refresh. Firing this automatically requires an external
// cron or `schedule:work` process — F01's docker-compose stack provisions
// neither, so `php artisan pokemon:sync` is the documented manual fallback.
// See docs/F06-pokemon-catalog-sync/spec.md.
Schedule::command('pokemon:sync')
    ->weeklyOn(config('pokemon.schedule.day'), config('pokemon.schedule.time'));
