<?php

namespace App\Data\Card;

use App\Data\Data;
use App\Models\Card\Card as Model;
use App\OpenApi\Property;
use Illuminate\Http\Request;
use OpenApi\Attributes\Schema;

#[Schema(required: ['card_set_id', 'front_text', 'back_text'])]
class Card extends Data
{
    #[Property(readOnly: true, example: '1')]
    public ?int $id;

    #[Property(writeOnly: true, example: '1')]
    public ?string $card_set_id;

    #[Property(example: 'What is 2 + 2?')]
    public string $front_text;

    #[Property(example: '4')]
    public string $back_text;

    #[Property(example: 'Math addition problem')]
    public ?string $description;

    public static function fromRequest(Request $request): Card
    {
        $payload = $request->toArray();
        if (array_key_exists('card_set_id', $payload) && $payload['card_set_id'] !== null) {
            $payload['card_set_id'] = (string) $payload['card_set_id'];
        }

        return static::from($payload);
    }

    public function persistAttributes(): array
    {
        return array_filter([
            'card_set_id' => $this->card_set_id,
            'front_text' => $this->front_text,
            'back_text' => $this->back_text,
            'description' => $this->description,
        ], static fn ($value) => $value !== null);
    }
}
