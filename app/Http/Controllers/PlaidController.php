<?php

namespace App\Http\Controllers;

use App\Models\PlaidAccount;
use App\Models\PlaidItem;
use App\Models\PlaidTransaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\PlaidService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Throwable;

class PlaidController extends Controller
{
    public function index(PlaidService $plaid): View
    {
        return view('plaid', [
            'configured' => $plaid->isConfigured(),
            'types' => TransactionType::orderBy('name')->get(['id', 'name', 'color']),
            'colors' => TransactionType::COLORS,
        ]);
    }

    public function createLinkToken(
        Request $request,
        PlaidService $plaid
    ): JsonResponse {
        $user = $this->currentUser($request);

        try {
            $result = $plaid->post('/link/token/create', [
                'client_name' => $plaid->credentials()['client_name'],
                'language' => 'en',
                'country_codes' => ['US'],
                'products' => ['transactions'],
                'user' => [
                    'client_user_id' => (string) $user->getAuthIdentifier(),
                ],
                'transactions' => [
                    'days_requested' => 90,
                ],
            ]);

            return response()->json([
                'link_token' => $result['link_token'],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Unable to create Plaid Link token.',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function exchangePublicToken(
        Request $request,
        PlaidService $plaid
    ): JsonResponse {
        $data = $request->validate([
            'public_token' => ['required', 'string'],
            'institution_name' => ['nullable', 'string'],
        ]);

        $user = $this->currentUser($request);

        try {
            $result = $plaid->post('/item/public_token/exchange', [
                'public_token' => $data['public_token'],
            ]);

            $item = PlaidItem::updateOrCreate(
                ['item_id' => $result['item_id']],
                [
                    'user_id' => $user->id,
                    'access_token' => $result['access_token'],
                    'institution_name' => $data['institution_name'] ?? null,
                ]
            );

            return response()->json(['item_id' => $item->item_id]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Unable to exchange public token.',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function syncTransactions(
        Request $request,
        PlaidService $plaid
    ): JsonResponse {
        $user = $this->currentUser($request);
        $added = $modified = $removed = 0;

        try {
            foreach (PlaidItem::where('user_id', $user->id)->get() as $item) {
                $this->syncAccounts($plaid, $item);

                $cursor = $item->cursor;

                // Page through /transactions/sync until Plaid reports no more updates.
                do {
                    $payload = ['access_token' => $item->access_token, 'count' => 500];
                    if ($cursor) {
                        $payload['cursor'] = $cursor;
                    }

                    $result = $plaid->post('/transactions/sync', $payload);

                    foreach (array_merge($result['added'], $result['modified']) as $tx) {
                        // Matched on transaction_id + account_id; transaction_type_id is never
                        // in this update list, so a re-sync keeps the user's choice.
                        $saved = PlaidTransaction::updateOrCreate(
                            [
                                'transaction_id' => $tx['transaction_id'],
                                'account_id' => $tx['account_id'],
                            ],
                            [
                                'plaid_item_id' => $item->id,
                                'pending_transaction_id' => $tx['pending_transaction_id'] ?? null,
                                'name' => $tx['name'],
                                'amount' => $tx['amount'],
                                'iso_currency_code' => $tx['iso_currency_code'],
                                'transaction_date' => $tx['date'],
                                'pending' => $tx['pending'],
                                'merchant_name' => $tx['merchant_name'] ?? null,
                                'category' => $tx['personal_finance_category']['primary'] ?? null,
                            ]
                        );

                        // When a pending transaction posts, Plaid gives it a new transaction_id.
                        // Carry over the type the user chose on the pending one.
                        if ($saved->transaction_type_id === null && $saved->pending_transaction_id) {
                            $typeId = PlaidTransaction::where('transaction_id', $saved->pending_transaction_id)
                                ->where('account_id', $saved->account_id)
                                ->value('transaction_type_id');

                            if ($typeId) {
                                $saved->update(['transaction_type_id' => $typeId]);
                            }
                        }
                    }

                    PlaidTransaction::whereIn(
                        'transaction_id',
                        array_column($result['removed'], 'transaction_id')
                    )->delete();

                    $added += count($result['added']);
                    $modified += count($result['modified']);
                    $removed += count($result['removed']);
                    $cursor = $result['next_cursor'];
                } while ($result['has_more']);

                $item->update(['cursor' => $cursor, 'last_synced_at' => now()]);
            }

            return response()->json(compact('added', 'modified', 'removed'));
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Unable to sync transactions.',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Store the bank name and the item's accounts (name, last digits, type),
     * so each transaction can show which bank account it belongs to.
     */
    private function syncAccounts(PlaidService $plaid, PlaidItem $item): void
    {
        if (blank($item->institution_name)) {
            $institutionId = $plaid->post('/item/get', [
                'access_token' => $item->access_token,
            ])['item']['institution_id'] ?? null;

            if ($institutionId) {
                $institution = $plaid->post('/institutions/get_by_id', [
                    'institution_id' => $institutionId,
                    'country_codes' => ['US'],
                ])['institution'];

                $item->update(['institution_name' => $institution['name']]);
            }
        }

        $accounts = $plaid->post('/accounts/get', [
            'access_token' => $item->access_token,
        ])['accounts'];

        foreach ($accounts as $account) {
            PlaidAccount::updateOrCreate(
                ['account_id' => $account['account_id']],
                [
                    'plaid_item_id' => $item->id,
                    'name' => $account['name'],
                    'official_name' => $account['official_name'] ?? null,
                    'mask' => $account['mask'] ?? null,
                    'type' => $account['type'] ?? null,
                    'subtype' => $account['subtype'] ?? null,
                    'balance_current' => $account['balances']['current'] ?? null,
                    'balance_available' => $account['balances']['available'] ?? null,
                    'iso_currency_code' => $account['balances']['iso_currency_code'] ?? null,
                ]
            );
        }
    }

    public function transactions(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        $transactions = PlaidTransaction::with(['item', 'account', 'transactionType'])
            ->whereHas('item', fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('transaction_date')
            ->get()
            ->map(fn (PlaidTransaction $t) => $t->withoutRelations()->toArray() + [
                'type_name' => $t->transactionType?->name,
                'type_color' => $t->transactionType?->color,
                'bank_name' => $t->item?->institution_name,
                'account_name' => $t->account?->name,
                'account_official_name' => $t->account?->official_name,
                'account_mask' => $t->account?->mask,
                'account_subtype' => $t->account?->subtype,
            ]);

        return response()->json($transactions);
    }

    public function updateType(Request $request, string $transactionId): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'string'],
            'transaction_type_id' => ['required', 'integer', 'exists:transaction_types,id'],
        ], [
            'transaction_type_id.exists' => 'That type no longer exists. Reload the page and pick another.',
        ]);

        $user = $this->currentUser($request);

        $transaction = PlaidTransaction::where('transaction_id', $transactionId)
            ->where('account_id', $data['account_id'])
            ->whereHas('item', fn ($q) => $q->where('user_id', $user->id))
            ->firstOrFail();

        $transaction->update(['transaction_type_id' => $data['transaction_type_id']]);

        return response()->json($transaction);
    }

    private function currentUser(Request $request): User
    {
        return $request->user() ?? abort(401);
    }
}
