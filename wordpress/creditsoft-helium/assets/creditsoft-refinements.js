/* CreditSoft responsive controls. Copyright 2026 Matthew C. Murphy. GPL-2.0-or-later. */
(() => {
    'use strict';
    const init = () => {
        const banner = document.querySelector('[data-cookie-banner]');
        const copy = banner?.querySelector('.creditsoft-cookie-copy p');
        if (copy) {
            const privacy = copy.querySelector('a[href*="privacy"]');
            const cookies = copy.querySelector('a[href*="cookies"]');
            const parts = [document.createTextNode('We use essential, analytics and marketing cookies. ')];
            for (const [link, label, fullLabel] of [[privacy, 'Privacy', 'Privacy Policy'], [cookies, 'Cookies', 'Cookie Policy']]) {
                if (!link) continue;
                link.textContent = label;
                link.setAttribute('aria-label', fullLabel);
                parts.push(link, document.createTextNode(' '));
            }
            copy.replaceChildren(...parts);
            banner.setAttribute('role', 'region');
            banner.setAttribute('aria-label', 'Cookie preferences');
        }

        const chat = document.querySelector('[data-creditsoft-chat]');
        const toggle = chat?.querySelector('.creditsoft-chat-toggle');
        const panel = chat?.querySelector('.creditsoft-chat-panel');
        if (chat && toggle && panel) {
            const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            icon.setAttribute('viewBox', '0 0 24 24');
            icon.setAttribute('aria-hidden', 'true');
            icon.setAttribute('focusable', 'false');
            const path = document.createElementNS(icon.namespaceURI, 'path');
            path.setAttribute('d', 'M6 4.5h12a3 3 0 0 1 3 3v7a3 3 0 0 1-3 3h-7l-5 3v-3a3 3 0 0 1-3-3v-7a3 3 0 0 1 3-3Z');
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke', 'currentColor');
            path.setAttribute('stroke-width', '1.8');
            path.setAttribute('stroke-linejoin', 'round');
            icon.append(path);
            toggle.prepend(icon);
            toggle.querySelector('span').textContent = 'CreditSoft chat';
            const close = document.createElement('span');
            close.className = 'creditsoft-chat-close';
            close.setAttribute('aria-hidden', 'true');
            close.textContent = '×';
            toggle.append(close);
            panel.setAttribute('role', 'region');
            panel.setAttribute('aria-label', 'CreditSoft support chat');
            const input = panel.querySelector('input');
            input?.setAttribute('aria-label', 'Message CreditSoft');
            const log = panel.querySelector('[data-chat-log]');
            log?.setAttribute('role', 'log');
            log?.setAttribute('aria-live', 'polite');
            const sync = () => {
                const open = !panel.hidden;
                chat.classList.toggle('is-open', open);
                toggle.setAttribute('aria-label', open ? 'Close CreditSoft chat' : 'Open CreditSoft chat');
                toggle.setAttribute('title', open ? 'Close chat' : 'Chat with CreditSoft');
            };
            new MutationObserver(sync).observe(panel, { attributes: true, attributeFilter: ['hidden'] });
            sync();
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !panel.hidden) {
                    toggle.click();
                    toggle.focus({ preventScroll: true });
                }
            });
        }

        // CPH owns phone navigation, nesting, Back, close and focus. Its original
        // package opens at <=820px; reuse the same dialog for our 821–980px header.
        const trigger = document.querySelector('[data-cph-menu-open="creditsoft-mobile-navigation"]');
        const drawer = document.getElementById('creditsoft-mobile-navigation');
        if (trigger && drawer && typeof drawer.showModal === 'function') {
            let tabletOpen = false;
            let previousOverflow = '';
            trigger.addEventListener('click', () => {
                if (!matchMedia('(min-width: 821px) and (max-width: 980px)').matches) return;
                drawer.showModal();
                previousOverflow = document.documentElement.style.overflow;
                document.documentElement.style.overflow = 'hidden';
                tabletOpen = true;
                trigger.setAttribute('aria-expanded', 'true');
                drawer.querySelector('[data-cph-panel="root"] h2')?.focus();
            });
            drawer.addEventListener('close', () => {
                if (tabletOpen) {
                    document.documentElement.style.overflow = previousOverflow;
                    tabletOpen = false;
                }
                if (matchMedia('(max-width: 980px)').matches) trigger.focus({ preventScroll: true });
            });
            matchMedia('(min-width: 981px)').addEventListener('change', (event) => {
                if (event.matches && drawer.open) drawer.close();
            });
        }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
