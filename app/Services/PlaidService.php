<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PlaidService
{
    public const ENVIRONMENTS = [
        'sandbox' => 'https://sandbox.plaid.com',
        'production' => 'https://production.plaid.com',
    ];

    /**
     * Plaid credentials, as saved on the Settings page.
     */
    public function credentials(): array
    {
        return [
            'client_id' => Setting::get('plaid_client_id'),
            'secret' => Setting::get('plaid_secret'),
            'env' => Setting::get('plaid_env', 'sandbox'),
            'client_name' => Setting::get('plaid_client_name', config('app.name')),
        ];
    }

    public function isConfigured(): bool
    {
        $credentials = $this->credentials();

        return filled($credentials['client_id'])
            && filled($credentials['secret'])
            && isset(self::ENVIRONMENTS[$credentials['env']]);
    }

    public function post(string $endpoint, array $payload, ?array $credentials = null): array
    {
        $credentials ??= $this->credentials();

        if (!filled($credentials['client_id']) || !filled($credentials['secret'])) {
            throw new RuntimeException('Plaid is not configured. Add your Plaid credentials in Settings.');
        }

        $baseUrl = self::ENVIRONMENTS[$credentials['env']]
            ?? throw new RuntimeException('Invalid Plaid environment');

        $response = Http::acceptJson()
            ->withHeaders([
                'PLAID-CLIENT-ID' => $credentials['client_id'],
                'PLAID-SECRET' => $credentials['secret'],
            ])
            ->connectTimeout(10)
            ->timeout(45)
            ->post($baseUrl . $endpoint, $payload);

        if (!$response->successful()) {
            $error = $response->json('error_code')
                ?? 'PLAID_API_ERROR';

            throw new RuntimeException(
                "Plaid request failed: {$error}"
            );
        }

        return $response->json();
    }
}
