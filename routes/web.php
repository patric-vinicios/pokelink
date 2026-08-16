<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Everything except the guest routes in auth.php sits behind `auth`, so a
| visitor opening any internal URL is sent to /login (F02 hardens the redirect
| and intended-URL restore behaviour).
|
| `/` currently renders Breeze's default dashboard view. F04 replaces it with
| the application shell and F07 turns it into the Pokémon search screen.
|
*/

Route::view('/', 'dashboard')
    ->middleware(['auth'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
