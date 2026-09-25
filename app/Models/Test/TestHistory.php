<?php

namespace App\Models\Test;

use App\Models\User;
use Database\Factories\TestHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): TestHistoryFactory
    {
        return TestHistoryFactory::new();
    }

    protected $fillable = [
        'test_id',
        'user_id',
        'answer',
        'completion_time',
    ];

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): BelongsTo
    {
        return $this->belongsTo(TaskHistory::class);
    }
}
