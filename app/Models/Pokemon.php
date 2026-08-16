<?php

namespace App\Models;

use Database\Factories\PokemonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Pokemon extends Model
{
    /** @use HasFactory<PokemonFactory> */
    use HasFactory;

    protected $table = 'pokemon';

    protected $primaryKey = 'number';

    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'name',
        'slug',
        'sprite_url',
    ];

    public function types(): BelongsToMany
    {
        return $this->belongsToMany(
            Type::class,
            'pokemon_type',
            'pokemon_number',
            'type_id',
            'number',
            'id',
        );
    }
}
