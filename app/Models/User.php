<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Enums\RoleName;
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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    use CrudTrait;
    use HasApiTokens;
    use HasFactory;
    use HasFuisicAuth;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    // Роли и права общие для API (sanctum) и админки (backpack)
    protected $guard_name = 'web';

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

    protected static function booted(): void
    {
        // Пока жив user_type (до fuisic-back#29), он задаёт одну из ролей admin/teacher/student,
        // остальные роли (moderator, parent) не трогаются. null — поле не передано в create (в БД default student)
        static::created(fn (User $user) => $user->assignRole(
            RoleName::fromUserType($user->user_type ?? UserType::student)->value
        ));

        static::updated(function (User $user) {
            if (! $user->wasChanged('user_type')) {
                return;
            }

            $user->removeRole(RoleName::fromUserType($user->getOriginal('user_type') ?? UserType::student)->value);
            $user->assignRole(RoleName::fromUserType($user->user_type)->value);
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // уже захэшированные значения (Hash::make в контроллерах) повторно не хэшируются
            'password' => 'hashed',
            'user_type' => UserType::class,
        ];
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return app(MediaStorage::class)->url($this->avatar_path);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(RoleName::admin->value);
    }

    public function assignRegistrationRole(string $role): void
    {
        $this->forceFill(['user_type' => RoleName::from($role)->legacyUserType()])->saveQuietly();
        $this->syncRoles([$role]);
    }

    public function authProfile(): array
    {
        $permissions = $this->isAdmin()
            ? Permission::query()->where('guard_name', $this->guard_name)->pluck('name')
            : $this->getAllPermissions()->pluck('name');

        return [
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $permissions->sort()->values()->all(),
            'teacher_verified' => $this->hasRole(RoleName::teacher->value)
                && $permissions->contains(PermissionName::catalogSubmit->value),
        ];
    }
}
