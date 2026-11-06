<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->foreignId('return_id')->nullable()->after('order_id')->constrained('returns')->restrictOnDelete();
            $table->index(['return_id', 'leg_type', 'status']);
        });

        Schema::table('return_items', function (Blueprint $table): void {
            $table->foreignId('replacement_variant_id')->nullable()->after('replacement_order_item_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('replacement_sku_snapshot', 80)->nullable()->after('replacement_variant_id');
            $table->string('replacement_product_name_snapshot', 220)->nullable()->after('replacement_sku_snapshot');
            $table->json('replacement_variant_snapshot')->nullable()->after('replacement_product_name_snapshot');
            $table->decimal('replacement_quantity', 18, 3)->nullable()->after('replacement_variant_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('return_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('replacement_variant_id');
            $table->dropColumn([
                'replacement_sku_snapshot',
                'replacement_product_name_snapshot',
                'replacement_variant_snapshot',
                'replacement_quantity',
            ]);
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropIndex(['return_id', 'leg_type', 'status']);
            $table->dropConstrainedForeignId('return_id');
        });
    }
};
