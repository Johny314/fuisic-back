<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Admin\Concerns\AuthorizesCrud;
use App\Models\Activity;
use App\Models\User;
use App\Services\AuditLog;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Журнал действий персонала (audit.view): только просмотр — операций и маршрутов
 * изменения и удаления нет. Фильтры — query-параметры (фильтры Backpack в платной версии).
 */
class AuditLogCrudController extends CrudController
{
    use AuthorizesCrud;
    use ListOperation;
    use ShowOperation;

    public const string SYSTEM = 'system';

    public function setup()
    {
        CRUD::setModel(Activity::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/audit-log');
        CRUD::setEntityNameStrings('запись журнала', 'Журнал действий');
        CRUD::addBaseClause('where', 'log_name', AuditLog::LOG_NAME);

        $this->authorizeCrud(PermissionName::auditView);
    }

    /**
     * Фильтры из query: causer (id или system), event, subject (ключ AuditLog::SUBJECTS), subject_id, from, to (Y-m-d).
     *
     * @return array{causer: ?string, event: ?string, subject: ?string, subject_id: ?int, from: ?string, to: ?string}
     */
    public static function filters(): array
    {
        $query = request()->query();
        $string = fn (string $key): ?string => is_string($query[$key] ?? null) && $query[$key] !== '' ? $query[$key] : null;
        $date = fn (string $key): ?string => ($value = $string($key)) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
        $causer = $string('causer');
        $event = $string('event');
        $subject = $string('subject');
        $subjectId = $string('subject_id');

        return [
            'causer' => $causer === self::SYSTEM || ctype_digit((string) $causer) ? $causer : null,
            'event' => AuditEvent::tryFrom((string) $event)?->value,
            'subject' => array_key_exists((string) $subject, AuditLog::SUBJECTS) ? $subject : null,
            'subject_id' => ctype_digit((string) $subjectId) ? (int) $subjectId : null,
            'from' => $date('from'),
            'to' => $date('to'),
        ];
    }

    /** Авторы, у которых есть записи в журнале: id => подпись. */
    public static function causerOptions(): Collection
    {
        $ids = Activity::query()->where('log_name', AuditLog::LOG_NAME)
            ->whereNotNull('causer_id')->distinct()->pluck('causer_id');

        return User::withTrashed()->whereKey($ids)->orderBy('name')->get()
            ->mapWithKeys(fn (User $user) => [$user->id => $user->name.' ('.($user->email ?? $user->username).')']);
    }

    protected function setupListOperation()
    {
        $filters = self::filters();

        CRUD::addClause('with', ['causer', 'subject']);
        CRUD::orderBy('id', 'desc');
        CRUD::addButton('top', 'audit_filters', 'view', 'admin.audit-log.filters');
        CRUD::setOperationSetting('searchableTable', false);

        match ($filters['causer']) {
            null => null,
            self::SYSTEM => CRUD::addClause('whereNull', 'causer_id'),
            default => CRUD::addClause('where', fn ($query) => $query
                ->where('causer_type', (new User)->getMorphClass())
                ->where('causer_id', (int) $filters['causer'])),
        };

        if ($filters['event']) {
            CRUD::addClause('where', 'event', $filters['event']);
        }

        if ($filters['subject']) {
            CRUD::addClause('where', 'subject_type', AuditLog::SUBJECTS[$filters['subject']][0]);
        }

        if ($filters['subject_id']) {
            CRUD::addClause('where', 'subject_id', $filters['subject_id']);
        }

        if ($filters['from']) {
            CRUD::addClause('where', 'created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if ($filters['to']) {
            CRUD::addClause('where', 'created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        $this->addCommonColumns();
        CRUD::addColumn([
            'name' => 'changes',
            'label' => 'Изменения',
            'type' => 'custom_html',
            'value' => fn (Activity $entry) => $entry->changeRows()
                ->map(fn (array $row) => '<b>'.e($row['field']).'</b>: '.e(self::shorten($row['old'])).' → '.e(self::shorten($row['new'])))
                ->implode('<br>') ?: '—',
            'orderable' => false,
        ]);
        CRUD::column('ip_address')->label('IP')->type('text')->orderable(false);
    }

    protected function setupShowOperation()
    {
        $this->addCommonColumns();
        CRUD::column('description')->label('Описание')->type('text');
        CRUD::column('ip_address')->label('IP')->type('text');
        CRUD::addColumn([
            'name' => 'changes',
            'label' => 'Было / стало',
            'type' => 'custom_html',
            'value' => fn (Activity $entry) => $entry->changeRows()->isEmpty() ? '—'
                : '<table class="table table-sm mb-0"><thead><tr><th>Поле</th><th>Было</th><th>Стало</th></tr></thead><tbody>'
                    .$entry->changeRows()->map(fn (array $row) => '<tr><td>'.e($row['field']).'</td><td>'.e($row['old'])
                        .'</td><td>'.e($row['new']).'</td></tr>')->implode('')
                    .'</tbody></table>',
        ]);
        CRUD::addColumn([
            'name' => 'properties',
            'label' => 'Дополнительно',
            'type' => 'closure',
            'function' => fn (Activity $entry) => $entry->properties?->isNotEmpty()
                ? json_encode($entry->properties, JSON_UNESCAPED_UNICODE)
                : '—',
        ]);
    }

    private function addCommonColumns(): void
    {
        CRUD::column('created_at')->label('Время')->type('datetime')->format('DD.MM.YYYY HH:mm:ss');
        CRUD::addColumn([
            'name' => 'causer',
            'label' => 'Кто',
            'type' => 'closure',
            'function' => fn (Activity $entry) => $entry->causerLabel(),
            'orderable' => false,
        ]);
        CRUD::addColumn([
            'name' => 'event',
            'label' => 'Действие',
            'type' => 'closure',
            'function' => fn (Activity $entry) => $entry->eventLabel(),
        ]);
        CRUD::addColumn([
            'name' => 'subject',
            'label' => 'Объект',
            'type' => 'closure',
            'function' => fn (Activity $entry) => $entry->subjectLabel(),
            'orderable' => false,
        ]);
    }

    private static function shorten(string $value): string
    {
        return mb_strimwidth($value, 0, 80, '…');
    }
}
