import { wireTimezonePicker } from '../lib/tz-picker.js';

export default function init() {
    wireTimezonePicker('#gen-tz');
    wireImagePreviews();
}

/**
 * Live preview for the favicon + per-theme logo file inputs. Each input
 * carries `data-preview-target` (the id of its preview box) and an
 * optional `data-preview-class` for the <img> sizing. Without this the
 * preview only updated after the form was saved.
 */
function wireImagePreviews() {
    document.querySelectorAll('input[type="file"][data-preview-target]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            const box = document.getElementById(input.dataset.previewTarget);
            if (!file || !box || !file.type.startsWith('image/')) return;

            const url = URL.createObjectURL(file);
            let img = box.querySelector('img');
            if (!img) {
                box.innerHTML = '';
                img = document.createElement('img');
                img.className = input.dataset.previewClass || 'max-h-14 max-w-[140px] object-contain';
                box.appendChild(img);
            }
            const old = img.src;
            img.src = url;
            img.alt = 'preview';
            if (old && old.startsWith('blob:')) URL.revokeObjectURL(old);
        });
    });
}
