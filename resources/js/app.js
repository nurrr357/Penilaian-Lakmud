import '@material/web/progress/circular-progress.js';
import katex from 'katex';
import 'katex/dist/katex.min.css';

document.querySelectorAll('[data-katex]').forEach(element => {
    const formula = element.dataset.katex;
    if (!formula) return;
    katex.render(formula, element, {
        displayMode: element.dataset.katexDisplay === 'true',
        throwOnError: false,
        strict: 'error',
        trust: false,
        output: 'htmlAndMathml',
    });
});

const menu = document.querySelector('#menu');
const backdrop = document.querySelector('#backdrop');
const sidebar = document.querySelector('#sidebar');
function syncSidebar() {
    const mobile = matchMedia('(max-width:840px)').matches;
    const open = mobile ? document.body.classList.contains('menu-open') : !document.body.classList.contains('collapsed');
    if (sidebar) sidebar.inert = !open;
    menu?.setAttribute('aria-expanded', String(open));
}
function closeMenu() { document.body.classList.remove('menu-open'); if (backdrop) backdrop.hidden = true; syncSidebar(); }
syncSidebar();
window.addEventListener('resize', syncSidebar);
menu?.addEventListener('click', () => {
    if (matchMedia('(max-width:840px)').matches) {
        const open = document.body.classList.toggle('menu-open'); backdrop.hidden = !open; menu.setAttribute('aria-expanded', String(open));
    } else { const collapsed = document.body.classList.toggle('collapsed'); menu.setAttribute('aria-expanded', String(!collapsed)); }
    syncSidebar();
});
backdrop?.addEventListener('click', closeMenu);
document.addEventListener('keydown', e => { if (e.key === 'Escape' && !document.querySelector('dialog[open]')) { closeMenu(); menu?.focus(); } });

let dirtyForm = null;
document.querySelectorAll('[data-unsaved]').forEach(form => form.addEventListener('input', () => { dirtyForm = form; }));
window.addEventListener('beforeunload', e => { if (dirtyForm) { e.preventDefault(); e.returnValue = ''; } });
document.addEventListener('click', e => {
    if (dirtyForm && e.target.closest('a[href]')) {
        if (!confirm('Perubahan belum disimpan. Tinggalkan halaman?')) e.preventDefault();
        else dirtyForm = null;
    }
});
document.querySelectorAll('[data-modal-open]').forEach(button => button.addEventListener('click', () => {
    const dialog = document.getElementById(button.dataset.modalOpen);
    dialog._opener = button;
    dialog.showModal();
}));
document.querySelectorAll('dialog').forEach(dialog => {
    dialog.addEventListener('keydown', e => {
        if (e.key !== 'Tab') return;
        const controls = [...dialog.querySelectorAll('button:not(:disabled), input:not([type="hidden"]):not(:disabled), select:not(:disabled), a[href]')];
        const first = controls[0], last = controls.at(-1);
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last?.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first?.focus(); }
    });
    dialog.querySelectorAll('[data-modal-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', e => {
        const box = dialog.getBoundingClientRect();
        if (e.target === dialog && (e.clientX < box.left || e.clientX > box.right || e.clientY < box.top || e.clientY > box.bottom)) dialog.close();
    });
    dialog.addEventListener('close', () => (dialog._opener ?? document.querySelector(`[data-modal-open="${dialog.id}"]`))?.focus());
    if (dialog.hasAttribute('data-reopen')) { dialog.showModal(); dialog.querySelector('[aria-invalid="true"]')?.focus(); }
});
document.addEventListener('submit', e => {
    const form = e.target;
    if (form.dataset.submitting) { e.preventDefault(); return; }
    if (dirtyForm && form !== dirtyForm && !confirm('Perubahan belum disimpan. Tinggalkan halaman?')) { e.preventDefault(); return; }
    dirtyForm = null;
    if (form.dataset.confirm && !confirm(form.dataset.confirm)) { e.preventDefault(); return; }
    form.dataset.submitting = 'true';
    const button = e.submitter;
    if (button) { button.dataset.label = button.textContent; button.textContent = 'Memproses…'; button.setAttribute('aria-busy', 'true'); button.disabled = true; }
});
window.addEventListener('pageshow', () => document.querySelectorAll('form[data-submitting]').forEach(form => delete form.dataset.submitting));
window.addEventListener('pageshow', () => document.querySelectorAll('[aria-busy=true]').forEach(button => { button.textContent = button.dataset.label; button.disabled = false; button.removeAttribute('aria-busy'); }));

