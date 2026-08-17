<?php

namespace App\Models;

use Database\Factories\FavoriteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;

/**
 * Doubles as the pivot model for User::favorites() (via ->using()) and as a
 * directly queryable model — every read/write in this feature goes through
 * an explicit user_id-scoped query at the call site rather than a relation
 * accessor, matching Message's unreadFor() precedent.
 */
class Favorite extends Model
{
    /** @use HasFactory<FavoriteFactory> */
    use AsPivot, HasFactory;

    protected $table = 'favorites';

    protected $fillable = [
        'user_id',
        'pokemon_number',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pokemon(): BelongsTo
    {
        return $this->belongsTo(Pokemon::class, 'pokemon_number', 'number');
    }
}
