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
        Schema::create('plaid_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plaid_item_id')
                ->constrained('plaid_items')
                ->cascadeOnDelete();

            // Matches plaid_transactions.account_id
            $table->string('account_id')->unique();
            $table->string('name');
            $table->string('official_name')->nullable();
            // Last 2–4 digits of the account number; Plaid never sends the full number.
            $table->string('mask', 10)->nullable();
            $table->string('type', 30)->nullable();
            $table->string('subtype', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plaid_accounts');
    }
};
