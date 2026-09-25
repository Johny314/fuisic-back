<?php

namespace App\Data\Test;

use App\Data\Data;
use App\Data\Section;
use App\Data\User\Owner;
use App\Enums\Classifications;
use App\Enums\Difficulty;
use App\Enums\Subject;
use App\Models\Test\Test as Model;
use App\OpenApi\Property;
use Illuminate\Http\Request;
use OpenApi\Attributes\Schema;

#[Schema(required: ['name', 'section_id'])]
class Test extends Data
{
    #[Property(readOnly: true, example: '1')]
    public ?int $id;

    #[Property(example: 'Science Test')]
    public string $name;

    #[Property(example: Subject::math->value)]
    public Subject $subject;

    #[Property(writeOnly: true, example: '1')]
    public ?string $section_id;

    #[Property(example: Classifications::first->value)]
    public ?string $class;

    #[Property(example: Difficulty::easy->value)]
    public ?string $difficulty;

    #[Property(readOnly: true, example: '1')]
    public ?string $user_id;

    #[Property(readOnly: true)]
    public ?Section $section;

    #[Property(readOnly: true)]
    public ?Owner $user;

    public static function fromRequest(Request $request): Test
    {
        return static::from([
            'user_id' => auth()->user()->id,
        ] + $request->toArray()
        );
    }

    public static function fromModel(Model $model): Test
    {
        $model->loadMissing(['section', 'user']);

        return static::from([
            'section' => $model->section ? Section::from($model->section) : null,
            'user' => $model->user ? Owner::from($model->user) : null,
        ] + $model->toArray());
    }

    public function persistAttributes(?int $userId = null): array
    {
        $payload = [
            'name' => $this->name,
            'subject' => $this->subject instanceof Subject ? $this->subject->value : $this->subject,
            'section_id' => $this->section_id,
            'class' => $this->class,
            'difficulty' => $this->difficulty,
        ];

        if ($userId !== null) {
            $payload['user_id'] = $userId;
        }

        return array_filter($payload, static fn ($value) => $value !== null && $value !== '');
    }
}
