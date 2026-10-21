import '@material/web/progress/circular-progress.js';
import katex from 'katex';
import 'katex/dist/katex.min.css';
import { mountSidebar } from './ui/sidebar';
import { mountButtonLoading } from './ui/button-loading';
import { mountCharts } from './ui/charts';

/* Render rumus. Dipanggil (1) saat halaman dimuat untuk elemen statis seperti halaman
   Perhitungan, dan (2) setelah isi <template> dipindah ke [data-modal-body], karena
   kueri dokumen tidak menembus isi <template>. */
function renderFormulas(root) {
    root.querySelectorAll('[data-katex]').forEach(element => {
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
}

renderFormulas(document);

mountSidebar();
mountButtonLoading();
mountCharts();

let dirtyForm = null;
document.querySelectorAll('[data-unsaved]').forEach(form => form.addEventListener('input', () => { dirtyForm = form; }));
window.addEventListener('beforeunload', e => { if (dirtyForm) { e.preventDefault(); e.returnValue = ''; } });
document.addEventListener('click', e => {
    if (dirtyForm && e.target.closest('a[href]')) {
        if (!confirm('Perubahan belum disimpan. Tinggalkan halaman?')) e.preventDefault();
        else dirtyForm = null;
    }
});

/* Modal: satu jalur buka untuk dua bentuk sumber. [data-modal-open] menunjuk <dialog>
   langsung (dialog form per baris) atau <template> (isi detail, diklon ke [data-modal-body]
   milik satu dialog bersama bertanda data-modal). */
document.addEventListener('click', event => {
    const opener = event.target.closest('[data-modal-open]');
    if (!opener) return;

    const source = document.getElementById(opener.dataset.modalOpen);
    if (!source) return;

    const dialog = source instanceof HTMLDialogElement
        ? source
        : document.querySelector('dialog[data-modal]');
    if (!dialog || dialog.open) return; // showModal() pada dialog yang sudah terbuka melempar InvalidStateError

    if (source instanceof HTMLTemplateElement) {
        const body = dialog.querySelector('[data-modal-body]');
        if (!body) return;
        body.replaceChildren(source.content.cloneNode(true));
        renderFormulas(body); // isi <template> baru terlihat kueri dokumen setelah dipindah ke sini
    }

    dialog._opener = opener; // dialog bersama tidak punya [data-modal-open="<id dialog>"] untuk fallback fokus
    dialog.showModal();
});
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
});
window.addEventListener('pageshow', () => document.querySelectorAll('form[data-submitting]').forEach(form => delete form.dataset.submitting));
