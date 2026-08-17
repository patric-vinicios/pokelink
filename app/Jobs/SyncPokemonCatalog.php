<?php

namespace App\Jobs;

use App\Exceptions\PokeApiSyncException;
use App\Models\Pokemon;
use App\Models\Type;
use App\Services\PokeApi\PokeApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Populates `pokemon`, `types`, and `pokemon_type` from exactly 19 calls
 * through PokeApiClient (1 index() + 18 typeRoster()). Dispatched from the
 * seeder onto the queue, and run synchronously by `pokemon:sync` via a
 * direct container call to handle() — not ::dispatchSync(), which for a
 * ShouldQueue job still routes through the queue and discards the return
 * value — so the command can print the returned stats directly.
 */
class SyncPokemonCatalog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public array $backoff;

    public int $timeout;

    public function __construct()
    {
        $this->tries = (int) config('pokemon.sync.tries');
        $this->backoff = config('pokemon.sync.backoff_seconds');
        $this->timeout = (int) config('pokemon.sync.timeout_seconds');
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return ['pokemon-sync'];
    }

    public function handle(PokeApiClient $client): SyncPokemonCatalogStats
    {
        $entries = $this->fetchIndex($client);
        $membersByType = $this->fetchTypeRosters($client);

        $this->syncTypes();

        $numberByName = [];

        foreach ($entries as $entry) {
            if ($entry['number'] !== null && filled($entry['name'])) {
                $numberByName[$entry['name']] = $entry['number'];
            }
        }

        [$created, $updated] = $this->syncPokemon($entries);

        $this->syncPokemonType($membersByType, $numberByName);

        return new SyncPokemonCatalogStats(
            created: $created,
            updated: $updated,
            total: $created + $updated,
        );
    }

    /**
     * @return list<array{number: ?int, name: ?string, url: ?string}>
     */
    private function fetchIndex(PokeApiClient $client): array
    {
        $result = $client->index();

        if (! $result->successful()) {
            throw new PokeApiSyncException('index');
        }

        $entries = $result->data()['entries'] ?? [];

        // F05 defaults a missing `results` key to an empty array rather than
        // failing, so an unexpected upstream schema change would otherwise
        // look like a successful sync of zero Pokémon. Fail fast instead.
        if ($entries === []) {
            throw new PokeApiSyncException('index');
        }

        return $entries;
    }

    /**
     * @return array<string, list<string>> type slug => member Pokémon names
     */
    private function fetchTypeRosters(PokeApiClient $client): array
    {
        $membersByType = [];

        foreach (array_keys(config('pokemon.type_labels')) as $slug) {
            $result = $client->typeRoster($slug);

            if (! $result->successful()) {
                throw new PokeApiSyncException("type/{$slug}");
            }

            $membersByType[$slug] = $result->data()['members'] ?? [];
        }

        return $membersByType;
    }

    private function syncTypes(): void
    {
        $now = now();

        $rows = collect(config('pokemon.type_labels'))
            ->map(fn (string $label, string $slug): array => [
                'slug' => $slug,
                'label_pt' => $label,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        DB::table('types')->upsert($rows, ['slug'], ['label_pt', 'updated_at']);
    }

    /**
     * @param  list<array{number: ?int, name: ?string, url: ?string}>  $entries
     * @return array{0: int, 1: int} [created, updated]
     */
    private function syncPokemon(array $entries): array
    {
        $batchSize = (int) config('pokemon.sync.batch_size');
        $created = 0;
        $updated = 0;
        $batchIndex = 0;

        $rows = collect($entries)
            ->filter(fn (array $entry) => $entry['number'] !== null && filled($entry['name']))
            ->map(fn (array $entry): array => [
                'number' => $entry['number'],
                'name' => $entry['name'],
                'slug' => $entry['name'],
                'sprite_url' => $this->officialArtworkUrl($entry['number']),
            ]);

        foreach ($rows->chunk($batchSize) as $chunk) {
            $batchIndex++;
            $numbers = $chunk->pluck('number')->all();

            DB::transaction(function () use ($chunk, $numbers, &$created, &$updated) {
                $existing = array_flip(
                    Pokemon::query()->whereIn('number', $numbers)->pluck('number')->all()
                );

                foreach ($numbers as $number) {
                    if (isset($existing[$number])) {
                        $updated++;
                    } else {
                        $created++;
                    }
                }

                $now = now();

                $values = $chunk->map(fn (array $row): array => [
                    ...$row,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('pokemon')->upsert($values, ['number'], ['name', 'slug', 'sprite_url', 'updated_at']);
            });

            Log::info('Lote de pokemon sincronizado', [
                'tabela' => 'pokemon',
                'lote' => $batchIndex,
                'linhas' => $chunk->count(),
            ]);
        }

        return [$created, $updated];
    }

    /**
     * @param  array<string, list<string>>  $membersByType
     * @param  array<string, int>  $numberByName
     */
    private function syncPokemonType(array $membersByType, array $numberByName): void
    {
        $typeIds = Type::query()->pluck('id', 'slug');
        $batchSize = (int) config('pokemon.sync.batch_size');
        $batchIndex = 0;

        $rows = collect();

        foreach ($membersByType as $typeSlug => $members) {
            $typeId = $typeIds[$typeSlug] ?? null;

            if ($typeId === null) {
                continue;
            }

            foreach ($members as $memberName) {
                $number = $numberByName[$memberName] ?? null;

                if ($number === null) {
                    // Reflects upstream data skew between the index and a type
                    // roster, not a call failure — skip and log, don't fail the job.
                    Log::warning('Membro do tipo sem correspondência no índice', [
                        'tipo' => $typeSlug,
                        'nome' => $memberName,
                    ]);

                    continue;
                }

                $rows->push([
                    'pokemon_number' => $number,
                    'type_id' => $typeId,
                ]);
            }
        }

        foreach ($rows->chunk($batchSize) as $chunk) {
            $batchIndex++;
            $now = now();

            $values = $chunk->map(fn (array $row): array => [
                ...$row,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            // No mutable data on a membership row beyond the unique key itself,
            // so the update clause is self-referential — a safe no-op on conflict.
            DB::table('pokemon_type')->upsert($values, ['pokemon_number', 'type_id'], ['type_id']);

            Log::info('Lote de vínculos pokemon-tipo sincronizado', [
                'tabela' => 'pokemon_type',
                'lote' => $batchIndex,
                'linhas' => $chunk->count(),
            ]);
        }
    }

    private function officialArtworkUrl(int $number): string
    {
        // See the identical comment on PokeApiClient::officialArtworkUrl():
        // jsdelivr mirrors the same files through a real CDN;
        // raw.githubusercontent.com rate-limits hard under this app's
        // per-page sprite volume.
        return "https://cdn.jsdelivr.net/gh/PokeAPI/sprites@master/sprites/pokemon/other/official-artwork/{$number}.png";
    }
}
