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
| Chat, and Meu Perfil. `/` is the Pokémon search screen (F07); /favoritos
| and /chat render a placeholder until F10 and F12 replace them.
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

// Real detail page (F09): local-catalog-first resolution with a lazily
// loaded PokeAPI detail panel. Click-through target for F08's result cards.
Volt::route('/pokemon/{slug}', 'pages.pokemon.show')
    ->middleware(['auth', 'auth.session'])
    ->name('pokemon.show');

Route::view('/favoritos', 'favoritos')
    ->middleware(['auth', 'auth.session'])
    ->name('favoritos');

Route::view('/chat', 'chat')
    ->middleware(['auth', 'auth.session'])
    ->name('chat');

Route::view('/perfil', 'perfil')
    ->middleware(['auth', 'auth.session'])
    ->name('perfil');

require __DIR__.'/auth.php';
