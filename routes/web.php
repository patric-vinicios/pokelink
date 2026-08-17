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
| These four routes match the shell's navigation (F04): Início, Favoritos,
| Chat, and Meu Perfil. `/` renders a placeholder until F07 turns it into the
| Pokémon search screen; /favoritos and /chat render a placeholder until F10
| and F12 replace them.
|
| `auth.session` is also applied to all four: it's what makes a password
| change's other-session invalidation (F11) observable, since it compares
| each request's session-stored password-hash reference against the user's
| live value on every one of these routes, not just /perfil.
|
*/

Route::view('/', 'dashboard')
    ->middleware(['auth', 'auth.session'])
    ->name('dashboard');

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
