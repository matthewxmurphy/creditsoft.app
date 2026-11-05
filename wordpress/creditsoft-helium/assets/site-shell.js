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
    const updateViewport = () => {
        const isMobile = mobile.matches;
        setOpen(isMobile && panel.contains(document.activeElement));
        // Decide whether to keep focus before the mobile CSS can hide the panel.
        header.classList.toggle('is-menu-mobile', isMobile);
    };
    mobile.addEventListener('change', updateViewport);
    updateViewport();
    header.classList.add('is-menu-enhanced');
});
