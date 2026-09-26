@if ($crud->hasAccess('block', $entry))
    @if ($entry->isBlocked())
        <form method="post" action="{{ url($crud->route.'/'.$entry->getKey().'/unblock') }}" class="d-inline"
              onsubmit="return confirm('Разблокировать пользователя?')">
            @csrf
            <button type="submit" bp-button="unblock" class="btn btn-sm btn-link">
                <i class="la la-unlock"></i> <span>Разблокировать</span>
            </button>
        </form>
    @else
        <a href="{{ url($crud->route.'/'.$entry->getKey().'/block') }}" bp-button="block" class="btn btn-sm btn-link">
            <i class="la la-ban"></i> <span>Заблокировать</span>
        </a>
    @endif
@endif
