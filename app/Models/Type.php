<?php

namespace App\Models;

use Database\Factories\TypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Type extends Model
{
    /** @use HasFactory<TypeFactory> */
    use HasFactory;

    protected $table = 'types';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'label_pt',
    ];

    public function pokemon(): BelongsToMany
    {
        return $this->belongsToMany(
            Pokemon::class,
            'pokemon_type',
            'type_id',
            'pokemon_number',
            'id',
            'number',
        );
    }
}
