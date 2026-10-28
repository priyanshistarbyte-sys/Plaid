<?php

namespace App\Http\Controllers;

use App\Models\PlaidItem;
use App\Services\PlaidService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BankAccountController extends Controller
{
    public function index(Request $request, PlaidService $plaid): View
    {
        $banks = PlaidItem::where('user_id', $request->user()->id)
            // Accounts with transactions first: those are the ones the Transactions page shows.
            ->with(['accounts' => fn ($q) => $q->withCount('transactions')
                ->orderByDesc('transactions_count')
                ->orderBy('name')])
            ->withCount('transactions')
            ->orderBy('institution_name')
            ->get();

        $accounts = $banks->flatMap->accounts;

        return view('banks', [
            'banks' => $banks,
            'accountCount' => $accounts->count(),
            'activeAccountCount' => $accounts->where('transactions_count', '>', 0)->count(),
            'transactionCount' => $banks->sum('transactions_count'),
            'lastSynced' => $banks->max('last_synced_at'),
            'configured' => $plaid->isConfigured(),
        ]);
    }
}
