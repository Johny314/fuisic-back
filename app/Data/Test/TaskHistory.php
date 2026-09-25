<?php

namespace App\Data\Test;

use App\Data\Data;
use App\Models\Test\TaskHistory as Model;
use App\OpenApi\Property;
use OpenApi\Attributes\Schema;

#[Schema(required: ['task_history_id', 'answer'])]
class TaskHistory extends Data
{
    #[Property(readOnly: true, example: '1')]
    public ?int $id;

    #[Property(example: 1)]
    public ?int $task_history_id;

    #[Property(example: 'Answer to task history')]
    public ?string $answer;

    #[Property(readOnly: true)]
    public ?TestHistory $testHistory;

    public static function fromModel(Model $model): TaskHistory
    {
        return static::from([
            'task_history_id' => $model->task_history_id,
            'answer' => $model->answer,
            'testHistory' => TestHistory::from($model->testHistory),
        ]);
    }
}
