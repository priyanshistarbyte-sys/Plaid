<?php

namespace App\Http\Middleware;

use App\Services\PlaidService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks every call that talks to Plaid (connect, exchange, sync) until the
 * Plaid credentials have been saved on the Settings page.
 */
class EnsurePlaidConfigured
{
    public function __construct(private PlaidService $plaid)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->plaid->isConfigured()) {
            return response()->json([
                'message' => 'Plaid is not configured. Add your Plaid credentials in Settings before connecting a bank or syncing.',
                'not_configured' => true,
            ], 422);
        }

        return $next($request);
    }
}
