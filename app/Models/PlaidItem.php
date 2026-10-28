<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaidItem extends Model
{
    protected $fillable = [
        'user_id',
        'item_id',
        'access_token',
        'cursor',
        'institution_name',
        'last_synced_at',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PlaidTransaction::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(PlaidAccount::class);
    }
}
