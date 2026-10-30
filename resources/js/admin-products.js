const productForm = document.querySelector('[data-product-form]');

if (productForm) {
    const rows = productForm.querySelector('[data-variant-rows]');
    const template = productForm.querySelector('[data-variant-template]');
    const addButton = productForm.querySelector('[data-add-variant]');
    const typeSelect = productForm.querySelector('[data-product-type]');
    const englishNameInput = productForm.querySelector('[data-product-name-en]');
    const groupSkuInput = productForm.querySelector('#sku');
    const slugInput = productForm.querySelector('#slug');

    const updateGeneratedIdentifiers = () => {
        if (productForm.dataset.autoIdentifiers !== 'true' || !englishNameInput) return;

        const slug = englishNameInput.value
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'product';

        slugInput.value = slug.slice(0, 180).replace(/-+$/g, '');
        groupSkuInput.value = slugInput.value.slice(0, 80).replace(/-+$/g, '').toUpperCase();
        updateVariantSkus();
    };

    const updateVariantSkus = () => {
        if (!rows || !groupSkuInput) return;

        const variable = typeSelect.value === 'variable';
        const occurrences = new Map();
        rows.querySelectorAll('[data-variant-row]').forEach((row, index) => {
            const parts = variable
                ? ['size_en', 'color_en'].map((field) => row.querySelector(`[name$="[${field}]"]`)?.value || '')
                    .map((value) => value.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''))
                    .filter(Boolean)
                : [String(index + 1).padStart(2, '0')];
            const identity = (parts.join('-') || String(index + 1).padStart(2, '0')).slice(0, 50);
            const familyLength = Math.max(1, 80 - identity.length - 1);
            const family = groupSkuInput.value.slice(0, familyLength).replace(/-+$/g, '');
            const base = `${family}-${identity}`.toUpperCase();
            const occurrence = (occurrences.get(base) || 0) + 1;
            occurrences.set(base, occurrence);
            const suffix = occurrence > 1 ? `-${occurrence}` : '';
            const sku = `${base.slice(0, 80 - suffix.length)}${suffix}`;
            const input = row.querySelector('[data-variant-sku]');
            if (input) input.value = sku;
        });
    };

    const updateProfitRates = () => {
        rows?.querySelectorAll('[data-variant-row]').forEach((row) => {
            const value = (field) => row.querySelector(`[name$="[${field}]"]`)?.value?.trim() || '';
            const output = row.querySelector('[data-profit-rate]');
            if (!output) return;
            const scaled = (text) => {
                if (!/^\d+(?:\.\d{0,4})?$/.test(text)) return null;
                const [whole, fraction = ''] = text.split('.');
                return BigInt(whole) * 10000n + BigInt(fraction.padEnd(4, '0'));
            };
            const inputs = ['factory_unit_cost', 'inbound_delivery_unit_cost', 'packaging_unit_cost', 'advertising_unit_cost'].map((field) => scaled(value(field)));
            const price = scaled(value('current_regular_price'));
            if (inputs.some((input) => input === null) || price === null) { output.value = ''; return; }
            const total = inputs.reduce((sum, input) => sum + input, 0n);
            if (total === 0n) { output.value = ''; return; }
            const difference = price - total;
            const numerator = (difference < 0n ? -difference : difference) * 1000000n;
            let quotient = numerator / total;
            if ((numerator % total) * 2n >= total) quotient += 1n;
            output.value = `${difference < 0n && quotient !== 0n ? '-' : ''}${quotient / 10000n}.${String(quotient % 10000n).padStart(4, '0')}`;
        });
    };

    const refreshRows = () => {
        const variable = typeSelect.value === 'variable';
        productForm.querySelectorAll('[data-variant-attributes]').forEach((fields) => {
            fields.hidden = !variable;
            fields.querySelectorAll('input').forEach((input) => {
                input.disabled = !variable;
            });
        });
        productForm.querySelectorAll('[data-variant-remove]').forEach((button) => {
            button.disabled = rows.querySelectorAll('[data-variant-row]').length < 2;
        });
        if (addButton) {
            addButton.disabled = !variable;
        }
        updateVariantSkus();
        updateProfitRates();
        const defaults = [...productForm.querySelectorAll('[data-variant-default]')];
        if (!defaults.some((checkbox) => checkbox.checked) && defaults[0]) {
            defaults[0].checked = true;
        }
    };

    const addRow = () => {
        const index = Number(rows.dataset.nextIndex || rows.querySelectorAll('[data-variant-row]').length);
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach((input) => {
            input.name = input.name.replaceAll('__INDEX__', String(index));
        });
        rows.append(fragment);
        rows.dataset.nextIndex = String(index + 1);
        refreshRows();
    };

    addButton?.addEventListener('click', addRow);
    englishNameInput?.addEventListener('input', updateGeneratedIdentifiers);
    typeSelect?.addEventListener('change', refreshRows);
    englishNameInput?.addEventListener('input', updateVariantSkus);
    rows?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-variant-remove]');
        if (button && rows.querySelectorAll('[data-variant-row]').length > 1) {
            button.closest('[data-variant-row]').remove();
            refreshRows();
        }
    });
    rows?.addEventListener('change', (event) => {
        const checkbox = event.target.closest('[data-variant-default]');
        if (checkbox?.checked) {
            productForm.querySelectorAll('[data-variant-default]').forEach((other) => {
                if (other !== checkbox) other.checked = false;
            });
        }
    });
    rows?.addEventListener('input', () => { updateVariantSkus(); updateProfitRates(); });
    refreshRows();
    updateGeneratedIdentifiers();
}
