{{-- Фильтр очереди заявок по статусу (фильтры Backpack — в платной версии) --}}
@use('App\Enums\TeacherVerificationStatus')
@use('App\Http\Controllers\Admin\TeacherVerificationCrudController')
@php
    $current = TeacherVerificationCrudController::statusFilter()?->value ?? TeacherVerificationCrudController::ALL_STATUSES;
    $options = collect(TeacherVerificationStatus::cases())
        ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
        ->put(TeacherVerificationCrudController::ALL_STATUSES, 'Все');
@endphp
<div class="btn-group ms-2" role="group" aria-label="Статус заявки">
    @foreach ($options as $value => $label)
        <a href="{{ url($crud->route).'?status='.$value }}"
           class="btn {{ $current === $value ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
    @endforeach
</div>
