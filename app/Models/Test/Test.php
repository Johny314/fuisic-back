<?php

namespace App\Models\Test;

use App\Models\Concerns\AuditsAdminChanges;
use App\Models\Section;
use App\Models\User;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\TestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Test extends Model
{
    use AuditsAdminChanges;
    use CrudTrait;
    use HasFactory, SoftDeletes;

    protected static function newFactory(): TestFactory
    {
        return TestFactory::new();
    }

    protected $fillable = [
        'name',
        'subject',
        'section_id',
        'class',
        'difficulty',
        'user_id',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
