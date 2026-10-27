@extends('layouts.app')
@section('page-title', 'Bank Transactions')
@section('page-subtitle', 'Connected accounts via Plaid · classify each transaction as Expense, Revenue or Transaction')

@section('page-actions')
    <button type="button" class="btn btn-outline-primary" id="sync" @disabled(!$configured)>
        <i class="bi bi-arrow-repeat me-1"></i> Sync
    </button>
    <button type="button" class="btn btn-primary" id="connect" @disabled(!$configured)>
        <i class="bi bi-bank me-1"></i> Connect bank
    </button>
@endsection

@push('styles')
<style>
    .type-cell { white-space: nowrap; }
    .type-btn { color: var(--accent-soft); font-size: 15px; }
    .tx-name { color: var(--text-strong); font-weight: 600; }
    .tx-sub { color: var(--text-muted); font-size: 11px; }
    .type-detail { display: grid; grid-template-columns: 130px 1fr; gap: 8px 16px; margin: 0 0 18px; }
    .type-detail dt { color: var(--text-muted); font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
    .type-detail dd { margin: 0; word-break: break-all; color: var(--text-strong); }
    .type-options { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .type-option {
        border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;
        display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0; transition: border-color .15s;
    }
    .type-option:hover, .type-option:has(input:checked) { border-color: var(--purple); }
    .type-option .form-check-input { margin: 0; }
    @media (max-width: 576px) { .type-options { grid-template-columns: 1fr; } .type-detail { grid-template-columns: 1fr; gap: 2px; } .type-detail dd { margin-bottom: 8px; } }
</style>
@endpush

@section('content')
<div class="container-fluid">
    @unless ($configured)
        <div class="alert alert-warning d-flex align-items-center gap-2 flex-wrap">
            <i class="bi bi-exclamation-triangle"></i>
            <div class="me-auto">Plaid is not configured yet, so you can't connect a bank or sync transactions.</div>
            <a href="{{ route('settings.edit') }}" class="btn btn-sm btn-primary"><i class="bi bi-gear me-1"></i> Open Settings</a>
        </div>
    @endunless

    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-label">Transactions</div>
            <div class="kpi-value white" id="kpiCount">0</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Expense</div>
            <div class="kpi-value red" id="kpiExpense">0.00</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Revenue</div>
            <div class="kpi-value green" id="kpiRevenue">0.00</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Transaction</div>
            <div class="kpi-value purple" id="kpiTransaction">0.00</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Not classified</div>
            <div class="kpi-value gold" id="kpiUntyped">0</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2 flex-wrap">
            <h5 class="mb-0 me-auto">Transaction List</h5>
            <select class="form-select form-select-sm w-auto" id="typeFilter" aria-label="Filter by type">
                <option value="">All types</option>
                <option value="expense">Expense</option>
                <option value="revenue">Revenue</option>
                <option value="transaction">Transaction</option>
                <option value="none">Not classified</option>
            </select>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-striped table-hover compact mb-0">
                    <thead>
                        <tr>
                            <th width="11%">Date</th>
                            <th>Name</th>
                            <th width="16%">Category</th>
                            <th width="9%">Status</th>
                            <th width="13%" class="text-end">Amount</th>
                            <th width="14%">Type</th>
                        </tr>
                    </thead>
                    <tbody id="rows">
                        <tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="typeModal" tabindex="-1" aria-labelledby="typeModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content" id="typeForm">
            <div class="modal-header">
                <h5 class="modal-title" id="typeModalTitle"><i class="bi bi-receipt me-2"></i>Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="type-detail" id="details"></dl>

                <label class="form-label">Type</label>
                <div class="type-options">
                    <label class="type-option">
                        <input class="form-check-input" type="radio" name="type" value="expense" required>
                        <span class="badge bg-danger">Expense</span>
                    </label>
                    <label class="type-option">
                        <input class="form-check-input" type="radio" name="type" value="revenue">
                        <span class="badge bg-success">Revenue</span>
                    </label>
                    <label class="type-option">
                        <input class="form-check-input" type="radio" name="type" value="transaction">
                        <span class="badge bg-info">Transaction</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveType"><i class="bi bi-check2 me-1"></i> Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>
<script>
    const base = @json(url('/plaid'));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    async function api(path, method = 'GET', body = null) {
        const res = await fetch(base + path, {
            method,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: body ? JSON.stringify(body) : null,
        });
        // Session ended (e.g. logged out in another tab): back to the PIN screen.
        if (res.status === 401 || res.status === 419) {
            window.location.href = @json(route('login'));
            throw new Error('Session expired');
        }
        const json = await res.json();
        if (json.not_configured) {
            // Settings were cleared since this page loaded: point the user there.
            throw new Error(`${json.message} <a href="${@json(route('settings.edit'))}">Open Settings</a>`);
        }
        if (!res.ok) throw new Error(json.error || json.message || res.statusText);
        return json;
    }

    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const typeBadges = {
        expense: '<span class="badge bg-danger">Expense</span>',
        revenue: '<span class="badge bg-success">Revenue</span>',
        transaction: '<span class="badge bg-info">Transaction</span>',
    };

    let transactions = [];
    let selected = null;

    function renderKpis() {
        const sum = (type) => transactions.filter(t => t.type === type).reduce((s, t) => s + Math.abs(Number(t.amount)), 0);
        document.getElementById('kpiCount').textContent = transactions.length;
        document.getElementById('kpiExpense').textContent = money(sum('expense'));
        document.getElementById('kpiRevenue').textContent = money(sum('revenue'));
        document.getElementById('kpiTransaction').textContent = money(sum('transaction'));
        document.getElementById('kpiUntyped').textContent = transactions.filter(t => !t.type).length;
    }

    function renderRows() {
        const filter = document.getElementById('typeFilter').value;
        const rows = transactions
            .map((t, i) => ({ t, i }))
            .filter(({ t }) => !filter || (filter === 'none' ? !t.type : t.type === filter));

        document.getElementById('rows').innerHTML = rows.length ? rows.map(({ t, i }) => `
            <tr>
                <td>${esc(t.transaction_date)}</td>
                <td>
                    <div class="tx-name">${esc(t.merchant_name ?? t.name)}</div>
                    ${t.merchant_name && t.merchant_name !== t.name ? `<div class="tx-sub">${esc(t.name)}</div>` : ''}
                </td>
                <td>${t.category ? `<span class="tag-soft">${esc(t.category.replace(/_/g, ' '))}</span>` : ''}</td>
                <td>${t.pending ? '<span class="badge bg-warning">Pending</span>' : '<span class="badge bg-secondary">Posted</span>'}</td>
                {{-- Plaid amounts: positive = money out, negative = money in --}}
                <td class="text-end fw-semibold ${Number(t.amount) < 0 ? 'c-green' : ''}">${money(t.amount)} ${esc(t.iso_currency_code)}</td>
                <td class="type-cell">
                    ${typeBadges[t.type] ?? ''}
                    <button type="button" class="icon-btn type-btn" data-index="${i}"
                            title="${t.type ? 'Change type' : 'Set type'}" aria-label="Set type">
                        <i class="bi ${t.type ? 'bi-pencil-square' : 'bi-plus-circle'}"></i>
                    </button>
                </td>
            </tr>`).join('')
            : `<tr><td colspan="6" class="text-center text-muted py-4">${transactions.length ? 'No transactions match this filter.' : 'No transactions yet — connect a bank to get started.'}</td></tr>`;
    }

    async function loadTransactions() {
        transactions = await api('/transactions');
        renderKpis();
        renderRows();
    }

    async function sync() {
        const btn = document.getElementById('sync');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Syncing…';
        try {
            const r = await api('/sync', 'POST');
            await loadTransactions();
            toastr.success(`${r.added} added, ${r.modified} modified, ${r.removed} removed.`, 'Sync complete');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Sync';
        }
    }

    document.getElementById('connect').addEventListener('click', async () => {
        try {
            const { link_token } = await api('/link-token', 'POST');

            Plaid.create({
                token: link_token,
                onSuccess: async (public_token, metadata) => {
                    try {
                        await api('/exchange-token', 'POST', {
                            public_token,
                            institution_name: metadata.institution?.name,
                        });
                        toastr.success(metadata.institution?.name ?? 'Bank', 'Connected');
                        await sync();
                    } catch (e) { toastr.error(e.message); }
                },
                onExit: (err) => { if (err) toastr.error(err.display_message || err.error_message, 'Plaid'); },
            }).open();
        } catch (e) { toastr.error(e.message); }
    });

    document.getElementById('sync').addEventListener('click', () => sync().catch(e => toastr.error(e.message)));
    document.getElementById('typeFilter').addEventListener('change', renderRows);

    const modal = new bootstrap.Modal(document.getElementById('typeModal'));
    const form = document.getElementById('typeForm');

    document.getElementById('rows').addEventListener('click', (e) => {
        const btn = e.target.closest('.type-btn');
        if (!btn) return;

        selected = transactions[btn.dataset.index];
        const fields = {
            'Transaction ID': selected.transaction_id,
            'Account ID': selected.account_id,
            'Date': selected.transaction_date,
            'Name': selected.name,
            'Merchant': selected.merchant_name,
            'Category': selected.category,
            'Amount': `${money(selected.amount)} ${selected.iso_currency_code ?? ''}`,
            'Status': selected.pending ? 'Pending' : 'Posted',
        };
        document.getElementById('details').innerHTML = Object.entries(fields)
            .map(([k, v]) => `<dt>${k}</dt><dd>${esc(v) || '—'}</dd>`).join('');

        form.reset();
        if (selected.type) form.elements.type.value = selected.type;
        modal.show();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('saveType');
        btn.disabled = true;
        try {
            await api(`/transactions/${encodeURIComponent(selected.transaction_id)}/type`, 'PATCH', {
                account_id: selected.account_id,
                type: form.elements.type.value,
            });
            modal.hide();
            await loadTransactions();
            toastr.success('Transaction type saved.');
        } catch (err) {
            toastr.error(err.message);
        } finally {
            btn.disabled = false;
        }
    });

    loadTransactions().catch(e => toastr.error(e.message));
</script>
@endpush
