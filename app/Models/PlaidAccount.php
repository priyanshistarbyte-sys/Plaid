<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaidAccount extends Model
{
    protected $fillable = [
        'plaid_item_id',
        'account_id',
        'name',
        'official_name',
        'mask',
        'type',
        'subtype',
        'balance_current',
        'balance_available',
        'iso_currency_code',
    ];

    protected function casts(): array
    {
        return [
            'balance_current' => 'decimal:2',
            'balance_available' => 'decimal:2',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PlaidTransaction::class, 'account_id', 'account_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PlaidItem::class, 'plaid_item_id');
    }
}
