document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-menu]').forEach(function (menu) {
        const button = menu.querySelector('[data-menu-button]');
        const panel = menu.querySelector('[data-menu-panel]');

        if (!button || !panel) {
            return;
        }

        function closeMenu() {
            panel.hidden = true;
            button.setAttribute('aria-expanded', 'false');
        }

        function openMenu() {
            panel.hidden = false;
            button.setAttribute('aria-expanded', 'true');
        }

        button.addEventListener('click', function (event) {
            event.stopPropagation();
            if (panel.hidden) {
                openMenu();
            } else {
                closeMenu();
            }
        });

        document.addEventListener('click', function (event) {
            if (!menu.contains(event.target)) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMenu();
                if (document.activeElement !== button && menu.contains(document.activeElement)) {
                    button.focus();
                }
            }
        });
    });
});
