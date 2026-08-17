<?php

namespace App\Console\Commands;

use App\Exceptions\PokeApiSyncException;
use App\Jobs\SyncPokemonCatalog;
use App\Jobs\SyncPokemonCatalogStats;
use Illuminate\Console\Command;

class SyncPokemonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pokemon:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza o catálogo local de Pokémon a partir do PokeAPI';

    public function handle(): int
    {
        try {
            // A direct container call, not ::dispatchSync() — for a ShouldQueue
            // job, dispatchSync() still runs through the queue's dispatcher
            // pipeline (forced onto the "sync" connection), which returns the
            // queue's job-id placeholder and swallows the handler's real return
            // value and thrown exceptions alike. Calling handle() directly
            // keeps container-based parameter resolution (PokeApiClient) while
            // getting the actual SyncPokemonCatalogStats and exception back.
            /** @var SyncPokemonCatalogStats $stats */
            $stats = app()->call([new SyncPokemonCatalog, 'handle']);
        } catch (PokeApiSyncException $e) {
            $this->error("Falha ao sincronizar o catálogo: PokeAPI indisponível (endpoint: {$e->endpoint}). Verifique os logs.");

            return self::FAILURE;
        }

        $this->info("{$stats->total} Pokémon sincronizados ({$stats->created} criados, {$stats->updated} atualizados)");

        return self::SUCCESS;
    }
}
