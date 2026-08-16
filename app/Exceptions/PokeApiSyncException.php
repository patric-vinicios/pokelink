<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by SyncPokemonCatalog whenever an upstream call is unavailable, not
 * found, or structurally malformed. Naming the failing endpoint here is what
 * makes it grep-able in `failed_jobs` and the queue logs.
 */
class PokeApiSyncException extends RuntimeException
{
    public function __construct(public readonly string $endpoint)
    {
        parent::__construct("Falha ao sincronizar o catálogo Pokémon: endpoint \"{$endpoint}\" indisponível ou retornou dados malformados.");
    }
}
