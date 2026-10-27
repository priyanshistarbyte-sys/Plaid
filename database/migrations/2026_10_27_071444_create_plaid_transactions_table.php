<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plaid_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plaid_item_id')
                ->constrained('plaid_items')
                ->cascadeOnDelete();

            $table->string('transaction_id')->unique();
            $table->string('account_id')->index();
            $table->string('name');
            $table->decimal('amount', 14, 2);
            $table->string('iso_currency_code', 3)->nullable();
            $table->date('transaction_date')->nullable();
            $table->boolean('pending')->default(false);
            $table->string('merchant_name')->nullable();
            $table->string('category')->nullable();
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plaid_transactions');
    }
};
