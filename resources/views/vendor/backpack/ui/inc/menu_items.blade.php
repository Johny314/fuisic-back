{{-- This file is used for menu items by any Backpack v6 theme --}}
{{-- Пункты — по тем же правам, что и разделы (Admin\Concerns\AuthorizesCrud) --}}
@php($can = fn (\App\Enums\PermissionName $permission): bool => (bool) backpack_user()?->can($permission->value))
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>

@if ($can(\App\Enums\PermissionName::usersView))
    <x-backpack::menu-item title="Пользователи" icon="la la-user" :link="backpack_url('user')" />
@endif
@if ($can(\App\Enums\PermissionName::rolesManage))
    <x-backpack::menu-item title="Роли" icon="la la-user-shield" :link="backpack_url('role')" />
@endif
@if ($can(\App\Enums\PermissionName::catalogManage))
    <x-backpack::menu-item title="Разделы" icon="la la-folder" :link="backpack_url('section')" />
    <x-backpack::menu-item title="Наборы карточек" icon="la la-clone" :link="backpack_url('card-set')" />
    <x-backpack::menu-item title="Тесты" icon="la la-tasks" :link="backpack_url('test')" />
    <x-backpack::menu-item title="Вопросы тестов" icon="la la-question-circle" :link="backpack_url('task')" />
@endif
@if ($can(\App\Enums\PermissionName::teachersVerify))
    <x-backpack::menu-item title="Заявки учителей" icon="la la-user-check" :link="backpack_url('teacher-verification')" />
@endif
@if ($can(\App\Enums\PermissionName::auditView))
    <x-backpack::menu-item title="Журнал действий" icon="la la-history" :link="backpack_url('audit-log')" />
@endif
