@extends('admin.layouts.app')

@section('title', $record->order_number.' · '.__('admin.orders.title'))

@section('content')
    <section class="page-heading staff-page-heading">
        <div>
            <p class="eyebrow" dir="ltr">{{ $record->order_number }}</p>
            <h1>{{ __('admin.orders.details') }}</h1>
            <p class="page-description">{{ __('admin.orders.placed_at') }}: <span dir="ltr">{{ \Illuminate\Support\Carbon::parse($record->placed_at)->timezone('Africa/Cairo')->format('Y-m-d H:i') }}</span>@if ($paymentMethod) · {{ __('admin.social_orders.payment_method') }}: {{ __('admin.social_orders.methods.'.$paymentMethod) }}@endif</p>
        </div>
        <a class="btn btn-outline-secondary secondary-button" href="{{ route('admin.orders.index') }}">{{ __('admin.orders.back') }}</a>
    </section>

    <div class="alert alert-info" role="status">{{ __('admin.orders.lifecycle_note') }}</div>

    <section class="row g-3 mb-4">
        <div class="col-md-3"><article class="card panel h-100"><small class="text-secondary">{{ __('admin.orders.order_status') }}</small><strong>{{ __('admin.order_status.'.$record->status) !== 'admin.order_status.'.$record->status ? __('admin.order_status.'.$record->status) : $record->status }}</strong></article></div>
        <div class="col-md-3"><article class="card panel h-100"><small class="text-secondary">{{ __('admin.orders.created_by') }}</small><strong>{{ $record->creator_name ?: '—' }}</strong></article></div>
        <div class="col-md-3"><article class="card panel h-100"><small class="text-secondary">{{ __('admin.orders.followed_by') }}</small><strong>{{ $assignedTo['name'] ?? __('admin.orders.unassigned') }}</strong></article></div>
        <div class="col-md-3"><article class="card panel h-100"><small class="text-secondary">{{ __('admin.orders.customer') }}</small><strong>{{ $record->customer_name }}</strong><span dir="ltr">{{ $record->customer_phone }}</span></article></div>
    </section>

    <section class="card panel customer-section-panel mb-4">
        <div class="panel-heading"><div><p class="eyebrow">{{ __('admin.orders.order_lifecycle') }}</p><h2>{{ __('admin.orders.lifecycle_actions') }}</h2></div></div>
        @if ($nextStatuses)
            <form class="row g-3 align-items-end" method="POST" action="{{ route('admin.orders.events.store', $record->id) }}">
                @csrf<input type="hidden" name="event_type" value="status_changed">
                <div class="col-md-6">
                    <label class="form-label" for="next-order-status">{{ __('admin.orders.move_to') }}</label>
                    <select class="form-select" id="next-order-status" name="new_status" required>@foreach ($nextStatuses as $status)<option value="{{ $status }}">{{ __('admin.order_status.'.$status) }}</option>@endforeach</select>
                </div>
                @if (in_array('received', $nextStatuses, true))
                    <div class="col-md-6"><div class="form-check"><input class="form-check-input" id="customer-received" type="checkbox" name="customer_received" value="1" required><label class="form-check-label" for="customer-received">{{ __('admin.orders.customer_receipt_confirm') }}</label></div></div>
                @endif
                <div class="col-12"><button class="btn btn-primary primary-button" type="submit">{{ __('admin.orders.change_status') }}</button></div>
            </form>
        @else
            <p class="mb-0">{{ $record->close_outcome === 'failure' ? __('admin.orders.failure_closed_status') : __('admin.orders.no_next_status') }}</p>
        @endif
    </section>

    @if ($canCreateReturn || $returnCases->isNotEmpty())
        <section class="card panel customer-section-panel mb-4">
            <div class="panel-heading"><div><p class="eyebrow">{{ __('admin.returns.title') }}</p><h2>{{ __('admin.returns.title') }}</h2></div>@if ($canCreateReturn)<a class="btn btn-primary primary-button" href="{{ route('admin.returns.order-create', $record->id) }}">{{ __('admin.returns.create') }}</a>@endif</div>
            @forelse ($returnCases as $case)
                <article class="order-operation-record mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2"><div><strong dir="ltr">{{ $case->return_number }}</strong> · {{ __('admin.returns.'.$case->reason_code) }} · <span class="badge text-bg-secondary">{{ __('admin.returns.statuses.'.$case->status) }}</span></div><a class="btn btn-sm btn-outline-secondary secondary-button" href="{{ route('admin.returns.show', $case->id) }}">{{ __('admin.returns.view') }}</a></article>
            @empty
            @endforelse
        </section>
    @endif

    @if ($canCloseOrder || $canReopenOrder)
        <section class="card panel customer-section-panel mb-4 hs-order-closure-panel">
            <div class="panel-heading"><div><p class="eyebrow">{{ __('admin.orders.closure') }}</p><h2>{{ __('admin.orders.close_failure') }}</h2></div></div>
            @if ($canCloseOrder)
                <p class="text-secondary">{{ __('admin.orders.failure_closure_help') }}</p>
                <form method="POST" action="{{ route('admin.orders.events.store', $record->id) }}">
                    @csrf<input type="hidden" name="event_type" value="closed"><input type="hidden" name="outcome" value="failure">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5"><label class="form-label" for="closure-reason">{{ __('admin.orders.failure_reason') }}</label><select class="form-select" id="closure-reason" name="reason_code" required><option value="">—</option>@foreach (\App\Support\OrderLifecycle::FAILURE_REASONS as $reason)<option value="{{ $reason }}">{{ __('admin.orders.failure_reasons.'.$reason) }}</option>@endforeach</select></div>
                        <div class="col-md-5"><label class="form-label" for="closure-details">{{ __('admin.orders.reason_details') }}</label><textarea class="form-control" id="closure-details" name="reason_details" rows="2" maxlength="500"></textarea></div>
                        <div class="col-md-2"><button class="btn btn-outline-danger secondary-button" type="submit">{{ __('admin.orders.close_failure') }}</button></div>
                    </div>
                </form>
            @endif
            @if ($canReopenOrder)
                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                    <p class="mb-0">{{ __('admin.orders.closed_as_failure', ['reason' => __('admin.orders.failure_reasons.'.$record->closure_reason_code)]) }}</p>
                    <form method="POST" action="{{ route('admin.orders.events.store', $record->id) }}" onsubmit="return confirm(@js(__('admin.orders.confirm_reopen')))" >
                        @csrf<input type="hidden" name="event_type" value="reopened">
                        <button class="btn btn-outline-secondary secondary-button" type="submit">{{ __('admin.orders.reopen') }}</button>
                    </form>
                </div>
            @endif
        </section>
    @endif

    <section class="card panel customer-section-panel mb-4">
        <div class="panel-heading"><div><p class="eyebrow">{{ __('admin.orders.customer_details') }}</p><h2>{{ $record->customer_name }}</h2></div></div>
        <p class="mb-1" dir="ltr">{{ $record->customer_phone }}</p>
        @if ($record->customer_email)<p class="mb-0" dir="ltr">{{ $record->customer_email }}</p>@endif
    </section>

    @foreach ($revisions as $revision)
        <section class="card panel customer-section-panel mb-4">
            <div class="panel-heading"><div><p class="eyebrow">{{ __('admin.orders.revision', ['number' => $revision->revision_no]) }}</p><h2>{{ __('admin.orders.items') }}</h2></div><span class="status-pill">{{ __('admin.orders.revision_status') }}: {{ $revision->status }}</span></div>
            <div class="table-scroll"><table class="table table-hover align-middle orders-table mb-0">
                <thead><tr><th>{{ __('admin.orders.product') }}</th><th>{{ __('admin.orders.sku') }}</th><th>{{ __('admin.orders.variant') }}</th><th>{{ __('admin.orders.quantity') }}</th><th>{{ __('admin.orders.unit_price') }}</th><th>{{ __('admin.orders.line_total') }}</th></tr></thead>
                <tbody>
                    @foreach ($revision->items as $item)
                        @php($variant = $item->variant_snapshot ? json_decode($item->variant_snapshot, true) : [])
                        <tr><td><div class="order-product-cell">
                            @if ($item->product_image_path_snapshot)
                                <img class="order-product-thumbnail" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item->product_image_path_snapshot) }}" alt="{{ $item->product_name_snapshot }}" loading="lazy" width="48" height="56">
                            @else
                                <span class="order-product-thumbnail order-product-thumbnail-placeholder" aria-hidden="true">{{ mb_substr($item->product_name_snapshot, 0, 1) }}</span>
                            @endif
                            <span>{{ $item->product_name_snapshot }}</span>
                        </div></td><td dir="ltr">{{ $variant['sku'] ?? $item->sku_snapshot }}</td><td>{{ $variant['name'] ?? $variant['variant_name'] ?? collect($variant['attributes'] ?? [])->map(fn ($attribute) => ($attribute['name'] ?? '').': '.($attribute['value'] ?? ''))->implode(' · ') }}</td><td>{{ $item->quantity }}</td><td dir="ltr">{{ $item->currency }} {{ $item->charged_unit_price }}</td><td dir="ltr">{{ $item->currency }} {{ $item->line_total }}</td></tr>
                    @endforeach
                </tbody>
            </table></div>
            @if ($revision->address)
                <div class="order-address-snapshot mt-3"><h3>{{ __('admin.orders.shipping_address') }}</h3><p class="mb-1"><strong>{{ $revision->address->recipient_name }}</strong> · <span dir="ltr">{{ $revision->address->phone }}</span></p><p class="mb-0">{{ collect([$revision->address->street, $revision->address->building, $revision->address->floor, $revision->address->apartment, $revision->address->district, $revision->address->city, $revision->address->governorate])->filter()->implode('، ') }}</p></div>
            @endif
            <dl class="row order-totals mt-3 mb-0">
                <dt class="col-sm-9">{{ __('admin.orders.subtotal') }}</dt><dd class="col-sm-3" dir="ltr">{{ $revision->currency }} {{ $revision->subtotal }}</dd>
                <dt class="col-sm-9">{{ __('admin.orders.discount') }}</dt><dd class="col-sm-3" dir="ltr">{{ $revision->currency }} {{ $revision->discount_total }}</dd>
                <dt class="col-sm-9">{{ __('admin.orders.customer_shipping') }}</dt><dd class="col-sm-3" dir="ltr">{{ $revision->currency }} {{ $revision->customer_shipping_charge }}</dd>
                @if ($revision->tax_total !== null)<dt class="col-sm-9">{{ __('admin.orders.tax') }}</dt><dd class="col-sm-3" dir="ltr">{{ $revision->currency }} {{ $revision->tax_total }}</dd>@endif
                <dt class="col-sm-9 fw-bold">{{ __('admin.orders.grand_total') }}</dt><dd class="col-sm-3 fw-bold" dir="ltr">{{ $revision->currency }} {{ $revision->grand_total }}</dd>
            </dl>
        </section>
    @endforeach

    @if ($instapayPayment)
        <section class="card panel customer-section-panel mb-4">
            <div class="panel-heading"><div><p class="eyebrow">{{ __('admin.social_orders.methods.instapay') }}</p><h2>{{ __('admin.social_orders.instapay_review') }}</h2></div></div>
            <p class="mb-1"><strong>{{ __('admin.orders.grand_total') }}:</strong> <span dir="ltr">{{ $record->currency }} {{ $record->grand_total }}</span></p>
            <p class="text-secondary">{{ __('admin.social_orders.verify_amount') }}</p>
            @if ($instapayProofs->contains('review_status', 'pending'))<p class="text-secondary">{{ __('admin.social_orders.proof_pending_notice') }}</p>@endif
            @forelse ($instapayProofs as $proof)
                <article class="order-operation-record mb-3">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                        <div><strong>{{ __('admin.social_orders.proof') }} #{{ $proof->id }}</strong><span class="badge ms-2 text-bg-{{ $proof->review_status === 'accepted' ? 'success' : ($proof->review_status === 'rejected' ? 'danger' : 'warning') }}">{{ __('admin.social_orders.statuses.'.$proof->review_status) }}</span><small class="d-block text-secondary mt-1">{{ __('admin.social_orders.proof_submitted_at') }}: <span dir="ltr">{{ \Illuminate\Support\Carbon::parse($proof->submitted_at)->timezone('Africa/Cairo')->format('Y-m-d H:i') }}</span></small>
                            @if ($proof->reviewed_by)<small class="d-block text-secondary">{{ __('admin.social_orders.reviewed_by') }}: {{ $proof->reviewer_name ?: __('admin.orders.system_actor') }}</small>@endif
                            @if ($proof->rejection_reason)<p class="mb-0 mt-2">{{ __('admin.social_orders.rejection_reason') }}: {{ $proof->rejection_reason }}</p>@endif
                        </div>
                    </div>
                    <a href="{{ route('admin.orders.payment-proofs.show', [$record->id, $proof->id]) }}" target="_blank" rel="noopener noreferrer">
                        <img class="instapay-proof-image" src="{{ route('admin.orders.payment-proofs.show', [$record->id, $proof->id]) }}" alt="{{ __('admin.social_orders.proof') }} #{{ $proof->id }}" loading="lazy">
                    </a>
                    @if ($proof->review_status === 'pending')
                        <div class="row g-3 mt-2">
                            <div class="col-md-4"><form method="POST" action="{{ route('admin.orders.payment-proofs.review', [$record->id, $proof->id]) }}" onsubmit="return confirm(@js(__('admin.social_orders.confirm_accept')))" >@csrf<input type="hidden" name="decision" value="accepted"><button class="btn btn-primary primary-button" type="submit">{{ __('admin.social_orders.accept_proof') }}</button></form></div>
                            <div class="col-md-8"><form method="POST" action="{{ route('admin.orders.payment-proofs.review', [$record->id, $proof->id]) }}">@csrf<input type="hidden" name="decision" value="rejected"><label class="form-label" for="rejection-reason-{{ $proof->id }}">{{ __('admin.social_orders.rejection_reason') }}</label><div class="d-flex gap-2"><input class="form-control" id="rejection-reason-{{ $proof->id }}" name="rejection_reason" maxlength="255" required><button class="btn btn-outline-danger" type="submit">{{ __('admin.social_orders.reject_proof') }}</button></div></form></div>
                        </div>
                    @elseif ($proof->review_status === 'accepted')
                        <p class="text-success mb-0 mt-3">{{ __('admin.social_orders.payment_received') }}</p>
                    @else
                        <p class="text-danger mb-0 mt-3">{{ __('admin.social_orders.proof_rejected_notice') }}</p>
                    @endif
                </article>
            @empty
                <p class="text-secondary mb-0">—</p>
            @endforelse
            @if ($instapayPayment->status !== 'paid' && ! $instapayProofs->contains('review_status', 'pending'))
                <form class="order-operation-record mt-3" method="POST" action="{{ route('admin.orders.payment-proofs.store', $record->id) }}" enctype="multipart/form-data">
                    @csrf<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <label class="form-label" for="replacement-payment-proof">{{ __('admin.social_orders.reupload_proof') }}</label>
                    <div class="d-flex gap-2 flex-wrap"><input class="form-control flex-grow-1 @error('payment_proof') is-invalid @enderror" id="replacement-payment-proof" name="payment_proof" type="file" accept="image/jpeg,image/png,image/webp" required><button class="btn btn-primary primary-button" type="submit">{{ __('admin.social_orders.upload_proof') }}</button></div>
                    <div class="form-text">{{ __('admin.social_orders.proof_help') }}</div>
                    @error('payment_proof')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </form>
            @endif
        </section>
    @endif

    <section class="card panel customer-section-panel mb-4">
        <div class="panel-heading"><div><p class="eyebrow">{{ __('admin.orders.shipments') }}</p><h2>{{ __('admin.orders.shipments') }}</h2></div></div>
        @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        <div class="alert alert-info">{{ __('admin.shipments.independent_states') }}</div>
        @if ($shipmentCreation && $shipmentCreation['can_create'])
            <form class="order-operation-record mb-4" method="POST" action="{{ route('admin.orders.shipments.store', $record->id) }}">
                @csrf<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <h3 class="h5">{{ __('admin.shipments.create') }}</h3>
                <div class="row g-3 align-items-end">
                    <div class="col-md-4"><label class="form-label" for="shipment-company">{{ __('admin.shipments.carrier') }}</label><select class="form-select" id="shipment-company" name="shipping_company_id" required><option value="">—</option>@foreach ($shipmentCreation['companies'] as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label" for="shipment-tracking">{{ __('admin.shipments.tracking') }}</label><input class="form-control" id="shipment-tracking" name="tracking_number" maxlength="120" dir="ltr"></div>
                    <div class="col-md-4"><label class="form-label" for="shipment-charge">{{ __('admin.shipments.customer_charge') }}</label><input class="form-control" id="shipment-charge" name="customer_shipping_charge" type="number" min="0" step="0.0001" max="999999999999999.9999" value="{{ $shipmentCreation['remaining_customer_charge'] }}" required><div class="form-text">{{ __('admin.shipments.customer_charge_help') }}</div></div>
                    <div class="col-md-4"><label class="form-label" for="shipment-cost">{{ __('admin.shipments.carrier_cost') }}</label><input class="form-control" id="shipment-cost" name="carrier_cost_actual" type="number" min="0" step="0.0001" max="999999999999999.9999"></div>
                    <div class="col-md-4"><label class="form-label">{{ __('admin.shipments.expected_cod') }}</label><input class="form-control" value="{{ $shipmentCreation['expected_cod_collection'] }} {{ $record->currency }}" readonly dir="ltr"></div>
                    <div class="col-md-4"><label class="form-label" for="shipment-description">{{ __('admin.shipments.description') }}</label><input class="form-control" id="shipment-description" name="description" maxlength="500"></div>
                    <div class="col-12"><button class="btn btn-primary primary-button" type="submit">{{ __('admin.shipments.create') }}</button></div>
                </div>
            </form>
        @elseif ($shipmentCreation)
            <div class="alert alert-warning">{{ $shipmentCreation['block_message'] }} @if ($shipmentCreation['companies']->isEmpty())<a href="{{ route('admin.shipping-companies.index') }}">{{ __('admin.shipping_companies.navigation') }}</a>@endif</div>
        @endif
        @forelse ($shipments as $shipment)
            <article class="order-operation-record mb-4">
                <div class="d-flex flex-wrap justify-content-between gap-2"><div><strong>{{ __('admin.shipments.attempt') }} {{ $loop->iteration }} · {{ $shipment->shipment_number }}</strong><div>{{ $shipment->carrier_name_snapshot ?: $shipment->carrier_name ?: '—' }} · <span class="badge text-bg-secondary">{{ __('admin.shipments.statuses.'.$shipment->status) }}</span></div></div><div dir="ltr">{{ $shipment->tracking_number ?: '—' }}</div></div>
                <div class="row g-2 mt-2"><div class="col-md-3"><small class="text-secondary">{{ __('admin.shipments.customer_shipping') }}</small><div dir="ltr">{{ $shipment->customer_shipping_charge }} {{ $shipment->currency }}</div></div><div class="col-md-3"><small class="text-secondary">{{ __('admin.shipments.carrier_cost') }}</small><div dir="ltr">{{ $shipment->carrier_cost_actual ?? '—' }} {{ $shipment->currency }}</div></div><div class="col-md-3"><small class="text-secondary">{{ __('admin.shipments.expected_cod') }}</small><div dir="ltr">{{ $shipment->expected_cod_collection }} {{ $shipment->currency }}</div></div><div class="col-md-3"><small class="text-secondary">{{ __('admin.shipments.actual_cod') }}</small><div dir="ltr">{{ $shipment->actual_cod_collected ?? '—' }} {{ $shipment->currency }}</div></div>
                    @if ($shipment->actual_cod_collected !== null)<div class="col-md-3"><small class="text-secondary">{{ __('admin.shipments.variance') }}</small><div dir="ltr">{{ $shipment->collection_variance }} {{ $shipment->currency }}</div></div>@endif
                    <div class="col-md-3"><small class="text-secondary">{{ __('admin.shipments.shipped_at') }}</small><div dir="ltr">{{ $shipment->shipped_at ? \Illuminate\Support\Carbon::parse($shipment->shipped_at)->timezone('Africa/Cairo')->format('Y-m-d H:i') : '—' }}</div></div><div class="col-md-3"><small class="text-secondary">{{ __('admin.shipments.delivered_at') }}</small><div dir="ltr">{{ $shipment->delivered_at ? \Illuminate\Support\Carbon::parse($shipment->delivered_at)->timezone('Africa/Cairo')->format('Y-m-d H:i') : '—' }}</div></div></div>
                <h3 class="h6 mt-3">{{ __('admin.shipments.event_history') }}</h3>
                @foreach ($shipment->events as $shipmentEvent)<div class="border-top py-2"><strong>{{ __('admin.shipments.statuses.'.$shipmentEvent->status) }}</strong> · <span dir="ltr">{{ \Illuminate\Support\Carbon::parse($shipmentEvent->event_at)->timezone('Africa/Cairo')->format('Y-m-d H:i') }}</span><div>{{ $shipmentEvent->description }}@if ($shipmentEvent->location) · {{ $shipmentEvent->location }}@endif</div><small class="text-secondary">{{ __('admin.orders.actor') }}: {{ $shipmentEvent->actor_name ?: __('admin.orders.system_actor') }}</small></div>@endforeach
                @if (auth()->user()->hasPermission('shipments.manage') && $record->status === 'shipped' && $shipment->next_statuses)
                    <form class="border-top pt-3 mt-2" method="POST" action="{{ route('admin.orders.shipments.events.store', [$record->id, $shipment->id]) }}">
                        @csrf<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <div class="row g-3 align-items-end"><div class="col-md-3"><label class="form-label" for="shipment-status-{{ $shipment->id }}">{{ __('admin.shipments.shipment_state') }}</label><select class="form-select" name="status" id="shipment-status-{{ $shipment->id }}" required>@foreach ($shipment->next_statuses as $nextStatus)<option value="{{ $nextStatus }}">{{ __('admin.shipments.statuses.'.$nextStatus) }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label" for="shipment-event-time-{{ $shipment->id }}">{{ __('admin.shipments.event_time') }}</label><input class="form-control" type="datetime-local" id="shipment-event-time-{{ $shipment->id }}" name="event_at" value="{{ now('Africa/Cairo')->format('Y-m-d\\TH:i') }}" required></div><div class="col-md-3"><label class="form-label" for="shipment-event-cost-{{ $shipment->id }}">{{ __('admin.shipments.carrier_cost') }}</label><input class="form-control" type="number" min="0" step="0.0001" id="shipment-event-cost-{{ $shipment->id }}" name="carrier_cost_actual" value="{{ $shipment->carrier_cost_actual }}"></div><div class="col-md-3"><label class="form-label" for="shipment-actual-cod-{{ $shipment->id }}">{{ __('admin.shipments.actual_cod') }}</label><input class="form-control" type="number" min="0" step="0.0001" id="shipment-actual-cod-{{ $shipment->id }}" name="actual_cod_collected" value="{{ $shipment->actual_cod_collected }}"></div><div class="col-md-6"><label class="form-label" for="shipment-event-location-{{ $shipment->id }}">{{ __('admin.shipments.location') }}</label><input class="form-control" id="shipment-event-location-{{ $shipment->id }}" name="location" maxlength="190"></div><div class="col-md-6"><label class="form-label" for="shipment-event-description-{{ $shipment->id }}">{{ __('admin.shipments.description') }}</label><input class="form-control" id="shipment-event-description-{{ $shipment->id }}" name="description" maxlength="500"></div><div class="col-12"><button class="btn btn-primary primary-button" type="submit">{{ __('admin.shipments.record_event') }}</button></div></div>
                    </form>
                @endif
            </article>
        @empty<p class="text-secondary mb-0">—</p>@endforelse
    </section>

    @if (auth()->user()->hasPermission('expenses.manage'))
        <section class="card panel customer-section-panel mb-4">
            <div class="panel-heading"><div><p class="eyebrow">{{ __('admin.returns.costs') }}</p><h2>{{ __('admin.returns.cost_history') }}</h2></div></div>
            <form method="POST" action="{{ route('admin.orders.costs.store', $record->id) }}" class="row g-3 align-items-end">@csrf<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <div class="col-lg-3"><label class="form-label">{{ __('admin.returns.cost_type') }}</label><select class="form-select" name="cost_type" required>@foreach(['product','packaging','advertising','other'] as $type)<option value="{{ $type }}">{{ __('admin.returns.cost_types.'.$type) }}</option>@endforeach</select></div>
                <div class="col-lg-3"><label class="form-label">{{ __('admin.returns.product') }}</label><select class="form-select" name="order_item_id"><option value="">—</option>@foreach($costItems as $costItem)@php($costVariant = $costItem->variant_snapshot ? json_decode($costItem->variant_snapshot, true) : [])<option value="{{ $costItem->order_item_id }}">{{ $costItem->product_name_snapshot }} · {{ $costItem->sku_snapshot }} · {{ $costVariant['variant_name'] ?? '' }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">{{ __('admin.returns.basis') }}</label><select class="form-select" name="basis"><option value="actual">{{ __('admin.returns.actual') }}</option><option value="estimate">{{ __('admin.returns.estimate') }}</option></select></div>
                <div class="col-md-2"><label class="form-label">{{ __('admin.returns.amount') }}</label><input class="form-control" type="number" min="0" step="0.0001" name="amount" required></div>
                <div class="col-lg-3"><label class="form-label">{{ __('admin.returns.description') }}</label><input class="form-control" name="description" maxlength="255" required></div>
                <div class="col-md-2"><button class="btn btn-primary primary-button" type="submit">{{ __('admin.returns.save_cost') }}</button></div>
            </form>
            <p class="small text-secondary mt-2">{{ __('admin.returns.cost_note') }}</p>
            <p class="small text-secondary">{{ __('admin.returns.product_cost_note') }}</p>
            <div class="table-responsive mt-3"><table class="table table-sm align-middle mb-0"><thead><tr><th>{{ __('admin.returns.cost_type') }}</th><th>{{ __('admin.returns.product') }}</th><th>{{ __('admin.returns.basis') }}</th><th>{{ __('admin.returns.amount') }}</th><th>{{ __('admin.returns.description') }}</th><th>{{ __('admin.orders.actor') }}</th><th>{{ __('admin.orders.placed_at') }}</th><th></th></tr></thead><tbody>@forelse($costEntries as $entry)@php($linkedItem = $entry->order_item_id ? $costItems->firstWhere('order_item_id', $entry->order_item_id) : null)<tr><td>{{ __('admin.returns.cost_types.'.$entry->cost_type) }} @if($entry->reverses_entry_id)<small class="d-block text-danger">{{ __('admin.returns.reverse_cost') }} #{{ $entry->reverses_entry_id }}</small>@endif</td><td>{{ $linkedItem?->product_name_snapshot ?: '—' }} @if($linkedItem)<small class="d-block" dir="ltr">{{ $linkedItem->sku_snapshot }}</small>@endif</td><td>{{ __('admin.returns.'.$entry->basis) }}</td><td dir="ltr">{{ $entry->amount }} {{ $entry->currency }}</td><td>{{ $entry->notes }}</td><td>{{ $entry->actor_name ?: '—' }}</td><td dir="ltr">{{ \Illuminate\Support\Carbon::parse($entry->recorded_at)->timezone('Africa/Cairo')->format('Y-m-d H:i') }}</td><td>@if(!$entry->reverses_entry_id && !$entry->is_reversed)<form method="POST" action="{{ route('admin.orders.costs.reverse', [$record->id, $entry->id]) }}" onsubmit="return confirm(@js(__('admin.returns.reverse_cost').' #'.$entry->id))">@csrf<input type="hidden" name="reason" value="{{ __('admin.returns.cost_reverse_reason') }}"><button class="btn btn-sm btn-outline-danger">{{ __('admin.returns.reverse_cost') }}</button></form>@endif</td></tr>@empty<tr><td colspan="8" class="text-secondary">—</td></tr>@endforelse</tbody></table></div>
        </section>
    @endif

    <section class="card panel customer-section-panel mb-4">
        <div class="panel-heading"><div><p class="eyebrow">{{ __('admin.orders.event_log') }}</p><h2>{{ __('admin.orders.event_log') }}</h2></div></div>
        @forelse ($events as $event)
            @php($details = $event->details ? json_decode($event->details, true) : [])
            <article class="order-event-row">
                <div class="d-flex justify-content-between gap-3 flex-wrap"><strong>{{ str_starts_with($event->event_type, 'payment_proof_') ? __('admin.social_orders.events.'.$event->event_type) : (__('admin.orders.events.'.$event->event_type) !== 'admin.orders.events.'.$event->event_type ? __('admin.orders.events.'.$event->event_type) : $event->event_type) }}</strong><small dir="ltr">{{ \Illuminate\Support\Carbon::parse($event->occurred_at)->timezone('Africa/Cairo')->format('Y-m-d H:i') }}</small></div>
                <small class="text-secondary">{{ __('admin.orders.actor') }}: {{ $event->actor_name ?: __('admin.orders.system_actor') }}</small>
                @if ($event->event_type === 'assigned')<p class="mb-0">{{ $details['previous_assignee']['name'] ?? __('admin.orders.unassigned') }} → {{ $details['new_assignee']['name'] ?? '—' }}</p>
                @elseif ($event->event_type === 'follow_up')<p class="mb-0" dir="ltr">{{ ! empty($details['previous_follow_up_at']) ? \Illuminate\Support\Carbon::parse($details['previous_follow_up_at'], 'UTC')->timezone('Africa/Cairo')->format('Y-m-d H:i') : '—' }} → {{ ! empty($details['new_follow_up_at']) ? \Illuminate\Support\Carbon::parse($details['new_follow_up_at'], 'UTC')->timezone('Africa/Cairo')->format('Y-m-d H:i') : '—' }}</p>
                @elseif ($event->event_type === 'note_added')<p class="mb-0">{{ $details['note'] ?? '' }}</p>
                @elseif ($event->event_type === 'created')<p class="mb-0">{{ $details['channel'] ?? '' }}</p>
                @elseif ($event->event_type === 'status_changed')<p class="mb-0">{{ __('admin.order_status.'.$event->previous_status) !== 'admin.order_status.'.$event->previous_status ? __('admin.order_status.'.$event->previous_status) : $event->previous_status }} → {{ __('admin.order_status.'.$event->current_status) !== 'admin.order_status.'.$event->current_status ? __('admin.order_status.'.$event->current_status) : $event->current_status }}@if ($event->close_outcome === 'success') · {{ __('admin.orders.outcomes.success') }}@endif</p>
                @elseif ($event->event_type === 'closed')<p class="mb-0">{{ __('admin.orders.outcomes.'.$event->close_outcome) }}@if ($event->reason_code) · {{ __('admin.orders.failure_reasons.'.$event->reason_code) }}@endif</p>@if (! empty($details['reason_details']))<small>{{ $details['reason_details'] }}</small>@endif
                @elseif ($event->event_type === 'payment_proof_reviewed')<p class="mb-0">{{ __('admin.social_orders.statuses.'.$details['decision']) }}@if (! empty($details['reason'])) · {{ $details['reason'] }}@endif</p>
                @elseif (in_array($event->event_type, ['damaged_return_opened', 'return_status_changed', 'damaged_return_closed'], true))<p class="mb-0">@if (! empty($details['status'])){{ __('admin.returns.statuses.'.$details['status']) }}@elseif (! empty($details['resolution'])){{ __('admin.returns.resolutions.'.$details['resolution']) }}@else{{ __('admin.returns.title') }}@endif @if (! empty($details['return_id']))· <a href="{{ route('admin.returns.show', $details['return_id']) }}">{{ __('admin.returns.view') }}</a>@endif</p>
                @elseif ($event->event_type === 'reopened')<p class="mb-0">{{ __('admin.orders.reopen_restores', ['status' => __('admin.order_status.'.$event->current_status)]) }}</p>@endif
            </article>
        @empty<p class="text-secondary mb-0">—</p>@endforelse
    </section>

    <section class="card panel customer-section-panel">
        <div class="panel-heading"><div><p class="eyebrow">{{ __('admin.orders.followed_by') }}</p><h2>{{ __('admin.orders.save_event') }}</h2></div></div>
        <p class="text-secondary">{{ __('admin.orders.follow_up_optional') }}</p>
        <form method="POST" action="{{ route('admin.orders.events.store', $record->id) }}">
            @csrf<input type="hidden" name="event_type" value="follow_up_update">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4"><label class="form-label" for="assigned-user">{{ __('admin.orders.assign') }}</label><select class="form-select" id="assigned-user" name="assigned_user_id"><option value="">{{ __('admin.orders.no_assignment_change') }}</option>@foreach ($staff as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select></div>
                <div class="col-lg-4"><label class="form-label" for="next-follow-up">{{ __('admin.orders.schedule_follow_up') }}</label><input class="form-control" id="next-follow-up" type="datetime-local" name="next_follow_up_at"></div>
                <div class="col-lg-4"><label class="form-label" for="order-note">{{ __('admin.orders.add_note') }}</label><textarea class="form-control" id="order-note" name="note" rows="2" maxlength="5000" placeholder="{{ __('admin.orders.note_placeholder') }}"></textarea></div>
                <div class="col-12"><button class="btn btn-primary primary-button" type="submit">{{ __('admin.orders.save_event') }}</button></div>
            </div>
        </form>
    </section>
@endsection
