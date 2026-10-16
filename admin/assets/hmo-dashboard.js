'use strict';

/** Mobile navigation toggle; the shared sidebar owns its open-state styling. */
document.addEventListener('DOMContentLoaded', () => {
    const menuButton = document.querySelector('.mobile-menu');
    const sidebar = document.querySelector('.sidebar');

    if (!menuButton || !sidebar) {
        return;
    }

    menuButton.addEventListener('click', () => {
        const isOpen = sidebar.classList.toggle('mobile-open');
        menuButton.setAttribute('aria-expanded', String(isOpen));
    });
});
