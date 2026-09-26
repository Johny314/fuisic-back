{{-- Галочки без JS: отправляет name[] = выбранные ключи; пустой hidden — чтобы снять все. --}}
@php
    $selected = collect(old_empty_or_null($field['name'], []) ?? $field['value'] ?? $field['default'] ?? [])
        ->map(fn ($value) => (string) $value)
        ->all();
    $columns = $field['number_of_columns'] ?? 2;
@endphp

@include('crud::fields.inc.wrapper_start')
    <label>{!! $field['label'] !!}</label>
    <input type="hidden" name="{{ $field['name'] }}" value="">
    <div class="row">
        @foreach ($field['options'] as $key => $option)
            <div class="col-sm-{{ intval(12 / $columns) }}">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="{{ $field['name'] }}[]" value="{{ $key }}"
                           id="{{ $field['name'] }}_{{ $key }}" @checked(in_array((string) $key, $selected, true))>
                    <label class="form-check-label font-weight-normal fw-normal" for="{{ $field['name'] }}_{{ $key }}">{{ $option }}</label>
                </div>
            </div>
        @endforeach
    </div>

    @error($field['name'])
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror

    @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif
@include('crud::fields.inc.wrapper_end')
