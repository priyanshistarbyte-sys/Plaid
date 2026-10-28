<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            // One of TransactionType::COLORS (a badge colour from the theme).
            $table->string('color', 20)->default('secondary');
            $table->timestamps();
        });

        // The three types the app started with.
        $defaults = ['expense' => ['Expense', 'danger'], 'revenue' => ['Revenue', 'success'], 'transaction' => ['Transaction', 'info']];
        $ids = [];
        foreach ($defaults as $slug => [$name, $color]) {
            $ids[$slug] = DB::table('transaction_types')->insertGetId([
                'name' => $name, 'color' => $color, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::table('plaid_transactions', function (Blueprint $table) {
            // Deleting a type leaves its transactions unclassified rather than deleting them.
            $table->foreignId('transaction_type_id')->nullable()->after('category')
                ->constrained('transaction_types')->nullOnDelete();
        });

        // Carry over what users already chose with the old fixed 'type' column.
        foreach ($ids as $slug => $id) {
            DB::table('plaid_transactions')->where('type', $slug)->update(['transaction_type_id' => $id]);
        }

        Schema::table('plaid_transactions', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plaid_transactions', function (Blueprint $table) {
            $table->string('type', 20)->nullable()->after('category');
        });

        foreach (DB::table('transaction_types')->get() as $type) {
            $slug = strtolower($type->name);
            if (in_array($slug, ['expense', 'revenue', 'transaction'], true)) {
                DB::table('plaid_transactions')->where('transaction_type_id', $type->id)->update(['type' => $slug]);
            }
        }

        Schema::table('plaid_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_type_id');
        });

        Schema::dropIfExists('transaction_types');
    }
};
