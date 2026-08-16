<?php

namespace App\Jobs;

final readonly class SyncPokemonCatalogStats
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $total,
    ) {}
}
