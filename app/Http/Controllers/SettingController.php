<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\PlaidService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SettingController extends Controller
{
    public function edit(PlaidService $plaid): View
    {
        $credentials = $plaid->credentials();

        return view('settings', [
            'clientId' => $credentials['client_id'],
            'hasSecret' => filled($credentials['secret']),
            'env' => $credentials['env'],
            'clientName' => Setting::get('plaid_client_name'),
            'configured' => $plaid->isConfigured(),
        ]);
    }

    public function update(Request $request, PlaidService $plaid): RedirectResponse
    {
        $current = $plaid->credentials();

        $data = $request->validate([
            'plaid_client_id' => ['required', 'string', 'max:100'],
            // The saved secret is never sent back to the browser; blank keeps it.
            'plaid_secret' => [Rule::requiredIf(blank($current['secret'])), 'nullable', 'string', 'max:100'],
            'plaid_env' => ['required', Rule::in(array_keys(PlaidService::ENVIRONMENTS))],
            'plaid_client_name' => ['required', 'string', 'max:30'],
        ], [
            'plaid_secret.required' => 'The secret is required.',
        ]);

        $credentials = [
            'client_id' => trim($data['plaid_client_id']),
            'secret' => filled($data['plaid_secret']) ? trim($data['plaid_secret']) : $current['secret'],
            'env' => $data['plaid_env'],
            'client_name' => $data['plaid_client_name'],
        ];

        // Check the keys against Plaid before saving, so a typo is caught here
        // rather than on the next bank connect.
        try {
            $plaid->post('/link/token/create', [
                'client_name' => $credentials['client_name'],
                'language' => 'en',
                'country_codes' => ['US'],
                'products' => ['transactions'],
                'user' => ['client_user_id' => (string) $request->user()->id],
            ], $credentials);
        } catch (Throwable $e) {
            return back()->withInput($request->except('plaid_secret'))->withErrors([
                'plaid_client_id' => 'Plaid rejected these credentials (' . $e->getMessage() . '). Check the Client ID, Secret and Environment.',
            ]);
        }

        Setting::put('plaid_client_id', $credentials['client_id']);
        Setting::put('plaid_secret', $credentials['secret']);
        Setting::put('plaid_env', $credentials['env']);
        Setting::put('plaid_client_name', $credentials['client_name']);

        return redirect()->route('settings.edit')->with('success', 'Plaid settings saved and verified.');
    }
}
