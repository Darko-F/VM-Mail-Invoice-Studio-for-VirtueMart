/** @license GNU General Public License version 2 or later; Copyright (C) 2026 ToPowerYou */
'use strict';
document.querySelectorAll('.vms-preview-button').forEach(button => {
    button.addEventListener('click', () => {
        const frame = document.getElementById('vms-preview');
        if (frame) frame.src = button.dataset.preview;
    });
});
const form = document.getElementById('adminForm');
if (form) form.addEventListener('change', () => form.querySelector('.vms-save')?.classList.add('vms-dirty'));
