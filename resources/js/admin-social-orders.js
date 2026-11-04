document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-social-order-form]');
    if (!form) return;

    const lines = form.querySelector('[data-order-lines]');
    const template = form.querySelector('[data-order-line-template]');
    const indices = [...lines.querySelectorAll('select[name^="items["]')]
        .map((select) => Number(select.name.match(/^items\[(\d+)\]/)?.[1] ?? -1));
    let nextIndex = Math.max(-1, ...indices) + 1;

    form.querySelector('[data-add-order-item]')?.addEventListener('click', () => {
        const markup = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        lines.insertAdjacentHTML('beforeend', markup);
    });

    lines.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-order-item]');
        if (!removeButton || lines.querySelectorAll('[data-order-line]').length <= 1) return;
        removeButton.closest('[data-order-line]').remove();
    });

    const method = form.querySelector('[data-payment-method]');
    const proofUpload = form.querySelector('[data-proof-upload]');
    const proofInput = proofUpload?.querySelector('input[type="file"]');
    const updateProofRequirement = () => {
        const required = method.value === 'instapay';
        proofUpload.hidden = !required;
        proofInput.required = required;
        if (!required) proofInput.value = '';
    };

    method.addEventListener('change', updateProofRequirement);
    updateProofRequirement();
});
