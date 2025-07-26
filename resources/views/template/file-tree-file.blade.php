<li class="d-flex justify-content-between align-items-start flex-row file-tree-li">
    <a href="{{ route('template.details_file', ['template_id' => $template->id, 'file_id' => $structure->id]) }}" class="d-flex align-items-center gap-2">
        <i class="bi bi-file-earmark"></i> {{ $structure->name }}
        @if ($template->type === 'cluster' && isset($structure->sort))
            <span class="badge bg-secondary py-1 px-2 aspect-ratio-1 rounded-pill badge-count ms-auto">{{ $structure->sort }}</span>
        @endif
    </a>
    <div class="file-tree-li-actions">
        <a href="{{ route('template.file.update', ['template_id' => $template->id, 'file_id' => $structure->id]) }}" class="btn btn-sm btn-warning text-white p-1 lh-1" title="{{ __('Update') }}">
            <i class="bi bi-pencil file-tree-action"></i>
        </a>
        <a href="{{ route('template.file.delete.action', ['template_id' => $template->id, 'file_id' => $structure->id]) }}" class="btn btn-sm btn-danger text-white p-1 lh-1" title="{{ __('Delete') }}">
            <i class="bi bi-trash file-tree-action"></i>
        </a>
    </div>
</li>
