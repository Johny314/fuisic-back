<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\Activity;
use App\Models\Card\CardSet;
use App\Models\Role;
use App\Models\Section;
use App\Models\TeacherVerification;
use App\Models\Test\Test;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Support\CauserResolver;

/**
 * Журнал действий персонала (spatie/laravel-activitylog, таблица activity_log).
 * Изменения записей пишутся только внутри asStaff() — в админке (RecordAdminActivity);
 * действия API обычных пользователей в журнал не попадают.
 */
class AuditLog
{
    public const string LOG_NAME = 'audit';

    /** Значение секретного поля в журнале: изменилось, но значение не пишется. */
    public const string HIDDEN = '(скрыто)';

    /** Типы объектов журнала: ключ фильтра => [класс, подпись]. */
    public const array SUBJECTS = [
        'user' => [User::class, 'Пользователь'],
        'role' => [Role::class, 'Роль'],
        'section' => [Section::class, 'Раздел'],
        'card-set' => [CardSet::class, 'Набор карточек'],
        'test' => [Test::class, 'Тест'],
        'teacher-verification' => [TeacherVerification::class, 'Заявка учителя'],
    ];

    private bool $recordsModelChanges = false;

    /** Внутри колбэка изменения моделей с AuditsAdminChanges пишутся в журнал от имени $actor. */
    public function asStaff(User $actor, Closure $callback): mixed
    {
        $previous = $this->recordsModelChanges;
        $this->recordsModelChanges = true;

        try {
            return app(CauserResolver::class)->withCauser($actor, $callback);
        } finally {
            $this->recordsModelChanges = $previous;
        }
    }

    public function recordsModelChanges(): bool
    {
        return $this->recordsModelChanges;
    }

    /**
     * Запись действия. $actor = null — «система» (планировщик).
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $properties
     */
    public function record(
        AuditEvent $event,
        Model $subject,
        ?User $actor,
        array $old = [],
        array $new = [],
        array $properties = [],
        ?string $description = null,
    ): ?Activity {
        $logger = activity(self::LOG_NAME)
            ->event($event->value)
            ->performedOn($subject)
            ->withChanges(array_filter(['attributes' => $new, 'old' => $old], fn (array $values) => $values !== []))
            ->withProperties($properties);

        $actor ? $logger->causedBy($actor) : $logger->causedByAnonymous();

        /** @var Activity|null */
        return $logger->log($description ?? $event->label());
    }

    /**
     * Изменение набора (права роли, роли пользователя): пишется, только если он поменялся.
     *
     * @param  iterable<string>  $before
     * @param  iterable<string>  $after
     */
    public function recordSetChange(AuditEvent $event, Model $subject, ?User $actor, string $field, iterable $before, iterable $after): void
    {
        $before = collect($before)->sort()->values()->all();
        $after = collect($after)->sort()->values()->all();

        if ($before === $after) {
            return;
        }

        $this->record($event, $subject, $actor, [$field => $before], [$field => $after], [
            'added' => array_values(array_diff($after, $before)),
            'removed' => array_values(array_diff($before, $after)),
        ]);
    }

    /** Ключ фильтра и подпись типа объекта по классу модели. */
    public static function subjectKey(?string $class): ?string
    {
        foreach (self::SUBJECTS as $key => [$subjectClass]) {
            if ($subjectClass === $class) {
                return $key;
            }
        }

        return null;
    }

    public static function subjectLabel(?string $class): string
    {
        $key = self::subjectKey($class);

        return $key ? self::SUBJECTS[$key][1] : ($class ? class_basename($class) : '—');
    }
}
