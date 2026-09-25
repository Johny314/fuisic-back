<?php

namespace App\Models\Card;

use App\Models\User;
use Database\Factories\CardSetHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CardSetHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): CardSetHistoryFactory
    {
        return CardSetHistoryFactory::new();
    }

    protected $fillable = [
        'card_set_id',
        'user_id',
        'completion_time',
    ];

    public function cardSet(): BelongsTo
    {
        return $this->belongsTo(CardSet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
