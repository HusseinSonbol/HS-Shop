<?php

namespace App\Services\Admin;

use App\Models\Order;
use App\Models\User;
use App\Services\Storefront\ShippingRateService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SocialOrderManagementService
{
    public function __construct(private readonly ShippingRateService $shippingRates) {}

    /** @param array<string, mixed> $data
     * @return array{order_id:int,order_number:string,created:bool}
     */
    public function create(User $actor, array $data): array
    {
        $revisionIdempotencyKey = 'social-order:'.$actor->id.':'.$data['idempotency_key'];
        $proofTransactionKey = 'social-payment-proof:'.$actor->id.':'.$data['idempotency_key'];
        $proofPath = null;
        $proofHash = null;
        if (($data['payment_method'] ?? null) === 'instapay') {
            /** @var UploadedFile $proof */
            $proof = $data['payment_proof'];
            $proofPath = $proof->store('payment-proofs', 'local');
            if (! $proofPath) {
                throw new RuntimeException('Failed to store the private payment proof.');
            }
            $proofHash = hash_file('sha256', (string) $proof->getRealPath()) ?: null;
        }

        try {
            $result = DB::transaction(function () use ($actor, $data, $proofPath, $proofHash, $revisionIdempotencyKey, $proofTransactionKey): array {
                $existing = DB::table('order_revisions as revisions')
                    ->join('orders', 'orders.id', '=', 'revisions.order_id')
                    ->where('revisions.idempotency_key', $revisionIdempotencyKey)
                    ->select('orders.id', 'orders.order_number')
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return ['order_id' => (int) $existing->id, 'order_number' => $existing->order_number, 'created' => false];
                }

                $customer = DB::table('customers')->where('id', $data['customer_id'])->whereNull('deleted_at')->lockForUpdate()->first();
                if (! $customer) {
                    throw ValidationException::withMessages(['customer_id' => __('admin.social_orders.validation.customer_invalid')]);
                }

                $rate = $this->shippingRates->find($data['shipping_rate_code'], lock: true);
                if (! $rate) {
                    throw ValidationException::withMessages(['shipping_rate_code' => __('admin.social_orders.validation.shipping_invalid')]);
                }

                $requestedItems = collect($data['items'])->keyBy(fn (array $item): int => (int) $item['variant_id']);
                $variants = DB::table('product_variants as variants')
                    ->join('products', 'products.id', '=', 'variants.product_id')
                    ->whereIn('variants.id', $requestedItems->keys())
                    ->whereNull('variants.deleted_at')
                    ->whereNull('products.deleted_at')
                    ->orderBy('variants.id')
                    ->lockForUpdate()
                    ->select([
                        'variants.id', 'variants.product_id', 'variants.sku as variant_sku', 'variants.variant_name',
                        'variants.is_active as variant_active', 'variants.current_regular_price', 'variants.current_sale_price',
                        'variants.currency', 'products.sku as product_sku', 'products.status as product_status',
                        'products.store_availability_status',
                    ])
                    ->get()
                    ->keyBy('id');

                if ($variants->count() !== $requestedItems->count()) {
                    throw ValidationException::withMessages(['items' => __('admin.social_orders.validation.variant_invalid')]);
                }

                DB::table('products')->whereIn('id', $variants->pluck('product_id')->unique())->orderBy('id')->lockForUpdate()->get();
                foreach ($variants as $variant) {
                    if (! $variant->variant_active || $variant->product_status !== 'published'
                        || $variant->store_availability_status !== 'available' || $variant->currency !== 'EGP'
                        || $variant->current_regular_price === null || $this->compareMoney((string) $variant->current_regular_price, '0') <= 0) {
                        throw ValidationException::withMessages(['items' => __('admin.social_orders.validation.variant_unavailable')]);
                    }
                }

                $rateAddress = app()->getLocale() === 'ar' ? $rate['governorate_ar'] : $rate['governorate_en'];
                $address = $data['shipping_address'];
                $subtotal = '0.0000';
                $discountTotal = '0.0000';
                $preparedLines = [];

                foreach ($requestedItems as $variantId => $item) {
                    $variant = $variants->get($variantId);
                    $regular = (string) $variant->current_regular_price;
                    $sale = $variant->current_sale_price !== null && $this->compareMoney((string) $variant->current_sale_price, $regular) < 0
                        ? (string) $variant->current_sale_price
                        : null;
                    $charged = $sale ?? $regular;
                    $quantity = (int) $item['quantity'];
                    $lineTotal = $this->multiplyMoney($charged, $quantity);
                    $lineDiscount = $this->multiplyMoney($sale === null ? '0.0000' : $this->subtractMoney($regular, $sale), $quantity);
                    $subtotal = $this->addMoney($subtotal, $lineTotal);
                    $discountTotal = $this->addMoney($discountTotal, $lineDiscount);
                    $preparedLines[] = [
                        'variant' => $variant,
                        'quantity' => $quantity,
                        'regular' => $regular,
                        'sale' => $sale,
                        'charged' => $charged,
                        'discount' => $lineDiscount,
                        'line_total' => $lineTotal,
                    ];
                }

                $shippingCharge = (string) $rate['charge'];
                $grandTotal = $this->addMoney($subtotal, $shippingCharge);
                $now = now();
                $orderNumber = 'HS-'.(string) Str::ulid();
                $orderId = (int) DB::table('orders')->insertGetId([
                    'order_number' => $orderNumber,
                    'public_id' => (string) Str::uuid(),
                    'customer_id' => $customer->id,
                    'channel' => $data['channel'],
                    'created_by_user_id' => $actor->id,
                    'source_reference' => $this->nullableTrim($data['source_reference'] ?? null),
                    'status' => 'new',
                    'currency' => 'EGP',
                    'subtotal' => $subtotal,
                    'discount_total' => $discountTotal,
                    'customer_shipping_charge' => $shippingCharge,
                    'tax_total' => null,
                    'grand_total' => $grandTotal,
                    'applied_revision_id' => null,
                    'customer_note' => $this->nullableTrim($data['customer_note'] ?? null),
                    'placed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $revisionId = (int) DB::table('order_revisions')->insertGetId([
                    'order_id' => $orderId,
                    'revision_no' => 1,
                    'revision_type' => 'initial',
                    'status' => 'applied',
                    'change_summary' => null,
                    'currency' => 'EGP',
                    'subtotal' => $subtotal,
                    'discount_total' => $discountTotal,
                    'customer_shipping_charge' => $shippingCharge,
                    'tax_total' => null,
                    'grand_total' => $grandTotal,
                    'proposed_by' => $actor->id,
                    'proposed_at' => $now,
                    'expires_at' => null,
                    'decided_at' => null,
                    'applied_at' => $now,
                    'idempotency_key' => $revisionIdempotencyKey,
                    'created_at' => $now,
                ]);

                $translations = DB::table('product_translations')
                    ->whereIn('product_id', $variants->pluck('product_id')->unique())
                    ->orderByRaw('CASE WHEN locale = ? THEN 0 WHEN locale = ? THEN 1 ELSE 2 END', [app()->getLocale(), 'en'])
                    ->get()
                    ->groupBy('product_id');
                $images = DB::table('product_images')
                    ->whereIn('product_id', $variants->pluck('product_id')->unique())
                    ->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')->get();
                $attributes = DB::table('variant_attribute_values as vav')
                    ->join('attributes', 'attributes.id', '=', 'vav.attribute_id')
                    ->join('attribute_value_translations as value_translations', function ($join): void {
                        $join->on('value_translations.attribute_value_id', '=', 'vav.attribute_value_id')
                            ->where('value_translations.locale', '=', app()->getLocale());
                    })
                    ->leftJoin('attribute_translations as attribute_translations', function ($join): void {
                        $join->on('attribute_translations.attribute_id', '=', 'attributes.id')
                            ->where('attribute_translations.locale', '=', app()->getLocale());
                    })
                    ->whereIn('vav.variant_id', $variants->keys())
                    ->select('vav.variant_id', 'attributes.code', 'attribute_translations.name as attribute_name', 'value_translations.value')
                    ->get()->groupBy('variant_id');

                foreach ($preparedLines as $lineNo => $line) {
                    $variant = $line['variant'];
                    $productTranslation = $translations->get($variant->product_id)?->first();
                    $variantAttributes = $attributes->get($variant->id, collect())->map(fn ($attribute): array => [
                        'code' => $attribute->code,
                        'name' => $attribute->attribute_name ?: $attribute->code,
                        'value' => $attribute->value,
                    ])->values()->all();
                    $image = $images->first(fn ($image): bool => (int) $image->product_id === (int) $variant->product_id
                        && ((int) $image->variant_id === (int) $variant->id || $image->variant_id === null));
                    $orderItemId = (int) DB::table('order_items')->insertGetId([
                        'order_id' => $orderId,
                        'line_key' => (string) Str::uuid(),
                        'created_at' => $now,
                    ]);
                    DB::table('order_revision_items')->insert([
                        'order_revision_id' => $revisionId,
                        'order_item_id' => $orderItemId,
                        'line_no' => $lineNo + 1,
                        'product_id' => $variant->product_id,
                        'variant_id' => $variant->id,
                        'sku_snapshot' => $variant->product_sku,
                        'product_name_snapshot' => mb_substr((string) ($productTranslation?->name ?? $variant->product_sku), 0, 220),
                        'variant_snapshot' => json_encode([
                            'sku' => $variant->variant_sku,
                            'name' => $variant->variant_name ?: implode(' · ', array_column($variantAttributes, 'value')),
                            'variant_name' => $variant->variant_name,
                            'attributes' => $variantAttributes,
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'product_image_path_snapshot' => $image?->path,
                        'quantity' => (string) $line['quantity'],
                        'regular_unit_price_snapshot' => $line['regular'],
                        'sale_unit_price_snapshot' => $line['sale'],
                        'charged_unit_price' => $line['charged'],
                        'discount_amount' => $line['discount'],
                        'tax_amount' => null,
                        'line_total' => $line['line_total'],
                        'currency' => 'EGP',
                        'tax_snapshot' => null,
                        'created_at' => $now,
                    ]);
                }

                DB::table('order_revision_addresses')->insert([
                    'order_revision_id' => $revisionId,
                    'address_type' => 'shipping',
                    'recipient_name' => trim($address['recipient_name']),
                    'phone' => trim($address['phone']),
                    'country_code' => 'EG',
                    'governorate' => $rateAddress,
                    'city' => trim($address['city']),
                    'district' => $this->nullableTrim($address['district'] ?? null),
                    'street' => trim($address['street']),
                    'building' => $this->nullableTrim($address['building'] ?? null),
                    'floor' => $this->nullableTrim($address['floor'] ?? null),
                    'apartment' => $this->nullableTrim($address['apartment'] ?? null),
                    'landmark' => $this->nullableTrim($address['landmark'] ?? null),
                    'postal_code' => $this->nullableTrim($address['postal_code'] ?? null),
                    'created_at' => $now,
                ]);
                DB::table('orders')->where('id', $orderId)->update(['applied_revision_id' => $revisionId, 'updated_at' => $now]);

                $paymentId = (int) DB::table('payments')->insertGetId([
                    'order_id' => $orderId,
                    'payment_number' => 'PAY-'.(string) Str::ulid(),
                    'method' => $data['payment_method'],
                    'status' => 'pending',
                    'amount' => $grandTotal,
                    'currency' => 'EGP',
                    'provider' => $data['payment_method'] === 'instapay' ? 'instapay' : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($data['payment_method'] === 'instapay') {
                    $transactionId = (int) DB::table('payment_transactions')->insertGetId([
                        'payment_id' => $paymentId,
                        'transaction_uuid' => (string) Str::uuid(),
                        'transaction_type' => 'proof_submitted',
                        'status' => 'pending',
                        'amount' => $grandTotal,
                        'currency' => 'EGP',
                        'provider_reference' => null,
                        'idempotency_key' => $proofTransactionKey,
                        'request_payload' => null,
                        'response_payload' => null,
                        'occurred_at' => $now,
                        'created_at' => $now,
                    ]);
                    DB::table('payment_proofs')->insert([
                        'payment_transaction_id' => $transactionId,
                        'storage_path' => $proofPath,
                        'file_sha256' => $proofHash,
                        'submitted_by' => $actor->id,
                        'submitted_at' => $now,
                        'review_status' => 'pending',
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'rejection_reason' => null,
                        'created_at' => $now,
                    ]);
                }

                $this->appendOrderEvent($orderId, $actor->id, 'created', null, 'new', ['channel' => $data['channel'], 'payment_method' => $data['payment_method']], $now);
                if ($data['payment_method'] === 'instapay') {
                    $this->appendOrderEvent($orderId, $actor->id, 'payment_proof_submitted', 'new', 'new', ['payment_method' => 'instapay'], $now);
                }
                $this->logActivity($actor->id, $orderId, $data['payment_method'] === 'instapay' ? 'order.social_created_with_instapay_proof' : 'order.social_created', null, [
                    'channel' => $data['channel'],
                    'payment_method' => $data['payment_method'],
                    'order_number' => $orderNumber,
                    'proof_id' => $data['payment_method'] === 'instapay' ? DB::table('payment_proofs')->where('payment_transaction_id', $transactionId)->value('id') : null,
                ]);

                return ['order_id' => $orderId, 'order_number' => $orderNumber, 'created' => true];
            }, attempts: 3);

            if (! $result['created'] && $proofPath) {
                Storage::disk('local')->delete($proofPath);
            }

            return $result;
        } catch (QueryException $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }
            if (in_array((string) $exception->getCode(), ['23000', '19'], true)) {
                $existing = DB::table('order_revisions as revisions')
                    ->join('orders', 'orders.id', '=', 'revisions.order_id')
                    ->where('revisions.idempotency_key', $revisionIdempotencyKey)
                    ->select('orders.id', 'orders.order_number')
                    ->first();
                if ($existing) {
                    return ['order_id' => (int) $existing->id, 'order_number' => $existing->order_number, 'created' => false];
                }
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }
            throw $exception;
        }
    }

    /** @param array{decision:string,rejection_reason?:?string} $data */
    public function reviewProof(int $orderId, int $proofId, User $actor, array $data): void
    {
        DB::transaction(function () use ($orderId, $proofId, $actor, $data): void {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            abort_if(! $order, 404);
            if (! in_array($order->channel, ['facebook', 'instagram'], true)) {
                abort(404);
            }

            $proof = DB::table('payment_proofs')
                ->join('payment_transactions', 'payment_transactions.id', '=', 'payment_proofs.payment_transaction_id')
                ->join('payments', 'payments.id', '=', 'payment_transactions.payment_id')
                ->where('payment_proofs.id', $proofId)
                ->where('payments.order_id', $orderId)
                ->where('payments.method', 'instapay')
                ->select('payment_proofs.*', 'payments.id as payment_id', 'payments.status as payment_status', 'payments.amount', 'payments.currency')
                ->lockForUpdate()
                ->first();
            abort_if(! $proof, 404);
            if ($proof->review_status !== 'pending' || $proof->payment_status === 'paid') {
                throw ValidationException::withMessages(['decision' => __('admin.social_orders.validation.proof_already_reviewed')]);
            }

            $now = now();
            $status = $data['decision'];
            $reason = $status === 'rejected' ? trim((string) ($data['rejection_reason'] ?? '')) : null;
            DB::table('payment_proofs')->where('id', $proofId)->update([
                'review_status' => $status,
                'reviewed_by' => $actor->id,
                'reviewed_at' => $now,
                'rejection_reason' => $reason ?: null,
            ]);
            if ($status === 'accepted') {
                DB::table('payments')->where('id', $proof->payment_id)->update(['status' => 'paid', 'updated_at' => $now]);
            }
            DB::table('payment_transactions')->insert([
                'payment_id' => $proof->payment_id,
                'transaction_uuid' => (string) Str::uuid(),
                'transaction_type' => 'verification',
                'status' => $status === 'accepted' ? 'succeeded' : 'failed',
                'amount' => $proof->amount,
                'currency' => $proof->currency,
                'provider_reference' => null,
                'idempotency_key' => 'payment-proof-review:'.$proofId,
                'request_payload' => null,
                'response_payload' => json_encode(['decision' => $status, 'reason' => $reason], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'occurred_at' => $now,
                'created_at' => $now,
            ]);
            $this->appendOrderEvent($orderId, $actor->id, 'payment_proof_reviewed', $order->status, $order->status, [
                'proof_id' => $proofId,
                'decision' => $status,
                'reason' => $reason,
            ], $now);
            $this->logActivity($actor->id, $orderId, 'order.instapay_proof_reviewed', [
                'proof_id' => $proofId,
                'review_status' => 'pending',
                'payment_status' => $proof->payment_status,
            ], [
                'proof_id' => $proofId,
                'review_status' => $status,
                'payment_status' => $status === 'accepted' ? 'paid' : $proof->payment_status,
                'rejection_reason' => $reason,
            ]);
        }, attempts: 3);
    }

    /** @param array{payment_proof:UploadedFile,idempotency_key:string} $data */
    public function uploadReplacementProof(int $orderId, User $actor, array $data): bool
    {
        $idempotencyKey = 'proof-resubmission:'.$actor->id.':'.$data['idempotency_key'];
        /** @var UploadedFile $file */
        $file = $data['payment_proof'];
        $path = $file->store('payment-proofs', 'local');
        if (! $path) {
            throw new RuntimeException('Failed to store the private payment proof.');
        }
        $hash = hash_file('sha256', (string) $file->getRealPath()) ?: null;

        try {
            $created = DB::transaction(function () use ($orderId, $actor, $path, $hash, $idempotencyKey): bool {
                $existing = DB::table('payment_transactions')
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()->first();
                if ($existing) {
                    return false;
                }

                $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
                abort_if(! $order, 404);
                if (! in_array($order->channel, ['facebook', 'instagram'], true)) {
                    abort(404);
                }
                $payment = DB::table('payments')->where('order_id', $orderId)->where('method', 'instapay')->lockForUpdate()->first();
                abort_if(! $payment, 404);
                if ($payment->status === 'paid') {
                    throw ValidationException::withMessages(['payment_proof' => __('admin.social_orders.validation.payment_already_paid')]);
                }

                $latestProof = DB::table('payment_proofs')
                    ->join('payment_transactions', 'payment_transactions.id', '=', 'payment_proofs.payment_transaction_id')
                    ->where('payment_transactions.payment_id', $payment->id)
                    ->orderByDesc('payment_proofs.id')
                    ->lockForUpdate()
                    ->select('payment_proofs.review_status')
                    ->first();
                if ($latestProof && $latestProof->review_status !== 'rejected') {
                    throw ValidationException::withMessages(['payment_proof' => __('admin.social_orders.validation.proof_resubmission_not_allowed')]);
                }

                $now = now();
                $transactionId = (int) DB::table('payment_transactions')->insertGetId([
                    'payment_id' => $payment->id,
                    'transaction_uuid' => (string) Str::uuid(),
                    'transaction_type' => 'proof_submitted',
                    'status' => 'pending',
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'provider_reference' => null,
                    'idempotency_key' => $idempotencyKey,
                    'request_payload' => null,
                    'response_payload' => null,
                    'occurred_at' => $now,
                    'created_at' => $now,
                ]);
                $proofId = (int) DB::table('payment_proofs')->insertGetId([
                    'payment_transaction_id' => $transactionId,
                    'storage_path' => $path,
                    'file_sha256' => $hash,
                    'submitted_by' => $actor->id,
                    'submitted_at' => $now,
                    'review_status' => 'pending',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'rejection_reason' => null,
                    'created_at' => $now,
                ]);
                $this->appendOrderEvent($orderId, $actor->id, 'payment_proof_submitted', $order->status, $order->status, [
                    'payment_method' => 'instapay', 'proof_id' => $proofId,
                ], $now);
                $this->logActivity($actor->id, $orderId, 'order.instapay_proof_submitted', null, ['proof_id' => $proofId]);

                return true;
            }, attempts: 3);

            if (! $created) {
                Storage::disk('local')->delete($path);
            }

            return $created;
        } catch (QueryException $exception) {
            Storage::disk('local')->delete($path);
            if (in_array((string) $exception->getCode(), ['23000', '19'], true)
                && DB::table('payment_transactions')
                    ->join('payments', 'payments.id', '=', 'payment_transactions.payment_id')
                    ->where('payments.order_id', $orderId)
                    ->where('payment_transactions.idempotency_key', $idempotencyKey)
                    ->exists()) {
                return false;
            }
            throw $exception;
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function proofForOrder(int $orderId, int $proofId): object
    {
        $proof = DB::table('payment_proofs')
            ->join('payment_transactions', 'payment_transactions.id', '=', 'payment_proofs.payment_transaction_id')
            ->join('payments', 'payments.id', '=', 'payment_transactions.payment_id')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('payment_proofs.id', $proofId)
            ->where('orders.id', $orderId)
            ->whereIn('orders.channel', ['facebook', 'instagram'])
            ->where('payments.method', 'instapay')
            ->select('payment_proofs.id', 'payment_proofs.storage_path')
            ->first();
        abort_if(! $proof, 404);

        return $proof;
    }

    /** @param array<string, mixed> $details */
    private function appendOrderEvent(int $orderId, int $actorId, string $type, ?string $previous, ?string $current, array $details, mixed $now): void
    {
        DB::table('order_activity_events')->insert([
            'order_id' => $orderId,
            'actor_user_id' => $actorId,
            'event_type' => $type,
            'previous_status' => $previous,
            'current_status' => $current,
            'close_outcome' => null,
            'reason_code' => null,
            'details' => json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'next_follow_up_at' => null,
            'occurred_at' => $now,
            'idempotency_key' => null,
            'created_at' => $now,
        ]);
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function logActivity(int $actorId, int $orderId, string $event, ?array $before, ?array $after): void
    {
        DB::table('activity_logs')->insert([
            'actor_user_id' => $actorId,
            'event' => $event,
            'subject_type' => Order::class,
            'subject_id' => $orderId,
            'before' => $before ? json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'after' => $after ? json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'correlation_id' => request()->attributes->get('correlation_id'),
            'created_at' => now(),
        ]);
    }

    private function multiplyMoney(string $money, int $quantity): string
    {
        return (string) DB::selectOne('SELECT CAST(CAST(? AS DECIMAL(19,4)) * ? AS DECIMAL(19,4)) AS amount', [$money, $quantity])->amount;
    }

    private function addMoney(string $left, string $right): string
    {
        return (string) DB::selectOne('SELECT CAST(? AS DECIMAL(19,4)) + CAST(? AS DECIMAL(19,4)) AS amount', [$left, $right])->amount;
    }

    private function subtractMoney(string $left, string $right): string
    {
        return (string) DB::selectOne('SELECT CAST(? AS DECIMAL(19,4)) - CAST(? AS DECIMAL(19,4)) AS amount', [$left, $right])->amount;
    }

    private function compareMoney(string $left, string $right): int
    {
        return (int) DB::selectOne('SELECT CASE WHEN CAST(? AS DECIMAL(19,4)) < CAST(? AS DECIMAL(19,4)) THEN -1 WHEN CAST(? AS DECIMAL(19,4)) > CAST(? AS DECIMAL(19,4)) THEN 1 ELSE 0 END AS comparison', [$left, $right, $left, $right])->comparison;
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
