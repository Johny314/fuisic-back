{{-- Фильтры журнала (фильтры Backpack — в платной версии): GET-параметры, их же получает поиск таблицы --}}
@use('App\Enums\AuditEvent')
@use('App\Http\Controllers\Admin\AuditLogCrudController')
@use('App\Services\AuditLog')
@php
    $filters = AuditLogCrudController::filters();
    $causers = AuditLogCrudController::causerOptions();
@endphp
<form method="GET" action="{{ url($crud->route) }}" class="row g-2 align-items-end mt-2 mb-2">
    <div class="col-auto">
        <label class="form-label mb-0" for="audit-causer">Кто</label>
        <select id="audit-causer" name="causer" class="form-select form-select-sm">
            <option value="">Все</option>
            <option value="{{ AuditLogCrudController::SYSTEM }}" @selected($filters['causer'] === AuditLogCrudController::SYSTEM)>Система</option>
            @foreach ($causers as $id => $label)
                <option value="{{ $id }}" @selected($filters['causer'] === (string) $id)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label mb-0" for="audit-event">Действие</label>
        <select id="audit-event" name="event" class="form-select form-select-sm">
            <option value="">Все</option>
            @foreach (AuditEvent::cases() as $event)
                <option value="{{ $event->value }}" @selected($filters['event'] === $event->value)>{{ $event->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label mb-0" for="audit-subject">Объект</label>
        <select id="audit-subject" name="subject" class="form-select form-select-sm">
            <option value="">Все</option>
            @foreach (AuditLog::SUBJECTS as $key => [, $label])
                <option value="{{ $key }}" @selected($filters['subject'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label mb-0" for="audit-subject-id">ID объекта</label>
        <input id="audit-subject-id" type="number" min="1" name="subject_id" value="{{ $filters['subject_id'] }}" class="form-control form-control-sm" style="width: 7rem">
    </div>
    <div class="col-auto">
        <label class="form-label mb-0" for="audit-from">С</label>
        <input id="audit-from" type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <label class="form-label mb-0" for="audit-to">По</label>
        <input id="audit-to" type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Показать</button>
        <a href="{{ url($crud->route) }}" class="btn btn-sm btn-outline-secondary">Сбросить</a>
    </div>
</form>
