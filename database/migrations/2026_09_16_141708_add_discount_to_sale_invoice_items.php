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
            Schema::table('sale_invoice_items', function (Blueprint $table) {
                $table->decimal('discount', 10, 2)->default(0)->after('quantity');
            });
        }

        public function down(): void
        {
            Schema::table('sale_invoice_items', function (Blueprint $table) {
                $table->dropColumn('discount');
            });
        }
};
