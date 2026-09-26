@extends('crud::show')

@use('App\Enums\TeacherVerificationStatus')

@section('content')
    @parent

    <div class="row mt-3">
        <div class="{{ $crud->getShowContentClass() }}">
            <div class="card">
                <div class="card-body">
                    @if ($entry->status === TeacherVerificationStatus::pending)
                        <h3 class="card-title">Решение по заявке</h3>
                        <p class="text-muted">Комментарий увидит учитель — в приложении и в письме. При отказе он обязателен.</p>
                    @elseif ($entry->status === TeacherVerificationStatus::approved)
                        <h3 class="card-title">Отозвать статус «Проверенный учитель»</h3>
                        <p class="text-muted">Учитель потеряет право публикации в каталог. Причину он увидит в приложении и в письме.</p>
                    @else
                        <p class="mb-0 text-muted">Решение принято: {{ $entry->status->label() }}. Учитель может подать новую заявку.</p>
                    @endif

                    @if (in_array($entry->status, [TeacherVerificationStatus::pending, TeacherVerificationStatus::approved], true))
                        <form method="POST" id="teacher-verification-decision">
                            @csrf
                            <div class="mb-3">
                                <label for="reviewer_comment" class="form-label">Комментарий проверяющего</label>
                                <textarea name="reviewer_comment" id="reviewer_comment" rows="3" maxlength="2000"
                                          class="form-control @error('reviewer_comment') is-invalid @enderror">{{ old('reviewer_comment') }}</textarea>
                                @error('reviewer_comment')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            @if ($entry->status === TeacherVerificationStatus::pending)
                                <button type="submit" class="btn btn-success"
                                        formaction="{{ url($crud->route.'/'.$entry->getKey().'/approve') }}">
                                    <i class="la la-check"></i> Одобрить
                                </button>
                                <button type="submit" class="btn btn-outline-danger"
                                        formaction="{{ url($crud->route.'/'.$entry->getKey().'/reject') }}">
                                    <i class="la la-times"></i> Отклонить
                                </button>
                            @else
                                <button type="submit" class="btn btn-danger"
                                        formaction="{{ url($crud->route.'/'.$entry->getKey().'/revoke') }}">
                                    <i class="la la-ban"></i> Отозвать статус
                                </button>
                            @endif
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
