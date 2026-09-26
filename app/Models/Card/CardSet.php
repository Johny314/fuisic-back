<?php

namespace App\Models\Card;

use App\Models\Concerns\AuditsAdminChanges;
use App\Models\Section;
use App\Models\User;
use App\Services\MediaStorage;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\CardSetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CardSet extends Model
{
    use AuditsAdminChanges;
    use CrudTrait;
    use HasFactory, SoftDeletes;

    protected static function newFactory(): CardSetFactory
    {
        return CardSetFactory::new();
    }

    protected $fillable = [
        'name',
        'subject',
        'section_id',
        'class',
        'difficulty',
        'user_id',
        'logo_path',
    ];

    protected $appends = [
        'logo_url',
    ];

    protected $hidden = [
        'logo_path',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return app(MediaStorage::class)->url($this->logo_path);
    }
}
