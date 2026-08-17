<?php

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

test('a mismatched csrf token redirects to login with an expired-session message', function () {
    Route::post('/__teste-csrf-expirado', function () {
        throw new TokenMismatchException('CSRF token mismatch.');
    })->middleware('web');

    $response = $this->post('/__teste-csrf-expirado');

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status', 'Sua sessão expirou. Faça login novamente.');
});

test('a database connection failure mid-session shows the pt-BR 503 page', function () {
    Route::get('/__teste-falha-banco', function () {
        throw new PDOException('SQLSTATE[HY000] [2002] Connection refused');
    })->middleware('web');

    $response = $this->get('/__teste-falha-banco');

    $response->assertStatus(503);
    $response->assertViewIs('errors.503');
    $response->assertSee('Serviço temporariamente indisponível');
    $response->assertDontSee('SQLSTATE');
    $response->assertDontSee('PDOException');
});
