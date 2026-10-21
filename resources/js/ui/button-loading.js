/**
 * Loading feedback untuk semua submit form (state terlihat + anti double-submit).
 * Dipasang global sekali; form dengan data-no-loading dikecualikan.
 * Catatan: onchange="this.form.submit()" mem-bypass event submit, jadi select
 * auto-submit ditangani lewat listener 'change' terpisah.
 */
export function mountButtonLoading() {
    const SPIN = '<span class="btn-spinner" aria-hidden="true"></span>';

    function setLoading(btn) {
        if (btn.disabled || btn.dataset.loading === '1') return;
        btn.dataset.loading = '1';
        btn.dataset.origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.classList.add('is-loading');
        btn.innerHTML = SPIN + (btn.dataset.loadingText || 'Memproses\u2026');
    }

    function reset(btn) {
        if (btn.dataset.loading !== '1') return;
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        btn.classList.remove('is-loading');
        btn.innerHTML = btn.dataset.origHtml;
        delete btn.dataset.loading;
    }

    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-loading')) return;
        const btn = form.querySelector('[type="submit"]');
        if (!btn) return;
        setLoading(btn);
        // Inline onsubmit (confirm hapus) bisa membatalkan submit -> pulihkan tombol.
        setTimeout(() => {
            if (e.defaultPrevented) reset(btn);
        }, 0);
    }, true);

    // Select auto-submit (filter halaman): tampilkan loading saat berubah.
    document.addEventListener('change', (e) => {
        const el = e.target;
        if (el instanceof HTMLSelectElement && el.hasAttribute('data-autosubmit') && el.form) {
            if (el.getAttribute('aria-busy') === 'true') return;
            el.setAttribute('aria-busy', 'true');
            el.form.requestSubmit();
        }
    }, true);

    // Pulihkan saat kembali via bfcache (tombol Back browser).
    window.addEventListener('pageshow', (e) => {
        if (!e.persisted) return;
        document.querySelectorAll('[data-loading="1"]').forEach(reset);
        document.querySelectorAll('select[aria-busy="true"]').forEach((s) => {
            s.disabled = false;
            s.removeAttribute('aria-busy');
        });
    });
}
