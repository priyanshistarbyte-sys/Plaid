<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaidTransaction extends Model
{
    public const TYPES = ['expense', 'revenue', 'transaction'];

    protected $fillable = [
        'plaid_item_id',
        'transaction_id',
        'pending_transaction_id',
        'type',
        'account_id',
        'name',
        'amount',
        'iso_currency_code',
        'transaction_date',
        'pending',
        'merchant_name',
        'category',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date:Y-m-d',
            'pending' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PlaidItem::class, 'plaid_item_id');
    }
}
