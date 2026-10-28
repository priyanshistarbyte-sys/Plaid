{{--
    Shared browser client for the Plaid endpoints: the JSON API helper plus the
    Connect bank (Plaid Link) and Sync flows. Used by the Transactions and Bank
    Accounts pages; each page decides what to refresh afterwards.
--}}
<script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>
<script>
    window.PlaidApp = (function () {
        const base = @json(url('/plaid'));
        const csrf = document.querySelector('meta[name="csrf-token"]').content;

        // `path` is relative to /plaid, unless a full URL is given.
        async function api(path, method = 'GET', body = null) {
            const res = await fetch(/^https?:/.test(path) ? path : base + path, {
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

        /** Pull new transactions and account details, then run `after` to refresh the page. */
        async function sync(button, after) {
            const label = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Syncing…';
            try {
                const r = await api('/sync', 'POST');
                if (after) await after();
                toastr.success(`${r.added} added, ${r.modified} modified, ${r.removed} removed.`, 'Sync complete');
            } finally {
                button.disabled = false;
                button.innerHTML = label;
            }
        }

        /** Open Plaid Link; on success save the connection and run a first sync. */
        async function connect(syncButton, after) {
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
                        await sync(syncButton, after);
                    } catch (e) { toastr.error(e.message); }
                },
                onExit: (err) => { if (err) toastr.error(err.display_message || err.error_message, 'Plaid'); },
            }).open();
        }

        /** Wire a page's #connect and #sync buttons to the flows above. */
        function bindButtons(after) {
            const syncButton = document.getElementById('sync');
            document.getElementById('connect').addEventListener('click', () =>
                connect(syncButton, after).catch(e => toastr.error(e.message)));
            syncButton.addEventListener('click', () =>
                sync(syncButton, after).catch(e => toastr.error(e.message)));
        }

        const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const money = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        return { api, sync, connect, bindButtons, esc, money };
    })();
</script>
