<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SetUserPin extends Command
{
    protected $signature = 'user:pin {email} {--name= : Name for a new user}';

    protected $description = 'Create a user, or set the login PIN of an existing one';

    public function handle(): int
    {
        $pin = (string) $this->secret('PIN (4–6 digits)');

        if (!preg_match('/^\d{4,6}$/', $pin)) {
            $this->error('The PIN must be 4 to 6 digits.');
            return self::FAILURE;
        }

        if ($pin !== (string) $this->secret('Confirm PIN')) {
            $this->error('The PINs do not match.');
            return self::FAILURE;
        }

        $email = $this->argument('email');

        // Login is by PIN alone, so two users can never share one.
        $taken = User::whereNotNull('pin')
            ->where('email', '!=', $email)
            ->get()
            ->contains(fn (User $user) => Hash::check($pin, $user->pin));

        if ($taken) {
            $this->error('That PIN is already used by another user. Choose a different one.');
            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);

        if (!$user->exists) {
            $user->name = $this->option('name') ?: Str::before($email, '@');
            $user->password = Str::random(32);
        }

        $user->pin = $pin;
        $user->save();

        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated') . " {$user->email} — you can now log in with this PIN.");

        return self::SUCCESS;
    }
}
