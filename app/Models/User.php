<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Concerns\AuditsAdminChanges;
use App\Services\MediaStorage;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Fuisic\Auth\Services\EmailVerificationService;
use Fuisic\Auth\Traits\HasFuisicAuth;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    use AuditsAdminChanges;
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
        // Пользователь без роли не остаётся: по умолчанию student, другие роли назначают syncRoles
        // (регистрация, фабрика, админка) уже после создания
        static::created(function (User $user) {
            if ($user->roles()->doesntExist()) {
                $user->assignRole(RoleName::student->value);
            }
        });

        // Новый email (профиль, админка, первый email ребёнка) не подтверждён, пока не придёт письмо.
        // Явно заданный в том же сохранении email_verified_at не трогаем.
        static::updating(function (User $user) {
            if ($user->isDirty('email') && ! $user->isDirty('email_verified_at')) {
                $user->email_verified_at = null;
            }
        });

        // письмо — после фиксации транзакции, чтобы job не прочитал старый email
        static::updated(function (User $user) {
            if ($user->wasChanged('email')) {
                DB::afterCommit(fn () => app(EmailVerificationService::class)->send($user));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // уже захэшированные значения (Hash::make в контроллерах) повторно не хэшируются
            'password' => 'hashed',
            'grade' => 'integer',
        ];
    }

    /** Логин уникален без учёта регистра — храним в нижнем. */
    protected function username(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    /** Дети родителя (аккаунты, которыми он управляет). */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'parent_child', 'parent_id', 'child_id')->withTimestamps();
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'parent_child', 'child_id', 'parent_id')->withTimestamps();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return app(MediaStorage::class)->url($this->avatar_path);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(RoleName::admin->value);
    }

    /** Персонал (admin, moderator и все, кому открыта админка): блокирует только admin. */
    public function isStaff(): bool
    {
        return $this->hasAnyRole([RoleName::admin->value, RoleName::moderator->value])
            || $this->checkPermissionTo(PermissionName::adminAccess->value);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(UserBlock::class);
    }

    public function activeBlock(): ?UserBlock
    {
        return $this->blocks()->active()->latest('id')->first();
    }

    public function isBlocked(): bool
    {
        return $this->blocks()->active()->exists();
    }

    /** Без действующей блокировки — для скрытия материалов заблокированных авторов из каталога. */
    public function scopeNotBlocked(Builder $query): void
    {
        $query->whereDoesntHave('blocks', fn (Builder $blocks) => $blocks->active());
    }

    public function authBlock(): ?array
    {
        $block = $this->activeBlock();

        return $block ? ['reason' => $block->reason, 'until' => $block->until] : null;
    }

    public function assignRegistrationRole(string $role): void
    {
        $this->syncRoles([$role]);
    }

    public function authProfile(): array
    {
        $permissions = $this->isAdmin()
            ? Permission::query()->where('guard_name', $this->guard_name)->pluck('name')
            : $this->getAllPermissions()->pluck('name');

        return [
            'username' => $this->username,
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $permissions->sort()->values()->all(),
            'teacher_verified' => $this->hasRole(RoleName::teacher->value)
                && $permissions->contains(PermissionName::catalogSubmit->value),
            'teacher_verification' => $this->latestTeacherVerification()->first()?->statusSummary(),
        ];
    }

    /** «Проверенный учитель»: роль teacher и право catalog.submit (выдаётся одобрением заявки). */
    public function isVerifiedTeacher(): bool
    {
        return $this->hasRole(RoleName::teacher->value)
            && $this->checkPermissionTo(PermissionName::catalogSubmit->value);
    }

    public function teacherVerifications(): HasMany
    {
        return $this->hasMany(TeacherVerification::class);
    }

    public function latestTeacherVerification(): HasOne
    {
        return $this->hasOne(TeacherVerification::class)->latestOfMany();
    }
}
