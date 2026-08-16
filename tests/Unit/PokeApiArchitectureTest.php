<?php

arch('apenas o serviço PokeApi usa o cliente Http para falar com a PokeAPI')
    ->expect('Illuminate\Support\Facades\Http')
    ->toOnlyBeUsedIn('App\Services\PokeApi');
