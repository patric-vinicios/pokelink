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
*/

Volt::route('/', 'pages.pokemon.search')
    ->middleware(['auth'])
    ->name('dashboard');

Route::view('/favoritos', 'favoritos')
    ->middleware(['auth'])
    ->name('favoritos');

Route::view('/chat', 'chat')
    ->middleware(['auth'])
    ->name('chat');

Route::view('/perfil', 'perfil')
    ->middleware(['auth'])
    ->name('perfil');

require __DIR__.'/auth.php';
