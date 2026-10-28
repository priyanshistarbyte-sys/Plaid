@extends('layouts.app')
@section('page-title', 'Bank Transactions')
@section('page-subtitle', 'Connected accounts via Plaid · classify each transaction with a type')

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
    .tx-bank { color: var(--text-strong); white-space: nowrap; }
    .type-detail { display: grid; grid-template-columns: 130px 1fr; gap: 8px 16px; margin: 0 0 18px; }
    .type-detail dt { color: var(--text-muted); font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
    .type-detail dd { margin: 0; word-break: break-all; color: var(--text-strong); }
    .type-options { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .type-label-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
    .type-label-row .form-label { margin: 0; }
    .quick-add { display: none; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
    .quick-add.open { display: flex; }
    .quick-add input { flex: 1 1 200px; }
    .quick-add select { flex: 0 0 130px; }
    /* KPI value colour follows the type's badge colour */
    .kpi-value.t-danger { color: var(--red); }
    .kpi-value.t-success { color: var(--green); }
    .kpi-value.t-info { color: var(--info); }
    .kpi-value.t-primary { color: var(--accent-soft); }
    .kpi-value.t-warning { color: var(--warn-fg); }
    .kpi-value.t-secondary { color: var(--text-muted); }
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

    {{-- Filled by renderKpis(): Transactions, one card per type, Not classified --}}
    <div class="kpi-row" id="kpis"></div>

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2 flex-wrap">
            <h5 class="mb-0 me-auto">Transaction List</h5>
            <select class="form-select form-select-sm w-auto" id="accountFilter" aria-label="Filter by account">
                <option value="">All accounts</option>
            </select>
            <select class="form-select form-select-sm w-auto" id="typeFilter" aria-label="Filter by type">
                <option value="">All types</option>
            </select>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-striped table-hover compact mb-0">
                    <thead>
                        <tr>
                            <th width="9%">Date</th>
                            <th>Name</th>
                            <th width="18%">Bank / Account</th>
                            <th width="14%">Category</th>
                            <th width="8%">Status</th>
                            <th width="11%" class="text-end">Amount</th>
                            <th width="12%">Type</th>
                        </tr>
                    </thead>
                    <tbody id="rows">
                        <tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr>
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

                <div class="type-label-row">
                    <label class="form-label">Type</label>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="quickAddToggle">
                        <i class="bi bi-plus-lg me-1"></i> New type
                    </button>
                </div>
                <div class="type-options" id="typeOptions"></div>

                <div class="quick-add" id="quickAdd">
                    <input type="text" class="form-control" id="quickName" maxlength="40" placeholder="New type name, e.g. Salary">
                    <select class="form-select" id="quickColor" aria-label="Color">
                        @foreach ($colors as $value => $label)
                            <option value="{{ $value }}" @selected($value === 'primary')>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary" id="quickAddSave"><i class="bi bi-check2 me-1"></i> Add</button>
                </div>
                <div class="form-text text-muted mt-2">
                    Manage all types on the <a href="{{ route('types.index') }}">Types</a> page.
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
@include('partials.plaid-client')
<script>
    const { api, esc, money } = PlaidApp;

    // Types from the Types page; the popup's quick add appends to this list.
    let types = @json($types);
    const typeBadge = (name, color) => name ? `<span class="badge bg-${esc(color)}">${esc(name)}</span>` : '';

    function renderTypeControls() {
        const typeFilter = document.getElementById('typeFilter');
        const current = typeFilter.value;
        typeFilter.innerHTML = '<option value="">All types</option>'
            + types.map(t => `<option value="${t.id}">${esc(t.name)}</option>`).join('')
            + '<option value="none">Not classified</option>';
        typeFilter.value = [...typeFilter.options].some(o => o.value === current) ? current : '';

        document.getElementById('typeOptions').innerHTML = types.length
            ? types.map(t => `
                <label class="type-option">
                    <input class="form-check-input" type="radio" name="type" value="${t.id}" required>
                    ${typeBadge(t.name, t.color)}
                </label>`).join('')
            : '<div class="text-muted">No types yet — add one with "New type".</div>';
    }

    // Plaid only ever shares the last few digits of an account number (the "mask").
    const maskedNumber = (t) => t.account_mask ? `•••• ${t.account_mask}` : '';
    const accountLabel = (t) => t.account_name
        ? [t.account_name, maskedNumber(t)].filter(Boolean).join(' · ')
        : 'Account details on next sync';

    let transactions = [];
    let selected = null;

    const accountFilter = document.getElementById('accountFilter');
    // Opened from the Bank Accounts page with ?account=… preselects that account.
    let wantedAccount = new URLSearchParams(window.location.search).get('account') || '';

    // Transactions in the selected account (all of them when none is selected).
    const inAccount = () => transactions.filter(t => !accountFilter.value || t.account_id === accountFilter.value);

    function renderAccountOptions() {
        const accounts = new Map();
        transactions.forEach(t => accounts.set(t.account_id, `${t.bank_name ?? 'Bank'} — ${accountLabel(t)}`));

        const current = wantedAccount || accountFilter.value;
        accountFilter.innerHTML = '<option value="">All accounts</option>' + [...accounts]
            .sort((a, b) => a[1].localeCompare(b[1]))
            .map(([id, label]) => `<option value="${esc(id)}">${esc(label)}</option>`).join('');
        accountFilter.value = accounts.has(current) ? current : '';
        wantedAccount = '';
    }

    function renderKpis() {
        const list = inAccount();
        const sum = (typeId) => list.filter(t => t.transaction_type_id === typeId)
            .reduce((s, t) => s + Math.abs(Number(t.amount)), 0);
        const card = (label, value, cls) =>
            `<div class="kpi-card"><div class="kpi-label">${esc(label)}</div><div class="kpi-value ${cls}">${value}</div></div>`;

        document.getElementById('kpis').innerHTML =
            card('Transactions', list.length, 'white')
            + types.map(t => card(t.name, money(sum(t.id)), 't-' + esc(t.color))).join('')
            + card('Not classified', list.filter(t => !t.transaction_type_id).length, 'gold');
    }

    function renderRows() {
        const filter = document.getElementById('typeFilter').value;
        const rows = transactions
            .map((t, i) => ({ t, i }))
            .filter(({ t }) => !accountFilter.value || t.account_id === accountFilter.value)
            .filter(({ t }) => !filter || (filter === 'none' ? !t.transaction_type_id : t.transaction_type_id === Number(filter)));

        document.getElementById('rows').innerHTML = rows.length ? rows.map(({ t, i }) => `
            <tr>
                <td class="text-nowrap">${esc(t.transaction_date)}</td>
                <td>
                    <div class="tx-name">${esc(t.merchant_name ?? t.name)}</div>
                    ${t.merchant_name && t.merchant_name !== t.name ? `<div class="tx-sub">${esc(t.name)}</div>` : ''}
                </td>
                <td>
                    <div class="tx-bank"><i class="bi bi-bank2 me-1"></i>${esc(t.bank_name) || '—'}</div>
                    <div class="tx-sub">${esc(accountLabel(t))}</div>
                </td>
                <td>${t.category ? `<span class="tag-soft">${esc(t.category.replace(/_/g, ' '))}</span>` : ''}</td>
                <td>${t.pending ? '<span class="badge bg-warning">Pending</span>' : '<span class="badge bg-secondary">Posted</span>'}</td>
                {{-- Plaid amounts: positive = money out, negative = money in --}}
                <td class="text-end fw-semibold ${Number(t.amount) < 0 ? 'c-green' : ''}">${money(t.amount)} ${esc(t.iso_currency_code)}</td>
                <td class="type-cell">
                    ${typeBadge(t.type_name, t.type_color)}
                    <button type="button" class="icon-btn type-btn" data-index="${i}"
                            title="${t.transaction_type_id ? 'Change type' : 'Set type'}" aria-label="Set type">
                        <i class="bi ${t.transaction_type_id ? 'bi-pencil-square' : 'bi-plus-circle'}"></i>
                    </button>
                </td>
            </tr>`).join('')
            : `<tr><td colspan="7" class="text-center text-muted py-4">${transactions.length ? 'No transactions match this filter.' : 'No transactions yet — connect a bank to get started.'}</td></tr>`;
    }

    async function loadTransactions() {
        transactions = await api('/transactions');
        renderAccountOptions();
        renderKpis();
        renderRows();
    }

    PlaidApp.bindButtons(loadTransactions);
    document.getElementById('typeFilter').addEventListener('change', renderRows);
    accountFilter.addEventListener('change', () => { renderKpis(); renderRows(); });

    const modal = new bootstrap.Modal(document.getElementById('typeModal'));
    const form = document.getElementById('typeForm');

    document.getElementById('rows').addEventListener('click', (e) => {
        const btn = e.target.closest('.type-btn');
        if (!btn) return;

        selected = transactions[btn.dataset.index];
        const fields = {
            'Bank': selected.bank_name,
            'Account': selected.account_official_name || selected.account_name,
            'Account number': maskedNumber(selected),
            'Account type': selected.account_subtype,
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
        const current = form.querySelector(`input[name="type"][value="${selected.transaction_type_id}"]`);
        if (current) current.checked = true;
        quickAdd.classList.remove('open');
        modal.show();
    });

    // Quick add: create a type without leaving the popup, then select it.
    const quickAdd = document.getElementById('quickAdd');
    const quickName = document.getElementById('quickName');

    document.getElementById('quickAddToggle').addEventListener('click', () => {
        quickAdd.classList.toggle('open');
        if (quickAdd.classList.contains('open')) quickName.focus();
    });

    async function createType() {
        const name = quickName.value.trim();
        if (!name) { quickName.focus(); return; }

        const btn = document.getElementById('quickAddSave');
        btn.disabled = true;
        try {
            const type = await api(@json(route('types.store')), 'POST', {
                name,
                color: document.getElementById('quickColor').value,
            });
            types = [...types, type].sort((a, b) => a.name.localeCompare(b.name));
            renderTypeControls();
            renderKpis();
            form.querySelector(`input[name="type"][value="${type.id}"]`).checked = true;
            quickName.value = '';
            quickAdd.classList.remove('open');
            toastr.success(`Type "${type.name}" created — click Save to apply it.`);
        } catch (err) {
            toastr.error(err.message);
        } finally {
            btn.disabled = false;
        }
    }

    document.getElementById('quickAddSave').addEventListener('click', createType);
    // Enter in the name box adds the type instead of submitting the popup.
    quickName.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); createType(); }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('saveType');
        btn.disabled = true;
        try {
            await api(`/transactions/${encodeURIComponent(selected.transaction_id)}/type`, 'PATCH', {
                account_id: selected.account_id,
                transaction_type_id: Number(form.elements.type.value),
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

    renderTypeControls();
    loadTransactions().catch(e => toastr.error(e.message));
</script>
@endpush
