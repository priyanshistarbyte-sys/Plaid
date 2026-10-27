<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PinLoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function show(): View
    {
        return view('auth.pin-login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'pin' => ['required', 'digits_between:4,6'],
        ]);

        // A PIN has few combinations, so throttle hard per IP.
        $key = 'pin-login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'pin' => 'Too many attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.',
            ]);
        }

        $user = User::whereNotNull('pin')
            ->get()
            ->first(fn (User $user) => Hash::check($request->input('pin'), $user->pin));

        if (!$user) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['pin' => 'Incorrect PIN.']);
        }

        RateLimiter::clear($key);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(url('/plaid'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
