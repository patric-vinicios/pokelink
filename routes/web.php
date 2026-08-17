<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Everything except the guest routes in auth.php sits behind `auth`, so a
| visitor opening any internal URL is sent to /login (F02 hardens the redirect
| and intended-URL restore behaviour).
|
| These four routes match the shell's navigation (F04): Início, Favoritos,
| Chat, and Meu Perfil. `/` is the Pokémon search screen (F07).
|
| `auth.session` is also applied to all four: it's what makes a password
| change's other-session invalidation (F11) observable, since it compares
| each request's session-stored password-hash reference against the user's
| live value on every one of these routes, not just /perfil.
|
*/

Volt::route('/', 'pages.pokemon.search')
    ->middleware(['auth', 'auth.session'])
    ->name('dashboard');

// Backward-compatible deep links return to the catalog and open the same
// modal used by card interactions; no standalone detail screen is rendered.
Route::get('/pokemon/{slug}', fn (string $slug) => redirect()->route('dashboard', ['pokemon' => $slug]))
    ->middleware(['auth', 'auth.session'])
    ->name('pokemon.show');

// Favorites page (F10): lists, filters, and sorts the authenticated user's
// collection using the same card component the search grid renders.
Volt::route('/favoritos', 'pages.pokemon.favorites')
    ->middleware(['auth', 'auth.session'])
    ->name('favoritos');

Route::view('/chat', 'chat')
    ->middleware(['auth', 'auth.session'])
    ->name('chat');

Route::view('/perfil', 'perfil')
    ->middleware(['auth', 'auth.session'])
    ->name('perfil');

require __DIR__.'/auth.php';
