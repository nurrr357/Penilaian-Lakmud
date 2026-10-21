/**
 * Perilaku sidebar/drawer (dokumen audit §6.4).
 *  - <840px: drawer off-canvas; tombol menu membuka, backdrop/Escape menutup.
 *  - >=840px: rail 88px; tombol menu melebarkan/menyempitkan.
 *  - Preferensi rail disimpan; >=1200px melebar otomatis bila belum ada preferensi.
 * Dipasang idempotent (dataset.mounted) agar aman dipanggil ulang.
 *
 * Catatan penerapan: nama kunci localStorage asal memuat karakter U+2026 literal
 * ('lakmud_si…ense') — ditulis ulang sesuai dokumen §2.2.
 */
const STORAGE_KEY = 'lakmud_sidebar_expanded';

export function mountSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');

    if (!sidebar || !toggle || sidebar.dataset.mounted === '1') {
        return;
    }
    sidebar.dataset.mounted = '1';

    const isRail = () => window.matchMedia('(min-width: 840px)').matches;

    const desktopPreference = localStorage.getItem(STORAGE_KEY);
    let railExpanded = desktopPreference === null
        ? window.matchMedia('(min-width: 1200px)').matches
        : desktopPreference === 'expanded';
    let drawerOpen = false;

    const applyState = () => {
        const rail = isRail();
        sidebar.classList.toggle('is-expanded', rail && railExpanded);
        sidebar.classList.toggle('is-collapsed', rail && !railExpanded);
        sidebar.classList.toggle('is-open', !rail && drawerOpen);
        backdrop?.classList.toggle('is-visible', !rail && drawerOpen);
        if (backdrop) {
            backdrop.hidden = !(!rail && drawerOpen);
        }
        sidebar.inert = !rail && !drawerOpen;
        toggle.setAttribute('aria-expanded', String(rail ? railExpanded : drawerOpen));
    };

    // Item aktif selalu terlihat tanpa perlu scroll manual.
    const revealActive = () => {
        if (!isRail() || !railExpanded) {
            return;
        }
        sidebar.querySelector('.app-nav-link.is-active')?.scrollIntoView({ block: 'nearest' });
    };

    applyState();
    setTimeout(revealActive, 260);

    toggle.addEventListener('click', () => {
        if (isRail()) {
            railExpanded = !railExpanded;
            localStorage.setItem(STORAGE_KEY, railExpanded ? 'expanded' : 'collapsed');
        } else {
            drawerOpen = !drawerOpen;
        }
        applyState();
        setTimeout(revealActive, 260);
    });

    backdrop?.addEventListener('click', () => {
        drawerOpen = false;
        applyState();
        toggle.focus();
    });

    sidebar.querySelectorAll('nav a').forEach((link) => {
        link.addEventListener('click', () => {
            if (!isRail()) {
                drawerOpen = false;
                applyState();
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || isRail() || !drawerOpen || document.querySelector('dialog[open]')) {
            return;
        }
        drawerOpen = false;
        applyState();
        toggle.focus();
    });

    window.matchMedia('(min-width: 840px)').addEventListener('change', () => {
        drawerOpen = false;
        applyState();
    });
}
