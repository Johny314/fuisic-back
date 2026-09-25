<?php

namespace App\Data\Test;

use App\Data\Data;
use App\Models\Test\Task as Model;
use App\OpenApi\Property;
use Illuminate\Http\Request;
use OpenApi\Attributes\Schema;

#[Schema(required: ['problem_statement', 'answer'])]
class Task extends Data
{
    #[Property(readOnly: true, example: '1')]
    public ?int $id;

    #[Property(writeOnly: true, example: '1')]
    public ?string $test_id;

    #[Property(example: 'What is 2 + 2?')]
    public ?string $problem_statement;

    #[Property(example: '4')]
    public string $answer;

    #[Property(example: 'Addition problem')]
    public ?string $description;

    #[Property(readOnly: true)]
    public ?Test $test;

    public static function fromRequest(Request $request): Task
    {
        $payload = $request->toArray();
        if (array_key_exists('test_id', $payload) && $payload['test_id'] !== null) {
            $payload['test_id'] = (string) $payload['test_id'];
        }

        return static::from($payload);
    }

    public static function fromModel(Model $model): Task
    {
        return static::from([
            'test' => $model->relationLoaded('test') && $model->test
                ? Test::from($model->test)
                : null,
        ] + $model->toArray());
    }

    public function persistAttributes(): array
    {
        return array_filter([
            'test_id' => $this->test_id,
            'problem_statement' => $this->problem_statement,
            'answer' => $this->answer,
        ], static fn ($value) => $value !== null);
    }
}
