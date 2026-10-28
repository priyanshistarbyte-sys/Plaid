@extends('layouts.app')
@section('page-title', 'Bank Accounts')
@section('page-subtitle', 'Banks connected through Plaid and the accounts under each one')

@section('page-actions')
    <button type="button" class="btn btn-outline-primary" id="sync" @disabled(!$configured || $banks->isEmpty())>
        <i class="bi bi-arrow-repeat me-1"></i> Sync
    </button>
    <button type="button" class="btn btn-primary" id="connect" @disabled(!$configured)>
        <i class="bi bi-bank me-1"></i> Connect bank
    </button>
@endsection

@push('styles')
<style>
    .bank-head { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .bank-logo {
        width: 40px; height: 40px; border-radius: 10px; flex: 0 0 auto;
        background: linear-gradient(135deg, var(--purple), var(--accent-soft));
        color: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px;
    }
    .bank-name { font-size: 1.05rem; font-weight: 700; color: var(--text-strong); }
    .bank-meta { color: var(--text-muted); font-size: 11px; }
    .acct-name { color: var(--text-strong); font-weight: 600; }
    .acct-sub { color: var(--text-muted); font-size: 11px; }
    .acct-mask { font-family: ui-monospace, Consolas, monospace; letter-spacing: 1px; white-space: nowrap; }
    /* Accounts with no transactions are hidden unless the switch is on. */
    .acct-empty { display: none; }
    .show-empty .acct-empty { display: table-row; }
    .show-empty .acct-empty td { opacity: .7; }
    .show-empty .only-active-note { display: none; }
    .accounts-toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; color: var(--text-muted); font-size: 12px; }
</style>
@endpush

@section('content')
<div class="container-fluid">
    @unless ($configured)
        <div class="alert alert-warning d-flex align-items-center gap-2 flex-wrap">
            <i class="bi bi-exclamation-triangle"></i>
            <div class="me-auto">Plaid is not configured yet, so you can't connect a bank or sync.</div>
            <a href="{{ route('settings.edit') }}" class="btn btn-sm btn-primary"><i class="bi bi-gear me-1"></i> Open Settings</a>
        </div>
    @endunless

    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-label">Banks connected</div>
            <div class="kpi-value purple">{{ $banks->count() }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Accounts with transactions</div>
            <div class="kpi-value white">
                {{ $activeAccountCount }}
                <span class="c-muted" style="font-size:.8rem;font-weight:500">of {{ $accountCount }} total</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Transactions</div>
            <div class="kpi-value white">{{ $transactionCount }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Last synced</div>
            <div class="kpi-value {{ $lastSynced ? 'green' : 'c-muted' }}" style="font-size:1.1rem" title="{{ $lastSynced?->toDayDateTimeString() }}">
                {{ $lastSynced?->diffForHumans() ?? 'Never' }}
            </div>
        </div>
    </div>

    @if ($accountCount > $activeAccountCount)
        <div class="accounts-toolbar">
            <label class="custom-switch" for="showEmpty">
                <input type="checkbox" id="showEmpty">
                <span class="switch-slider"></span>
            </label>
            <label for="showEmpty" style="cursor:pointer">
                Show accounts with no transactions ({{ $accountCount - $activeAccountCount }})
            </label>
        </div>
    @endif

    <div id="bankList">
    @forelse ($banks as $bank)
        @php($active = $bank->accounts->where('transactions_count', '>', 0)->count())
        <div class="card">
            <div class="card-header">
                <div class="bank-head">
                    <div class="bank-logo"><i class="bi bi-bank2"></i></div>
                    <div class="me-auto">
                        <div class="bank-name">{{ $bank->institution_name ?? 'Unknown bank' }}</div>
                        <div class="bank-meta">
                            Connected {{ $bank->created_at->format('M j, Y') }}
                            · Last synced {{ $bank->last_synced_at?->diffForHumans() ?? 'never' }}
                        </div>
                    </div>
                    <span class="badge bg-primary" title="{{ $bank->accounts->count() }} accounts in total">
                        {{ $active }} active {{ Str::plural('account', $active) }} · {{ $bank->accounts->count() }} total
                    </span>
                    <span class="badge bg-secondary">{{ $bank->transactions_count }} {{ Str::plural('transaction', $bank->transactions_count) }}</span>
                </div>
            </div>
            <div class="card-body">
                @if ($bank->accounts->isEmpty())
                    <div class="text-center text-muted py-3">
                        Account details haven't been loaded yet — click <strong>Sync</strong> to fetch them.
                    </div>
                @else
                    <div class="table-container">
                        <table class="table table-striped table-hover compact mb-0">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th width="14%">Account number</th>
                                    <th width="12%">Type</th>
                                    <th width="14%" class="text-end">Current balance</th>
                                    <th width="14%" class="text-end">Available</th>
                                    <th width="10%" class="text-end">Transactions</th>
                                    <th width="6%"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($active === 0)
                                    <tr class="only-active-note">
                                        <td colspan="7" class="text-center text-muted py-3">
                                            None of this bank's accounts have transactions yet.
                                        </td>
                                    </tr>
                                @endif
                                @foreach ($bank->accounts as $account)
                                    <tr class="{{ $account->transactions_count === 0 ? 'acct-empty' : '' }}">
                                        <td>
                                            <div class="acct-name">{{ $account->name }}</div>
                                            @if ($account->official_name && $account->official_name !== $account->name)
                                                <div class="acct-sub">{{ $account->official_name }}</div>
                                            @endif
                                        </td>
                                        <td class="acct-mask">{{ $account->mask ? '•••• ' . $account->mask : '—' }}</td>
                                        <td>
                                            @if ($account->subtype || $account->type)
                                                <span class="tag-soft">{{ Str::upper($account->subtype ?? $account->type) }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold text-nowrap">
                                            {{ $account->balance_current !== null ? number_format($account->balance_current, 2) . ' ' . $account->iso_currency_code : '—' }}
                                        </td>
                                        <td class="text-end text-nowrap">
                                            {{ $account->balance_available !== null ? number_format($account->balance_available, 2) . ' ' . $account->iso_currency_code : '—' }}
                                        </td>
                                        <td class="text-end">{{ $account->transactions_count }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('plaid.index', ['account' => $account->account_id]) }}"
                                               class="icon-btn" title="View transactions" aria-label="View transactions for {{ $account->name }}">
                                                <i class="bi bi-list-ul"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body text-center py-5">
                <div class="bank-logo mx-auto mb-3"><i class="bi bi-bank2"></i></div>
                <h5 class="mb-1">No banks connected yet</h5>
                <p class="text-muted mb-3">Connect a bank to see its accounts and start syncing transactions.</p>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('connect').click()" @disabled(!$configured)>
                    <i class="bi bi-bank me-1"></i> Connect bank
                </button>
            </div>
        </div>
    @endforelse
    </div>

    <p class="text-muted small mb-0">
        Balances are as of the last sync. Plaid only shares the last digits of an account number.
        Hidden accounts (loans, investments, …) are shared by the bank but have no transactions.
    </p>
</div>
@endsection

@push('scripts')
@include('partials.plaid-client')
<script>
    // This page is rendered on the server, so reload it after a connect or sync
    // (with a short pause so the "Sync complete" toast can be read first).
    PlaidApp.bindButtons(() => { setTimeout(() => window.location.reload(), 1500); });

    // Remember the "show accounts with no transactions" switch in this browser.
    (function () {
        const toggle = document.getElementById('showEmpty');
        if (!toggle) return;
        const list = document.getElementById('bankList');
        const apply = () => list.classList.toggle('show-empty', toggle.checked);

        try { toggle.checked = localStorage.getItem('plaid-show-empty-accounts') === '1'; } catch (e) { /* private mode */ }
        apply();

        toggle.addEventListener('change', () => {
            apply();
            try { localStorage.setItem('plaid-show-empty-accounts', toggle.checked ? '1' : '0'); } catch (e) { /* private mode */ }
        });
    })();
</script>
@endpush
