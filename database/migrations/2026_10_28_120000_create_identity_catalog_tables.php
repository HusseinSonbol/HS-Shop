<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 32)->nullable()->after('password');
            $table->string('preferred_locale', 10)->default('ar')->after('phone');
            $table->boolean('is_active')->default(true)->after('preferred_locale');
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->boolean('is_system')->default(false);
            $table->timestamps(6);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps(6);
        });

        Schema::create('user_role', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->primary(['user_id', 'role_id']);
            $table->index('role_id');
        });

        Schema::create('role_permission', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('customer_number', 40)->unique();
            $table->string('display_name', 160);
            $table->string('phone', 32);
            $table->string('email', 190)->nullable();
            $table->timestamp('marketing_consent_at', 6)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps(6);
            $table->softDeletes();
            $table->index(['phone', 'deleted_at']);
        });

        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('label', 80)->nullable();
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
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps(6);
            $table->softDeletes();
            $table->index(['customer_id', 'is_default']);
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('slug', 190)->unique();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);
            $table->softDeletes();
            $table->index(['parent_id', 'is_active', 'sort_order']);
        });

        Schema::create('category_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name', 190);
            $table->text('description')->nullable();
            $table->timestamps(6);
            $table->unique(['category_id', 'locale']);
            $table->index(['locale', 'name']);
        });

        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 190)->unique();
            $table->string('logo_path', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);
            $table->softDeletes();
        });

        Schema::create('brand_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name', 190);
            $table->text('description')->nullable();
            $table->timestamps(6);
            $table->unique(['brand_id', 'locale']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('product_type', 20)->default('variable');
            $table->string('sku', 80)->unique();
            $table->string('slug', 190)->unique();
            $table->string('status', 24)->default('draft');
            $table->string('store_availability_status', 20)->default('unavailable');
            $table->timestamp('availability_checked_at', 6)->nullable();
            $table->foreignId('availability_checked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('requires_shipping')->default(true);
            $table->string('tax_class', 80)->nullable();
            $table->unsignedInteger('weight_g')->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->timestamps(6);
            $table->softDeletes();
            $table->index(['status', 'store_availability_status', 'published_at']);
        });

        Schema::create('product_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('locale', 10);
            $table->string('name', 220);
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            $table->string('meta_title', 220)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->timestamps(6);
            $table->unique(['product_id', 'locale']);
            $table->index(['locale', 'name']);
        });

        Schema::create('product_categories', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->primary(['product_id', 'category_id']);
            $table->index(['category_id', 'product_id']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('sku', 80)->unique();
            $table->string('barcode', 80)->nullable()->unique();
            $table->string('variant_name', 220)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('current_regular_price', 19, 4);
            $table->decimal('current_sale_price', 19, 4)->nullable();
            $table->char('currency', 3)->default('EGP');
            $table->unsignedBigInteger('current_price_version_id')->nullable();
            $table->unsignedInteger('weight_g')->nullable();
            $table->timestamps(6);
            $table->softDeletes();
            $table->index(['product_id', 'is_active']);
        });

        Schema::create('product_price_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_no');
            foreach (['factory_unit_cost', 'inbound_delivery_unit_cost', 'packaging_unit_cost', 'advertising_unit_cost', 'pricing_cost_total'] as $column) {
                $table->decimal($column, 19, 4);
            }
            $table->decimal('profit_rate_percent', 9, 4);
            $table->decimal('calculated_regular_price', 19, 4)->nullable();
            $table->decimal('official_regular_price', 19, 4);
            $table->decimal('sale_price', 19, 4)->nullable();
            $table->char('currency', 3)->default('EGP');
            $table->timestamp('recorded_at', 6);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('change_reason', 255)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->unique(['product_variant_id', 'version_no']);
            $table->index(['product_variant_id', 'recorded_at']);
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->foreign('current_price_version_id', 'variants_current_price_version_fk')->references('id')->on('product_price_history')->restrictOnDelete();
        });

        Schema::create('product_collections', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps(6);
        });

        Schema::create('product_collection_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_collection_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('title', 160);
            $table->timestamps(6);
            $table->unique(['product_collection_id', 'locale'], 'prod_collection_translation_locale_uq');
        });

        Schema::create('product_collection_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_collection_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->foreignId('added_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('added_at', 6);
            $table->unique(['product_collection_id', 'product_id']);
            $table->unique(['product_collection_id', 'position']);
        });

        Schema::create('product_availability_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('availability_status', 20);
            $table->timestamp('checked_at', 6);
            $table->foreignId('checked_by')->constrained('users')->restrictOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['product_id', 'checked_at']);
        });

        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('input_type', 24);
            $table->boolean('is_variant_axis')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps(6);
        });

        Schema::create('attribute_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name', 120);
            $table->timestamps(6);
            $table->unique(['attribute_id', 'locale']);
        });

        Schema::create('attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->restrictOnDelete();
            $table->string('code', 100);
            $table->integer('sort_order')->default(0);
            $table->unique(['attribute_id', 'code']);
        });

        Schema::create('attribute_value_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('value', 160);
            $table->timestamps(6);
            $table->unique(['attribute_value_id', 'locale']);
        });

        Schema::create('variant_attribute_values', function (Blueprint $table): void {
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('attribute_id')->constrained()->restrictOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->restrictOnDelete();
            $table->primary(['variant_id', 'attribute_id']);
            $table->unique(['variant_id', 'attribute_value_id']);
        });

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->string('path', 500);
            $table->string('alt_text', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps(6);
            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('supplier_type', 20);
            $table->string('name', 190);
            $table->string('contact_name', 160)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 32)->nullable();
            $table->json('address_json')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);
            $table->softDeletes();
        });

        Schema::create('product_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('supplier_product_code', 100)->nullable();
            $table->decimal('unit_cost', 19, 4);
            $table->char('currency', 3)->default('EGP');
            $table->string('supplier_availability_status', 20)->default('unknown');
            $table->timestamp('valid_from', 6)->nullable();
            $table->timestamp('valid_until', 6)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps(6);
            $table->unique(['product_id', 'supplier_id']);
            $table->index(['supplier_id', 'is_active', 'supplier_availability_status'], 'product_supplier_availability_idx');
        });

        Schema::create('product_supplier_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_supplier_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_no');
            $table->decimal('unit_cost', 19, 4);
            $table->char('currency', 3);
            $table->string('supplier_availability_status', 20);
            $table->timestamp('valid_from', 6)->nullable();
            $table->timestamp('valid_until', 6)->nullable();
            $table->boolean('is_active');
            $table->timestamp('changed_at', 6);
            $table->foreignId('changed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('change_reason', 255)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->unique(['product_supplier_id', 'version_no']);
            $table->index(['product_supplier_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_supplier_history');
        Schema::dropIfExists('product_suppliers');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('variant_attribute_values');
        Schema::dropIfExists('attribute_value_translations');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attribute_translations');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('product_availability_history');
        Schema::dropIfExists('product_collection_items');
        Schema::dropIfExists('product_collection_translations');
        Schema::dropIfExists('product_collections');
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropForeign('variants_current_price_version_fk');
        });
        Schema::dropIfExists('product_price_history');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('product_translations');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brand_translations');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('category_translations');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn(['is_active', 'preferred_locale', 'phone']);
        });
    }
};
