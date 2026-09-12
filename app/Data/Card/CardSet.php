<?php

namespace App\Data\Card;

use App\Data\Data;
use App\Data\Section;
use App\Data\User\User;
use App\Enums\Classifications;
use App\Enums\Difficulty;
use App\Enums\Subject;
use App\Models\Card\CardSet as Model;
use App\OpenApi\Property;
use Illuminate\Http\Request;
use OpenApi\Attributes\Schema;
use Spatie\LaravelData\Attributes\Hidden;

#[Schema(required: ['name', 'section_id'])]
class CardSet extends Data
{
    #[Property(readOnly: true, example: '1')]
    public ?int $id;

    #[Property(example: 'Math Basics')]
    public string $name;

    #[Property(example: Subject::math->value)]
    public Subject $subject;

    #[Property(writeOnly: true, example: '1')]
    public ?string $section_id;

    #[Property(example: Classifications::first->value)]
    public ?Classifications $class;

    #[Property(example: Difficulty::easy->value)]
    public ?Difficulty $difficulty;

    #[Property(writeOnly: true, example: '1')]
    public ?string $user_id;

    #[Property(schema: Section::class, readOnly: true)]
    public ?Section $section;

    #[Property(schema: User::class, readOnly: true)]
    public ?User $user;

    #[Hidden]
    #[Property(writeOnly: true, example: 'card-set-logos/abc.png')]
    public ?string $logo_path = null;

    #[Property(readOnly: true, example: 'http://localhost:9000/fuisic/card-set-logos/abc.png')]
    public ?string $logo_url = null;

    public static function fromRequest(Request $request): CardSet
    {
        return static::from($request->toArray());
    }

    public static function fromModel(Model $model): CardSet
    {
        $model->loadMissing(['section', 'user']);

        return static::from([
                'section' => $model->section ? Section::from($model->section) : null,
                'user' => $model->user ? User::from($model->user) : null,
                'logo_url' => $model->logo_url,
            ] + $model->toArray());
    }

    public function persistAttributes(?int $userId = null): array
    {
        $payload = [
            'name' => $this->name,
            'subject' => $this->subject->value,
            'section_id' => $this->section_id,
            'class' => $this->class?->value,
            'difficulty' => $this->difficulty?->value,
            'logo_path' => $this->logo_path,
        ];

        if ($userId !== null) {
            $payload['user_id'] = $userId;
        }

        return array_filter($payload, static fn ($value) => $value !== null);
    }
}
