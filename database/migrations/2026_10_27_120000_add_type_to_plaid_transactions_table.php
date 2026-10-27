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
        Schema::table('plaid_transactions', function (Blueprint $table) {
            // User-chosen classification: expense, revenue or transaction. Never touched by sync.
            $table->string('type', 20)->nullable()->after('category');
            // Set when a posted transaction replaces a pending one, so the chosen type carries over.
            $table->string('pending_transaction_id')->nullable()->after('transaction_id')->index();
            $table->index(['transaction_id', 'account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plaid_transactions', function (Blueprint $table) {
            $table->dropIndex(['transaction_id', 'account_id']);
            $table->dropColumn(['type', 'pending_transaction_id']);
        });
    }
};
