<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_invoice_items', function (Blueprint $table) {
            // Drop the foreign key first, then the column
            $table->dropForeign(['variation_id']);
            $table->dropColumn('variation_id');

            // Change quantity from integer to decimal to support fractional units
            $table->decimal('quantity', 15, 2)->change();

            // Add discount column (per-item, kept at 0 for now but required by model fillable)
            $table->decimal('discount', 10, 2)->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('sale_invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('variation_id')->nullable();
            $table->foreign('variation_id')->references('id')->on('product_variations')->onDelete('cascade');

            $table->integer('quantity')->change();
            $table->dropColumn('discount');
        });
    }
};