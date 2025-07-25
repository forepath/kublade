@if ($template->environmentVariables->isEmpty())
    <div class="alert alert-warning mb-0 d-flex align-items-center gap-3">
        <i class="bi bi-exclamation-triangle fs-5"></i>
        {{ __('No environment variables defined') }}
    </div>
@else
    <ul class="field-tree">
        @foreach ($template->environmentVariables as $environmentVariable)
            <li class="d-flex justify-content-between align-items-start flex-row file-tree-li">
                <span class="d-flex align-items-center gap-3">
                    <i class="bi bi-gear"></i>
                    <div class="d-flex flex-column lh-1 align-items-start">
                        {{ $environmentVariable->key }}
                    </div>
                </span>
                <div class="file-tree-li-actions">
                    <a href="{{ route('template.env-variable.update.action', ['template_id' => $template->id, 'env_variable_id' => $environmentVariable->id]) }}" class="btn btn-sm btn-warning text-white p-1 lh-1" title="{{ __('Update') }}">
                        <i class="bi bi-pencil file-tree-action"></i>
                    </a>
                    <a href="{{ route('template.env-variable.delete.action', ['template_id' => $template->id, 'env_variable_id' => $environmentVariable->id]) }}" class="btn btn-sm btn-danger text-white p-1 lh-1" title="{{ __('Delete') }}">
                        <i class="bi bi-trash file-tree-action"></i>
                    </a>
                </div>
            </li>
        @endforeach
    </ul>
@endif
