@extends('layouts.app')
@section('page-title', 'Transaction Types')
@section('page-subtitle', 'Create the types you can pick for each transaction on the Transactions page')

@push('styles')
<style>
    .color-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; vertical-align: middle; }
    .color-dot.danger { background: var(--red); }
    .color-dot.success { background: var(--green); }
    .color-dot.info { background: var(--info); }
    .color-dot.primary { background: var(--accent-soft); }
    .color-dot.warning { background: var(--warn-fg); }
    .color-dot.secondary { background: var(--text-muted); }
</style>
@endpush

@section('content')
@php($editing = old('_method') === 'PUT')
<div class="container-fluid">
    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Add Type</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route('types.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-5">
                    <label class="form-label" for="name">Type name</label>
                    <input type="text" id="name" name="name" maxlength="40" required
                           value="{{ $editing ? '' : old('name') }}"
                           class="form-control" placeholder="e.g. Salary, Rent, Refund, Owner Draw">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="color">Color</label>
                    <select id="color" name="color" class="form-select color-select" data-preview="addPreview">
                        @foreach ($colors as $value => $label)
                            <option value="{{ $value }}" @selected(($editing ? 'primary' : old('color', 'primary')) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Preview</label>
                    <div><span class="badge bg-primary" id="addPreview">New type</span></div>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i> Add</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Type List</h5></div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-striped table-hover compact mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th width="18%">Color</th>
                            <th width="16%" class="text-end">Transactions</th>
                            <th width="14%" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($types as $type)
                            <tr>
                                <td><span class="badge bg-{{ $type->color }}">{{ $type->name }}</span></td>
                                <td><span class="color-dot {{ $type->color }}"></span>{{ $colors[$type->color] ?? $type->color }}</td>
                                <td class="text-end">{{ $type->transactions_count }}</td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="icon-btn edit-btn" title="Edit" aria-label="Edit {{ $type->name }}"
                                            data-url="{{ route('types.update', $type) }}"
                                            data-name="{{ $type->name }}" data-color="{{ $type->color }}">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form method="POST" action="{{ route('types.destroy', $type) }}" class="d-inline delete-form"
                                          data-name="{{ $type->name }}" data-count="{{ $type->transactions_count }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="icon-btn danger" title="Delete" aria-label="Delete {{ $type->name }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No types yet — add one above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content" id="editForm">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title" id="editModalTitle"><i class="bi bi-tags me-2"></i>Edit Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="editName">Type name</label>
                    <input type="text" id="editName" name="name" maxlength="40" required class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="editColor">Color</label>
                    <select id="editColor" name="color" class="form-select color-select" data-preview="editPreview">
                        @foreach ($colors as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="form-label">Preview</label>
                <div><span class="badge" id="editPreview"></span></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Live badge preview next to each colour picker.
    function paintPreview(select) {
        const preview = document.getElementById(select.dataset.preview);
        const nameInput = select.closest('form').querySelector('input[name="name"]');
        preview.className = 'badge bg-' + select.value;
        preview.textContent = nameInput.value.trim() || 'New type';
    }
    document.querySelectorAll('.color-select').forEach(select => {
        const nameInput = select.closest('form').querySelector('input[name="name"]');
        select.addEventListener('change', () => paintPreview(select));
        nameInput.addEventListener('input', () => paintPreview(select));
        paintPreview(select);
    });

    const editModal = new bootstrap.Modal(document.getElementById('editModal'));
    const editForm = document.getElementById('editForm');

    document.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', () => {
        editForm.action = btn.dataset.url;
        editForm.elements.name.value = btn.dataset.name;
        editForm.elements.color.value = btn.dataset.color;
        paintPreview(document.getElementById('editColor'));
        editModal.show();
    }));

    document.querySelectorAll('.delete-form').forEach(form => form.addEventListener('submit', (e) => {
        const count = Number(form.dataset.count);
        const message = `Delete the type "${form.dataset.name}"?` + (count
            ? `\n\n${count} transaction${count === 1 ? '' : 's'} using it will become "Not classified".`
            : '');
        if (!confirm(message)) e.preventDefault();
    }));
</script>
@endpush
