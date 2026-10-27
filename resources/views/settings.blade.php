@extends('layouts.app')
@section('page-title', 'Settings')
@section('page-subtitle', 'Plaid account credentials used to connect banks and sync transactions')

@section('page-actions')
    @if ($configured)
        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Plaid connected</span>
    @else
        <span class="badge bg-warning"><i class="bi bi-exclamation-triangle me-1"></i> Not configured</span>
    @endif
@endsection

@section('content')
<div class="container-fluid">
    @unless ($configured)
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle"></i>
            <div>Add your Plaid credentials below. Until they are saved, banks cannot be connected and transactions cannot be synced.</div>
        </div>
    @endunless

    <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-bank2 me-2"></i>Plaid Account</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route('settings.update') }}" class="row g-3" autocomplete="off">
                @csrf
                @method('PUT')

                <div class="col-md-6">
                    <label class="form-label" for="plaid_client_id">Client ID</label>
                    <input type="text" id="plaid_client_id" name="plaid_client_id"
                           value="{{ old('plaid_client_id', $clientId) }}"
                           class="form-control @error('plaid_client_id') is-invalid @enderror" required>
                    @error('plaid_client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="plaid_secret">Secret</label>
                    <div class="input-group">
                        <input type="password" id="plaid_secret" name="plaid_secret"
                               class="form-control @error('plaid_secret') is-invalid @enderror"
                               placeholder="{{ $hasSecret ? '•••••••••••• saved — leave blank to keep' : 'Paste your Plaid secret' }}"
                               {{ $hasSecret ? '' : 'required' }} autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary" id="toggleSecret" title="Show / hide" aria-label="Show or hide secret">
                            <i class="bi bi-eye"></i>
                        </button>
                        @error('plaid_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-text text-muted">Stored encrypted. Use the secret that matches the environment you pick.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="plaid_env">Environment</label>
                    <select id="plaid_env" name="plaid_env" class="form-select @error('plaid_env') is-invalid @enderror" required>
                        <option value="sandbox" @selected(old('plaid_env', $env) === 'sandbox')>Sandbox (test data)</option>
                        <option value="production" @selected(old('plaid_env', $env) === 'production')>Production (real banks)</option>
                    </select>
                    @error('plaid_env')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="plaid_client_name">App name shown in Plaid</label>
                    <input type="text" id="plaid_client_name" name="plaid_client_name" maxlength="30"
                           value="{{ old('plaid_client_name', $clientName ?? config('app.name')) }}"
                           class="form-control @error('plaid_client_name') is-invalid @enderror" required>
                    @error('plaid_client_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 d-flex align-items-center gap-3 flex-wrap">
                    <button type="submit" class="btn btn-primary" id="saveSettings">
                        <i class="bi bi-shield-check me-1"></i> Verify &amp; Save
                    </button>
                    <span class="text-muted small">
                        Find these in the Plaid Dashboard → Developers → Keys.
                        Changing the Client ID or Environment means banks connected with the old keys must be connected again.
                    </span>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('toggleSecret').addEventListener('click', function () {
        const input = document.getElementById('plaid_secret');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        this.innerHTML = `<i class="bi ${show ? 'bi-eye-slash' : 'bi-eye'}"></i>`;
    });

    document.querySelector('form').addEventListener('submit', function () {
        const btn = document.getElementById('saveSettings');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verifying…';
    });
</script>
@endpush
