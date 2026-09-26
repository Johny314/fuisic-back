<?php

namespace App\Data\Repetition;

use App\Data\Data;
use App\Enums\ReviewRating;
use App\OpenApi\Property;
use Illuminate\Validation\Rule;
use OpenApi\Attributes\Schema;

/** Оценка карточки. review_id генерирует клиент: при повторной отправке — тот же. */
#[Schema(required: ['review_id', 'rating'])]
class ReviewSubmit extends Data
{
    /** Дольше — считаем как час: ответ явно отложили */
    public const MAX_DURATION_MS = 3_600_000;

    #[Property(format: 'uuid', description: 'Клиентский id оценки; повтор с тем же id не создаёт вторую оценку', example: '0b6f7c1e-3f1a-4c55-9f0e-2a4d0f8a9c11')]
    public string $review_id;

    #[Property(type: 'integer', enum: [1, 2, 3, 4], description: '1 — Снова, 2 — Трудно, 3 — Хорошо, 4 — Легко', example: 3)]
    public int $rating;

    #[Property(type: 'integer', minimum: 0, description: 'Время ответа, мс (больше часа — записывается час)', example: 4200)]
    public ?int $duration_ms = null;

    public static function rules(): array
    {
        return [
            'review_id' => ['required', 'uuid'],
            'rating' => ['required', 'integer', Rule::enum(ReviewRating::class)],
            'duration_ms' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function ratingEnum(): ReviewRating
    {
        return ReviewRating::from($this->rating);
    }

    public function duration(): ?int
    {
        return $this->duration_ms === null ? null : min($this->duration_ms, self::MAX_DURATION_MS);
    }
}
