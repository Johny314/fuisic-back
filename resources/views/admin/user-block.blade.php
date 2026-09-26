@extends(backpack_view('blank'))

@php
    $breadcrumbs = [
        trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
        $crud->entity_name_plural => url($crud->route),
        'Блокировка' => false,
    ];
@endphp

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h1 class="mb-0">Блокировка</h1>
        <p class="ms-2 ml-2 mb-0">{{ $entry->name }} ({{ $entry->email }})</p>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ url($crud->route.'/'.$entry->getKey().'/block') }}">
            @csrf
            <div class="card">
                <div class="card-body">
                    <div class="mb-3 form-group">
                        <label class="form-label" for="reason">Причина <span class="text-danger">*</span></label>
                        <textarea name="reason" id="reason" rows="3" class="form-control" required maxlength="1000">{{ old('reason') }}</textarea>
                        <small class="form-text text-muted">Видна пользователю при входе.</small>
                    </div>
                    <div class="mb-3 form-group">
                        <label class="form-label" for="comment">Внутренний комментарий</label>
                        <textarea name="comment" id="comment" rows="3" class="form-control" maxlength="5000">{{ old('comment') }}</textarea>
                        <small class="form-text text-muted">Только для персонала.</small>
                    </div>
                    <div class="mb-3 form-group">
                        <label class="form-label" for="until">Срок</label>
                        <input type="datetime-local" name="until" id="until" class="form-control" value="{{ old('until') }}">
                        <small class="form-text text-muted">Пусто — бессрочно. Время {{ config('app.timezone') }}.</small>
                    </div>
                    <p class="text-muted mb-0">Все API-токены и сессии админки пользователя будут отозваны, его материалы скрыты из каталога.</p>
                </div>
            </div>
            <button type="submit" class="btn btn-danger"><i class="la la-ban"></i> Заблокировать</button>
            <a href="{{ url($crud->route) }}" class="btn btn-secondary">{{ trans('backpack::crud.cancel') }}</a>
        </form>
    </div>
</div>
@endsection
