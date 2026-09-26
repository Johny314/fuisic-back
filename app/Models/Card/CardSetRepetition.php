<?php

namespace App\Models\Card;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Набор в «Моих повторениях» пользователя. Меняется только через App\Services\Repetitions.
 */
class CardSetRepetition extends Model
{
    protected $fillable = [
        'user_id',
        'card_set_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cardSet(): BelongsTo
    {
        return $this->belongsTo(CardSet::class);
    }
}
