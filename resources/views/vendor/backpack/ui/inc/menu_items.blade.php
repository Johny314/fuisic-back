{{-- This file is used for menu items by any Backpack v6 theme --}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>

<x-backpack::menu-item title="Пользователи" icon="la la-user" :link="backpack_url('user')" />
<x-backpack::menu-item title="Разделы" icon="la la-folder" :link="backpack_url('section')" />
<x-backpack::menu-item title="Наборы карточек" icon="la la-clone" :link="backpack_url('card-set')" />
<x-backpack::menu-item title="Тесты" icon="la la-tasks" :link="backpack_url('test')" />
@if (backpack_user()?->can(\App\Enums\PermissionName::teachersVerify->value))
    <x-backpack::menu-item title="Заявки учителей" icon="la la-user-check" :link="backpack_url('teacher-verification')" />
@endif
