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
        Schema::table('plaid_accounts', function (Blueprint $table) {
            // Balances as of the last sync (Plaid's cached values, not real-time).
            $table->decimal('balance_current', 14, 2)->nullable()->after('subtype');
            $table->decimal('balance_available', 14, 2)->nullable()->after('balance_current');
            $table->string('iso_currency_code', 3)->nullable()->after('balance_available');
        });

        Schema::table('plaid_items', function (Blueprint $table) {
            $table->timestamp('last_synced_at')->nullable()->after('institution_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plaid_accounts', function (Blueprint $table) {
            $table->dropColumn(['balance_current', 'balance_available', 'iso_currency_code']);
        });

        Schema::table('plaid_items', function (Blueprint $table) {
            $table->dropColumn('last_synced_at');
        });
    }
};
