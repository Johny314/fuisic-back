<?php

namespace App\Models\Test;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }

    protected $fillable = [
        'test_id',
        'problem_statement',
        'answer',
    ];

    public function test()
    {
        return $this->belongsTo(Test::class);
    }
}
