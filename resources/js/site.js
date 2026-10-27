document.addEventListener('DOMContentLoaded', () => {
    const button = document.querySelector('[data-nav-toggle]');
    const panel = document.getElementById('mainNav');

    if (!button || !panel) {
        return;
    }

    button.addEventListener('click', () => {
        const open = panel.classList.toggle('is-open');
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
});
