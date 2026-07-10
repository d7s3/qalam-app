<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /** @return HasMany<ConversationParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Find the existing 1:1 conversation between two participants, or create one.
     */
    public static function findOrCreateBetween(string $typeA, int $idA, string $typeB, int $idB): self
    {
        $existing = self::query()
            ->whereHas('participants', fn ($q) => $q->where('participant_type', $typeA)->where('participant_id', $idA))
            ->whereHas('participants', fn ($q) => $q->where('participant_type', $typeB)->where('participant_id', $idB))
            ->withCount('participants')
            ->get()
            ->firstWhere('participants_count', 2);

        if ($existing) {
            return $existing;
        }

        $conversation = self::create();
        $conversation->participants()->createMany([
            ['participant_type' => $typeA, 'participant_id' => $idA],
            ['participant_type' => $typeB, 'participant_id' => $idB],
        ]);

        return $conversation;
    }
}
