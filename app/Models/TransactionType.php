<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionType extends Model
{
    /** Badge colours from the theme (Bootstrap bg-* classes), with a label for the picker. */
    public const COLORS = [
        'danger' => 'Red',
        'success' => 'Green',
        'info' => 'Blue',
        'primary' => 'Purple',
        'warning' => 'Orange',
        'secondary' => 'Gray',
    ];

    protected $fillable = ['name', 'color'];

    public function transactions(): HasMany
    {
        return $this->hasMany(PlaidTransaction::class);
    }
}
