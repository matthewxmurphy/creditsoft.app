// Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
// Product/support https://builtnotbegged.com; recorded policy identity Matthew X Murphy.
// SPDX-License-Identifier: GPL-2.0-or-later. CreditSoft child 2026.10.1 UNRELEASED.
document.querySelectorAll('[data-creditsoft-header]').forEach(header => {
    const button = header.querySelector('.creditsoft-menu-toggle');
    const panel = header.querySelector('.creditsoft-primary-panel');
    if (!button || !panel) return;
    const mobile = window.matchMedia('(max-width: 980px)');
    const setOpen = opened => {
        header.classList.toggle('is-menu-open', opened);
        button.setAttribute('aria-expanded', String(opened));
    };
    const close = focus => {
        setOpen(false);
        if (focus) button.focus();
    };
    button.addEventListener('click', () => setOpen(button.getAttribute('aria-expanded') !== 'true'));
    header.addEventListener('keydown', event => { if (event.key === 'Escape' && header.classList.contains('is-menu-open')) close(true); });
    document.addEventListener('click', event => { if (!header.contains(event.target)) close(false); });
    mobile.addEventListener('change', event => { if (!event.matches) close(false); });
    // The fallback menu may already have keyboard focus if this script arrived late.
    if (mobile.matches && panel.contains(document.activeElement)) setOpen(true);
    header.classList.add('is-menu-enhanced');
});
