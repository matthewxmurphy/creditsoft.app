// Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
// Product/support https://builtnotbegged.com; recorded policy identity Matthew X Murphy.
// SPDX-License-Identifier: GPL-2.0-or-later. CreditSoft child 2026.10.1 UNRELEASED.
document.querySelectorAll('[data-creditsoft-header]').forEach(header => {
    const button = header.querySelector('.creditsoft-menu-toggle');
    const panel = header.querySelector('.creditsoft-primary-panel');
    if (!button || !panel) return;
    const close = focus => {
        header.classList.remove('is-menu-open'); button.setAttribute('aria-expanded', 'false');
        if (focus) button.focus();
    };
    button.addEventListener('click', () => {
        const opened = button.getAttribute('aria-expanded') !== 'true';
        header.classList.toggle('is-menu-open', opened); button.setAttribute('aria-expanded', String(opened));
    });
    header.addEventListener('keydown', event => { if (event.key === 'Escape' && header.classList.contains('is-menu-open')) close(true); });
    document.addEventListener('click', event => { if (!header.contains(event.target)) close(false); });
    window.matchMedia('(max-width: 980px)').addEventListener('change', event => { if (!event.matches) close(false); });
});
