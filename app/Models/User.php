<?php

namespace App\Models;

use App\Enums\UserType;
use App\Services\MediaStorage;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Fuisic\Auth\Traits\HasFuisicAuth;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    use CrudTrait;
    use HasApiTokens;
    use HasFactory;
    use HasFuisicAuth;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'avatar_path',
    ];

    protected $appends = [
        'avatar_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'avatar_path',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'user_type' => UserType::class,
        ];
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return app(MediaStorage::class)->url($this->avatar_path);
    }
}
