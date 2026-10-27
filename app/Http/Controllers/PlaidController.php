<?php

namespace App\Http\Controllers;

use App\Models\PlaidItem;
use App\Models\PlaidTransaction;
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
        return view('plaid', ['configured' => $plaid->isConfigured()]);
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
                $cursor = $item->cursor;

                // Page through /transactions/sync until Plaid reports no more updates.
                do {
                    $payload = ['access_token' => $item->access_token, 'count' => 500];
                    if ($cursor) {
                        $payload['cursor'] = $cursor;
                    }

                    $result = $plaid->post('/transactions/sync', $payload);

                    foreach (array_merge($result['added'], $result['modified']) as $tx) {
                        // Matched on transaction_id + account_id; 'type' is never in this
                        // update list, so a re-sync keeps the user's choice.
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
                        if ($saved->type === null && $saved->pending_transaction_id) {
                            $type = PlaidTransaction::where('transaction_id', $saved->pending_transaction_id)
                                ->where('account_id', $saved->account_id)
                                ->value('type');

                            if ($type) {
                                $saved->update(['type' => $type]);
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

                $item->update(['cursor' => $cursor]);
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

    public function transactions(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        $transactions = PlaidTransaction::whereHas(
            'item',
            fn ($q) => $q->where('user_id', $user->id)
        )
            ->orderByDesc('transaction_date')
            ->get();

        return response()->json($transactions);
    }

    public function updateType(Request $request, string $transactionId): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'string'],
            'type' => ['required', 'in:' . implode(',', PlaidTransaction::TYPES)],
        ]);

        $user = $this->currentUser($request);

        $transaction = PlaidTransaction::where('transaction_id', $transactionId)
            ->where('account_id', $data['account_id'])
            ->whereHas('item', fn ($q) => $q->where('user_id', $user->id))
            ->firstOrFail();

        $transaction->update(['type' => $data['type']]);

        return response()->json($transaction);
    }

    private function currentUser(Request $request): User
    {
        return $request->user() ?? abort(401);
    }
}
