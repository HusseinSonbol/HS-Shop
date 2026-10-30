<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('guest_token', 36)->nullable()->unique();
            $table->string('status', 24)->default('active');
            $table->char('currency', 3)->default('EGP');
            $table->timestamp('expires_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->timestamp('added_at', 6);
            $table->timestamps(6);
            $table->unique(['cart_id', 'variant_id']);
        });

        Schema::create('customer_favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->unique(['customer_id', 'product_id']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->char('public_id', 36)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('channel', 20);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('source_reference', 120)->nullable();
            $table->string('status', 24);
            $table->timestamp('closed_at', 6)->nullable();
            $table->string('close_outcome', 16)->nullable();
            $table->string('closure_reason_code', 80)->nullable();
            $table->char('currency', 3)->default('EGP');
            $table->decimal('subtotal', 19, 4);
            $table->decimal('discount_total', 19, 4);
            $table->decimal('customer_shipping_charge', 19, 4);
            $table->decimal('tax_total', 19, 4)->nullable();
            $table->decimal('grand_total', 19, 4);
            $table->unsignedBigInteger('applied_revision_id')->nullable();
            $table->text('customer_note')->nullable();
            $table->timestamp('placed_at', 6);
            $table->timestamp('cancelled_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['status', 'placed_at']);
            $table->index(['channel', 'placed_at']);
            $table->index(['customer_id', 'placed_at']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->char('line_key', 36)->unique();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index('order_id');
        });

        Schema::create('order_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision_no');
            $table->string('revision_type', 24);
            $table->string('status', 24);
            $table->text('change_summary')->nullable();
            $table->char('currency', 3);
            $table->decimal('subtotal', 19, 4);
            $table->decimal('discount_total', 19, 4);
            $table->decimal('customer_shipping_charge', 19, 4);
            $table->decimal('tax_total', 19, 4)->nullable();
            $table->decimal('grand_total', 19, 4);
            $table->foreignId('proposed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('proposed_at', 6);
            $table->timestamp('expires_at', 6)->nullable();
            $table->timestamp('decided_at', 6)->nullable();
            $table->timestamp('applied_at', 6)->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->unique(['order_id', 'revision_no']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('order_revision_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_revision_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->string('sku_snapshot', 80);
            $table->string('product_name_snapshot', 220);
            $table->json('variant_snapshot')->nullable();
            $table->string('product_image_path_snapshot', 500)->nullable();
            $table->decimal('quantity', 18, 3);
            $table->decimal('regular_unit_price_snapshot', 19, 4);
            $table->decimal('sale_unit_price_snapshot', 19, 4)->nullable();
            $table->decimal('charged_unit_price', 19, 4);
            $table->decimal('discount_amount', 19, 4);
            $table->decimal('tax_amount', 19, 4)->nullable();
            $table->decimal('line_total', 19, 4);
            $table->char('currency', 3);
            $table->json('tax_snapshot')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->unique(['order_revision_id', 'line_no']);
            $table->unique(['order_revision_id', 'order_item_id']);
            $table->index(['product_id', 'variant_id']);
        });

        Schema::create('order_revision_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_revision_id')->constrained()->restrictOnDelete();
            $table->string('address_type', 12);
            $table->string('recipient_name', 160);
            $table->string('phone', 32);
            $table->char('country_code', 2);
            $table->string('governorate', 120);
            $table->string('city', 120);
            $table->string('district', 120)->nullable();
            $table->string('street', 255);
            $table->string('building', 80)->nullable();
            $table->string('floor', 80)->nullable();
            $table->string('apartment', 80)->nullable();
            $table->string('landmark', 255)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->unique(['order_revision_id', 'address_type']);
        });

        Schema::create('customer_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_revision_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('decision', 16);
            $table->timestamp('decided_at', 6);
            $table->string('channel', 24);
            $table->json('evidence')->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['order_revision_id', 'decided_at']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign('applied_revision_id')->references('id')->on('order_revisions')->restrictOnDelete();
        });

        Schema::create('order_item_supplier_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_revision_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('selected_supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('supplier_name_snapshot', 190);
            $table->decimal('selected_unit_cost', 19, 4);
            $table->char('currency', 3);
            $table->string('action', 16);
            $table->foreignId('selected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('selected_at', 6);
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['order_item_id', 'selected_at']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('payment_number', 40)->unique();
            $table->string('method', 32);
            $table->string('status', 24);
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3);
            $table->string('provider', 80)->nullable();
            $table->timestamps(6);
            $table->index(['order_id', 'status']);
        });

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->char('transaction_uuid', 36)->unique();
            $table->string('transaction_type', 24);
            $table->string('status', 24);
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3);
            $table->string('provider_reference', 190)->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('occurred_at', 6);
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['payment_id', 'occurred_at']);
        });

        Schema::create('payment_proofs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained()->restrictOnDelete();
            $table->string('storage_path', 500);
            $table->char('file_sha256', 64)->nullable();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at', 6);
            $table->string('review_status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at', 6)->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['review_status', 'submitted_at']);
        });

        Schema::create('shipping_companies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 160);
            $table->string('integration_driver', 80)->nullable();
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->string('shipment_number', 40)->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('shipping_company_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('leg_type', 24)->default('outbound');
            $table->string('status', 24);
            $table->string('tracking_number', 120)->nullable();
            $table->decimal('customer_shipping_charge', 19, 4);
            $table->decimal('carrier_cost_actual', 19, 4)->nullable();
            $table->decimal('expected_cod_collection', 19, 4);
            $table->decimal('actual_cod_collected', 19, 4)->nullable();
            $table->char('currency', 3);
            $table->timestamp('shipped_at', 6)->nullable();
            $table->timestamp('delivered_at', 6)->nullable();
            $table->json('recipient_snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps(6);
            $table->index(['order_id', 'status']);
            $table->index(['shipping_company_id', 'status']);
        });

        Schema::create('shipment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->unique(['shipment_id', 'order_item_id']);
        });

        Schema::create('shipment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->restrictOnDelete();
            $table->string('status', 24);
            $table->timestamp('event_at', 6);
            $table->string('description', 500)->nullable();
            $table->string('location', 190)->nullable();
            $table->string('source', 24);
            $table->string('provider_event_id', 190)->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['shipment_id', 'event_at']);
            $table->index(['source', 'provider_event_id']);
        });

        Schema::create('returns', function (Blueprint $table): void {
            $table->id();
            $table->string('return_number', 40)->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('case_type', 20);
            $table->string('status', 24);
            $table->string('reason_code', 80);
            $table->text('reason')->nullable();
            $table->timestamp('requested_at', 6);
            $table->timestamp('received_at', 6)->nullable();
            $table->string('resolution', 24)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps(6);
            $table->index(['order_id', 'status']);
        });

        Schema::create('return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->string('condition', 24)->nullable();
            $table->string('disposition', 24)->nullable();
            $table->decimal('refund_amount', 19, 4)->default(0);
            $table->foreignId('replacement_order_item_id')->nullable()->constrained('order_items')->restrictOnDelete();
            $table->timestamps(6);
            $table->index(['order_item_id', 'return_id']);
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->string('refund_number', 40)->unique();
            $table->foreignId('return_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('status', 24);
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3);
            $table->string('reason', 255)->nullable();
            $table->timestamp('refunded_at', 6)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps(6);
            $table->index(['order_id', 'status']);
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('expense_number', 40)->unique();
            $table->string('expense_type', 24);
            $table->string('cost_category', 32)->nullable();
            $table->string('description', 255);
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3);
            $table->date('expense_date');
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 24);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps(6);
            $table->index(['expense_date', 'cost_category']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('order_cost_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('cost_type', 32);
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3);
            $table->string('basis', 16);
            $table->foreignId('expense_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('recorded_at', 6);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('order_cost_entries')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['order_id', 'cost_type', 'recorded_at']);
            $table->index(['order_item_id', 'cost_type']);
        });

        Schema::create('order_activity_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event_type', 32);
            $table->string('previous_status', 24)->nullable();
            $table->string('current_status', 24)->nullable();
            $table->string('close_outcome', 16)->nullable();
            $table->string('reason_code', 80)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('next_follow_up_at', 6)->nullable();
            $table->timestamp('occurred_at', 6);
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['order_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_activity_events');
        Schema::dropIfExists('order_cost_entries');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('shipping_companies');
        Schema::dropIfExists('payment_proofs');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_item_supplier_selections');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['applied_revision_id']);
        });
        Schema::dropIfExists('customer_approvals');
        Schema::dropIfExists('order_revision_addresses');
        Schema::dropIfExists('order_revision_items');
        Schema::dropIfExists('order_revisions');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customer_favorites');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
