<?php

namespace App\Models\Card;

use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Card extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): CardFactory
    {
        return CardFactory::new();
    }

    protected $fillable = [
        'card_set_id',
        'front_text',
        'back_text',
        'description',
    ];

    public function cardSet()
    {
        return $this->belongsTo(CardSet::class);
    }
}
