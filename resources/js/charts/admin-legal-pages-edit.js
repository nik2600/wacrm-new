/**
 * Legal-pages editor — add / remove / reorder sections + live HTML preview.
 * The form posts sections[i][n|title|body]; indices must stay sequential
 * after every mutation, so renumber() rewrites every input name in order.
 */
export default function initLegalPagesEdit() {
    const list = document.getElementById('sections-list');
    const tpl = document.getElementById('section-row-tpl');
    if (!list || !tpl || list.dataset.wired === '1') return;
    list.dataset.wired = '1';

    const renumber = () => {
        list.querySelectorAll('[data-section-row]').forEach((row, i) => {
            row.querySelectorAll('[name]').forEach((el) => {
                el.name = el.name.replace(/sections\[[^\]]*\]/, `sections[${i}]`);
            });
        });
    };

    const renderPreview = (row) => {
        const wrap = row.querySelector('[data-preview]');
        const body = row.querySelector('[data-body]');
        if (!wrap || wrap.classList.contains('hidden')) return;
        const target = wrap.querySelector('.prose-body');
        if (target) target.innerHTML = body ? body.value : '';
    };

    const addRow = () => {
        const frag = tpl.content.cloneNode(true);
        list.appendChild(frag);
        renumber();
        const rows = list.querySelectorAll('[data-section-row]');
        const last = rows[rows.length - 1];
        last?.querySelector('[data-title]')?.focus();
    };

    // Add-section buttons (there may be more than one trigger).
    document.querySelectorAll('[data-add-section]').forEach((btn) =>
        btn.addEventListener('click', addRow)
    );

    // Delegated handling for per-row controls.
    list.addEventListener('click', (e) => {
        const row = e.target.closest('[data-section-row]');
        if (!row) return;

        if (e.target.closest('[data-remove]')) {
            row.remove();
            renumber();
            return;
        }
        if (e.target.closest('[data-move-up]')) {
            const prev = row.previousElementSibling;
            if (prev) row.parentNode.insertBefore(row, prev);
            renumber();
            return;
        }
        if (e.target.closest('[data-move-down]')) {
            const next = row.nextElementSibling;
            if (next) row.parentNode.insertBefore(next, row);
            renumber();
            return;
        }
        if (e.target.closest('[data-preview-toggle]')) {
            const wrap = row.querySelector('[data-preview]');
            const btn = e.target.closest('[data-preview-toggle]');
            if (wrap) {
                wrap.classList.toggle('hidden');
                const open = !wrap.classList.contains('hidden');
                btn.textContent = open ? 'Hide preview' : 'Preview';
                if (open) renderPreview(row);
            }
        }
    });

    // Keep an open preview in sync while typing.
    list.addEventListener('input', (e) => {
        if (e.target.matches('[data-body]')) {
            renderPreview(e.target.closest('[data-section-row]'));
        }
    });

    renumber();
}
