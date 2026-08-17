<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Resolves the single, deterministic conversation between two users,
     * creating it if it does not exist yet. The pair is normalized (lower
     * id first) before the lookup so it never matters who calls this first.
     */
    public static function betweenUsers(User $a, User $b): self
    {
        [$lowerId, $higherId] = self::orderedPair($a->id, $b->id);

        return static::firstOrCreate([
            'user_one_id' => $lowerId,
            'user_two_id' => $higherId,
        ]);
    }

    /**
     * Looks up the conversation between two user ids without creating one —
     * used to open a thread that may not exist yet without writing a row
     * just because someone viewed it.
     */
    public static function findBetween(int $userIdA, int $userIdB): ?self
    {
        [$lowerId, $higherId] = self::orderedPair($userIdA, $userIdB);

        return static::where('user_one_id', $lowerId)->where('user_two_id', $higherId)->first();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function orderedPair(int $userIdA, int $userIdB): array
    {
        return $userIdA < $userIdB ? [$userIdA, $userIdB] : [$userIdB, $userIdA];
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $query) use ($userId) {
            $query->where('user_one_id', $userId)->orWhere('user_two_id', $userId);
        });
    }

    public function otherUserId(int $currentUserId): int
    {
        return $this->user_one_id === $currentUserId ? $this->user_two_id : $this->user_one_id;
    }
}
