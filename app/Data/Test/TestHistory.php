<?php

namespace App\Data\Test;

use App\Data\Data;
use App\Data\User\Owner;
use App\Models\Test\TestHistory as Model;
use App\OpenApi\Property;
use OpenApi\Attributes\Schema;

#[Schema(required: ['test_id', 'user_id', 'answer'])]
class TestHistory extends Data
{
    #[Property(readOnly: true, example: '1')]
    public ?int $id;

    #[Property(example: 1)]
    public ?int $test_id;

    #[Property(example: 1)]
    public ?int $user_id;

    #[Property(example: 'Answer to test')]
    public ?string $answer;

    #[Property(example: 120)]
    public ?int $completion_time;

    #[Property(readOnly: true)]
    public ?Test $test;

    #[Property(readOnly: true)]
    public ?Owner $user;

    #[Property(readOnly: true)]
    public ?TaskHistory $answers;

    public static function fromModel(Model $model): TestHistory
    {
        return static::from([
            'test_id' => $model->test_id,
            'user_id' => $model->user_id,
            'answer' => $model->answer,
            'completion_time' => $model->completion_time,
            'test' => Test::from($model->test),
            'user' => Owner::from($model->user),
            'answers' => TaskHistory::from($model->answers),
        ]);
    }
}
