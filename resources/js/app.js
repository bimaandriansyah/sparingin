
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
}

/**
 * ajaxForm — submits a plain Fortify form (login/register) in the background.
 * Errors appear in place (no full-page reload, typed values kept); on success we navigate.
 *
 * Usage: <form method="POST" action="…" x-data="ajaxForm" x-on:submit.prevent="submit"
 *              data-redirect="/dashboard" data-two-factor="/two-factor-challenge">
 * Show errors with x-text="error('phone')". Falls back to a normal submit on anything unexpected
 * (expired CSRF token, server error), so the form never gets stuck.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('ajaxForm', () => ({
        submitting: false,
        errors: {},

        error(field) {
            return this.errors[field]?.[0] ?? '';
        },

        async submit() {
            if (this.submitting) return;
            const form = this.$el.closest('form');
            this.submitting = true;
            this.errors = {};

            let response;
            try {
                response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
            } catch {
                this.submitting = false;
                this.errors = { form: ['Koneksi terputus. Periksa internet lalu coba lagi.'] };
                return;
            }

            if (response.ok) {
                const data = await response.json().catch(() => ({}));
                window.location.assign(data.two_factor ? form.dataset.twoFactor : form.dataset.redirect);
                return; // keep the loading state while the next page loads
            }

            if (response.status === 422 || response.status === 429) {
                const data = await response.json().catch(() => ({}));
                this.errors = data.errors ?? { form: [data.message ?? 'Terjadi kesalahan.'] };
                this.submitting = false;
                this.$nextTick(() => form.querySelector('[aria-invalid="true"], [data-error]:not(:empty)')?.scrollIntoView({ block: 'center', behavior: 'smooth' }));
                return;
            }

            form.submit(); // 419 (expired session) or anything else: let the server handle it normally
        },
    }));
});
