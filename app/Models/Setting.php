<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    /** Keys whose values are stored encrypted with APP_KEY. */
    private const ENCRYPTED = ['plaid_secret'];

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::where('key', $key)->value('value');

        if ($value === null || $value === '') {
            return $default;
        }

        return in_array($key, self::ENCRYPTED, true) ? Crypt::decryptString($value) : $value;
    }

    public static function put(string $key, ?string $value): void
    {
        if ($value !== null && $value !== '' && in_array($key, self::ENCRYPTED, true)) {
            $value = Crypt::encryptString($value);
        }

        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
