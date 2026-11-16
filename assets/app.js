(() => {
    const dialog = document.getElementById('confirm-dialog');
    let pendingForm = null;

    const dismissFlash = (flash) => {
        if (!flash) {
            return;
        }

        flash.classList.add('is-leaving');
        window.setTimeout(() => flash.remove(), 320);
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.matches('form[data-confirm]')) {
            return;
        }

        if (form.dataset.confirmed === 'true') {
            return;
        }

        const message = form.dataset.confirm || 'Подтвердить действие?';

        if (!dialog || typeof dialog.showModal !== 'function') {
            if (window.confirm(message)) {
                form.dataset.confirmed = 'true';
                form.requestSubmit();
            }

            return;
        }

        event.preventDefault();
        pendingForm = form;

        const text = dialog.querySelector('[data-confirm-text]');
        if (text) {
            text.textContent = message;
        }

        dialog.showModal();
    });

    if (dialog) {
        const confirmButton = dialog.querySelector('[data-confirm-ok]');
        const cancelButton = dialog.querySelector('[data-confirm-cancel]');

        const closeDialog = () => {
            pendingForm = null;
            dialog.close();
        };

        cancelButton?.addEventListener('click', closeDialog);

        confirmButton?.addEventListener('click', () => {
            if (!pendingForm) {
                dialog.close();
                return;
            }

            const form = pendingForm;
            pendingForm = null;
            form.dataset.confirmed = 'true';
            dialog.close();
            form.requestSubmit();
        });

        dialog.addEventListener('click', (event) => {
            if (event.target !== dialog) {
                return;
            }

            const rect = dialog.getBoundingClientRect();
            const inside = (
                event.clientX >= rect.left &&
                event.clientX <= rect.right &&
                event.clientY >= rect.top &&
                event.clientY <= rect.bottom
            );

            if (!inside) {
                closeDialog();
            }
        });

        dialog.addEventListener('cancel', () => {
            pendingForm = null;
        });
    }

    document.querySelectorAll('[data-dismiss-flash]').forEach((button) => {
        button.addEventListener('click', () => {
            dismissFlash(button.closest('.flash'));
        });
    });

    document.querySelectorAll('.flash--success').forEach((flash) => {
        window.setTimeout(() => dismissFlash(flash), 6500);
    });

    const fatherFilter = document.querySelector('[data-father-filter]');

    if (fatherFilter) {
        const items = Array.from(document.querySelectorAll('[data-father-item]'));
        const empty = document.querySelector('[data-father-empty]');

        fatherFilter.addEventListener('input', () => {
            const query = fatherFilter.value.trim().toLowerCase();
            let visible = 0;

            items.forEach((item) => {
                const matches = item.textContent.toLowerCase().includes(query);
                item.hidden = !matches;

                if (matches) {
                    visible += 1;
                }
            });

            if (empty) {
                empty.hidden = visible !== 0;
            }
        });
    }
})();
