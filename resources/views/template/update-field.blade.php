@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row mb-3">
        <div class="col-md-12">
            <a href="{{ route('template.details', ['template_id' => $template->id]) }}" class="btn btn-sm btn-secondary text-white">
                <i class="bi bi-arrow-left"></i>
            </a>
        </div>
    </div>
    @if ($template->gitCredentials)
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="alert alert-secondary mb-0 d-flex align-items-center gap-3">
                    <i class="bi bi-git fs-5"></i>
                    {{ __('This template is synced from a Git repository. Changing the template manually may result in unexpected behavior!') }}
                </div>
            </div>
        </div>
    @endif
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card border border-secondary mb-3">
                <div class="card-header">{{ __('Update field') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('template.field.update.action', ['template_id' => $template->id, 'field_id' => $field->id]) }}">
                        @csrf
                        <input type="hidden" name="template_id" value="{{ $template->id }}">

                        <div class="row mb-3">
                            <label for="template" class="col-md-4 col-form-label text-md-end">{{ __('Template') }}</label>

                            <div class="col-md-6">
                                <input id="template" type="text" class="form-control" value="{{ $template->name }}" required readonly>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="key" class="col-md-4 col-form-label text-md-end">{{ __('Type') }}</label>

                            <div class="col-md-6">
                                <select id="type" class="form-control @error('type') is-invalid @enderror" name="type">
                                    <option value="input_text"{{ (old('type') ?? $field->type) == 'input_text' ? ' selected' : '' }}>{{ __('Text') }}</option>
                                    <option value="input_number"{{ (old('type') ?? $field->type) == 'input_number' ? ' selected' : '' }}>{{ __('Number') }}</option>
                                    <option value="input_range"{{ (old('type') ?? $field->type) == 'input_range' ? ' selected' : '' }}>{{ __('Range') }}</option>
                                    <option value="input_radio"{{ (old('type') ?? $field->type) == 'input_radio' ? ' selected' : '' }}>{{ __('Radio') }}</option>
                                    <option value="input_radio_image"{{ (old('type') ?? $field->type) == 'input_radio_image' ? ' selected' : '' }}>{{ __('Radio image') }}</option>
                                    <option value="input_checkbox"{{ (old('type') ?? $field->type) == 'input_checkbox' ? ' selected' : '' }}>{{ __('Checkbox') }}</option>
                                    <option value="input_hidden"{{ (old('type') ?? $field->type) == 'input_hidden' ? ' selected' : '' }}>{{ __('Hidden text') }}</option>
                                    <option value="select"{{ (old('type') ?? $field->type) == 'select' ? ' selected' : '' }}>{{ __('Select') }}</option>
                                    <option value="textarea"{{ (old('type') ?? $field->type) == 'textarea' ? ' selected' : '' }}>{{ __('Textarea') }}</option>
                                </select>

                                @error('type')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="label" class="col-md-4 col-form-label text-md-end">{{ __('Label') }}</label>

                            <div class="col-md-6">
                                <input id="label" type="text" class="form-control @error('label') is-invalid @enderror" name="label" value="{{ old('label') ?? $field->label }}">

                                @error('label')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="key" class="col-md-4 col-form-label text-md-end">{{ __('Key') }}</label>

                            <div class="col-md-6">
                                <input id="key" type="text" class="form-control @error('key') is-invalid @enderror" name="key" value="{{ old('key') ?? $field->key }}">

                                @error('key')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="value" class="col-md-4 col-form-label text-md-end">{{ __('Value') }}</label>

                            <div class="col-md-6">
                                <input id="value" type="text" class="form-control @error('value') is-invalid @enderror" name="value" value="{{ old('value') ?? $field->value }}">

                                @error('value')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <label for="advanced" class="col-md-4 col-form-label text-md-end">{{ __('Advanced') }}</label>

                            <div class="col-md-6">
                                <input id="advanced" type="checkbox" class="form-check-input" name="advanced" value="1" {{ old('advanced') ?? $field->advanced ? 'checked' : '' }}>

                                @error('advanced')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <label for="required" class="col-md-4 col-form-label text-md-end">{{ __('Required') }}</label>

                            <div class="col-md-6">
                                <input id="required" type="checkbox" class="form-check-input" name="required" value="1" {{ old('required') ?? $field->required ? 'checked' : '' }}>

                                @error('required')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <label for="secret" class="col-md-4 col-form-label text-md-end">{{ __('Secret') }}</label>

                            <div class="col-md-6">
                                <input id="secret" type="checkbox" class="form-check-input" name="secret" value="1" {{ old('secret') ?? $field->secret ? 'checked' : '' }}>

                                @error('secret')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <label for="set_on_create" class="col-md-4 col-form-label text-md-end">{{ __('Set on create') }}</label>

                            <div class="col-md-6">
                                <input id="set_on_create" type="checkbox" class="form-check-input" name="set_on_create" value="1" {{ old('set_on_create') ?? $field->set_on_create ? 'checked' : '' }}>

                                @error('set_on_create')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="row mb-3 align-items-center">
                            <label for="set_on_update" class="col-md-4 col-form-label text-md-end">{{ __('Set on update') }}</label>

                            <div class="col-md-6">
                                <input id="set_on_update" type="checkbox" class="form-check-input" name="set_on_update" value="1" {{ old('set_on_update') ?? $field->set_on_update ? 'checked' : '' }}>

                                @error('set_on_update')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="border rounded py-4 mb-3" id="options" @if ($field->type !== 'input_range' && $field->type !== 'input_number') style="display: none" @endif>
                            <div class="row mb-3">
                                <div class="col-md-6 offset-md-4">
                                    <h5>{{ __('Options') }}</h5>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="min" class="col-md-4 col-form-label text-md-end">{{ __('Min') }}</label>

                                <div class="col-md-6">
                                    <input id="min" type="number" class="form-control @error('min') is-invalid @enderror" name="min" value="{{ old('min') ?? $field->min }}">

                                    @error('min')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label for="max" class="col-md-4 col-form-label text-md-end">{{ __('Max') }}</label>

                                <div class="col-md-6">
                                    <input id="max" type="number" class="form-control @error('max') is-invalid @enderror" name="max" value="{{ old('max') ?? $field->max }}">

                                    @error('max')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <label for="step" class="col-md-4 col-form-label text-md-end">{{ __('Step') }}</label>

                                <div class="col-md-6">
                                    <input id="step" type="number" class="form-control @error('step') is-invalid @enderror" name="step" value="{{ old('step') ?? $field->step }}">

                                    @error('step')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row mb-0">
                            <div class="col-md-8 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Submit') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            @if ($field->type === 'input_radio' || $field->type === 'input_radio_image' || $field->type === 'select')
            <div class="card border border-secondary">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __('Options') }}</span>
                    <a href="{{ route('template.field.option.add', ['template_id' => $template->id, 'field_id' => $field->id]) }}" class="btn btn-sm btn-primary" title="{{ __('Add') }}">
                        <i class="bi bi-plus"></i>
                    </a>
                </div>

                <div class="card-body d-flex flex-column gap-4 p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="font-monospace">
                                <tr class="align-middle">
                                    <th class="w-100" scope="col">{{ __('Label') }}</th>
                                    <th scope="col">{{ __('Value') }}</th>
                                    <th scope="col">{{ __('Default') }}</th>
                                    <th scope="col">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($options as $option)
                                    <tr class="align-middle">
                                        <td>{{ $option->label }}</td>
                                        <td>{{ $option->value }}</td>
                                        <td>
                                            @if ($option->default)
                                                <i class="bi bi-check-circle text-success fs-5"></i>
                                            @else
                                                <i class="bi bi-x-circle text-danger fs-5"></i>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="{{ route('template.field.option.update', ['template_id' => $template->id, 'field_id' => $field->id, 'option_id' => $option->id]) }}" class="btn btn-sm btn-warning" title="{{ __('Update') }}">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="{{ route('template.field.option.delete.action', ['template_id' => $template->id, 'field_id' => $field->id, 'option_id' => $option->id]) }}" class="btn btn-sm btn-danger" title="{{ __('Delete') }}">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $options->links('pagination::bootstrap-5') }}
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        $('#type').change(function() {
            $('#options').toggle($(this).val() === 'input_range' || $(this).val() === 'input_number');
        });
    });
</script>
@endsection
