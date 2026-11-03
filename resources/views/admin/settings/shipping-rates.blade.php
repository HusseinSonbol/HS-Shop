@extends('admin.layouts.app')

@section('title', __('admin.shipping_rates.title'))

@section('content')
    <section class="page-heading d-flex align-items-center justify-content-between gap-3 flex-wrap mb-4">
        <div>
            <p class="eyebrow mb-1">{{ __('admin.shipping_rates.eyebrow') }}</p>
            <h1 class="mb-1">{{ __('admin.shipping_rates.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('admin.shipping_rates.description') }}</p>
        </div>
    </section>

    <div class="alert alert-info" role="status">{{ __('admin.shipping_rates.checkout_note') }}</div>

    <section class="card panel customer-list-panel">
        <form method="POST" action="{{ route('admin.settings.shipping-rates.update') }}" id="shipping-rates-form">
            @csrf
            @method('PUT')
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">{{ __('admin.shipping_rates.table_eyebrow') }}</p>
                    <h2>{{ __('admin.shipping_rates.table_title') }}</h2>
                </div>
                <button class="btn btn-primary primary-button" type="button" data-add-shipping-rate>{{ __('admin.shipping_rates.add') }}</button>
            </div>
            <div class="table-scroll">
                <table class="table table-hover align-middle orders-table mb-0">
                    <thead><tr><th>{{ __('admin.shipping_rates.arabic_name') }}</th><th>{{ __('admin.shipping_rates.english_name') }}</th><th>{{ __('admin.shipping_rates.charge') }}</th><th></th></tr></thead>
                    <tbody data-shipping-rate-rows>
                        @foreach (old('rates', $rates) as $index => $rate)
                            <tr>
                                <td>
                                    <input type="hidden" name="rates[{{ $index }}][code]" value="{{ $rate['code'] ?? '' }}">
                                    <label class="visually-hidden" for="rate-ar-{{ $index }}">{{ __('admin.shipping_rates.arabic_name') }}</label>
                                    <input id="rate-ar-{{ $index }}" class="form-control" name="rates[{{ $index }}][governorate_ar]" value="{{ $rate['governorate_ar'] ?? '' }}" maxlength="120" required>
                                </td>
                                <td><label class="visually-hidden" for="rate-en-{{ $index }}">{{ __('admin.shipping_rates.english_name') }}</label><input id="rate-en-{{ $index }}" class="form-control" name="rates[{{ $index }}][governorate_en]" value="{{ $rate['governorate_en'] ?? '' }}" maxlength="120" required></td>
                                <td><div class="input-group"><input class="form-control" type="number" name="rates[{{ $index }}][charge]" value="{{ $rate['charge'] ?? '' }}" min="0" step="0.0001" inputmode="decimal" required aria-label="{{ __('admin.shipping_rates.charge') }}"><span class="input-group-text">{{ __('admin.currency') }}</span></div></td>
                                <td class="text-end"><button class="btn btn-sm btn-outline-danger" type="button" data-remove-shipping-rate>{{ __('admin.shipping_rates.remove') }}</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('rates.*.governorate_ar')<div class="text-danger small px-3 pt-2">{{ $message }}</div>@enderror
            @error('rates.*.governorate_en')<div class="text-danger small px-3 pt-2">{{ $message }}</div>@enderror
            @error('rates.*.charge')<div class="text-danger small px-3 pt-2">{{ $message }}</div>@enderror
            <div class="p-3 d-flex align-items-center justify-content-between gap-3 flex-wrap">
                <span class="small text-body-secondary">{{ __('admin.shipping_rates.empty_hint') }}</span>
                <button class="btn btn-primary primary-button" type="submit">{{ __('admin.shipping_rates.save') }}</button>
            </div>
        </form>
    </section>

    <template id="shipping-rate-row-template">
        <tr>
            <td><input type="hidden" name="rates[__INDEX__][code]" value=""><label class="visually-hidden" for="rate-ar-__INDEX__">{{ __('admin.shipping_rates.arabic_name') }}</label><input id="rate-ar-__INDEX__" class="form-control" name="rates[__INDEX__][governorate_ar]" maxlength="120" required></td>
            <td><label class="visually-hidden" for="rate-en-__INDEX__">{{ __('admin.shipping_rates.english_name') }}</label><input id="rate-en-__INDEX__" class="form-control" name="rates[__INDEX__][governorate_en]" maxlength="120" required></td>
            <td><div class="input-group"><input class="form-control" type="number" name="rates[__INDEX__][charge]" min="0" step="0.0001" inputmode="decimal" required aria-label="{{ __('admin.shipping_rates.charge') }}"><span class="input-group-text">{{ __('admin.currency') }}</span></div></td>
            <td class="text-end"><button class="btn btn-sm btn-outline-danger" type="button" data-remove-shipping-rate>{{ __('admin.shipping_rates.remove') }}</button></td>
        </tr>
    </template>
@endsection

@push('scripts')
    <script>
        (() => {
            const rows = document.querySelector('[data-shipping-rate-rows]');
            const template = document.getElementById('shipping-rate-row-template');
            let nextIndex = Math.max(-1, ...Array.from(rows.querySelectorAll('input[name$="[code]"]')).map((input) => Number(input.name.match(/rates\[(\d+)\]/)?.[1] ?? -1))) + 1;

            document.querySelector('[data-add-shipping-rate]').addEventListener('click', () => {
                const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
                rows.insertAdjacentHTML('beforeend', html);
            });

            rows.addEventListener('click', (event) => {
                if (event.target.closest('[data-remove-shipping-rate]')) {
                    event.target.closest('tr').remove();
                }
            });
        })();
    </script>
@endpush
