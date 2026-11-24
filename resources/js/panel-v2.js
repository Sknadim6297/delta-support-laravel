/**
 * Panel v2 shell interactions — no Lux selectors.
 */
document.addEventListener('DOMContentLoaded', function () {
    const shell = document.querySelector('[data-pv-shell]');
    if (!shell) {
        return;
    }

    const storageKey = 'delta.panel.v2.collapsed';
    const toggle = shell.querySelector('[data-pv-sidebar-toggle]');
    const overlay = shell.querySelector('[data-pv-overlay]');
    const mq = window.matchMedia('(max-width: 960px)');

    function isMobile() {
        return mq.matches;
    }

    function setCollapsed(collapsed) {
        shell.classList.toggle('is-collapsed', collapsed);
        if (!isMobile()) {
            try {
                localStorage.setItem(storageKey, collapsed ? '1' : '0');
            } catch (e) {
                /* ignore */
            }
        }
    }

    function closeMobile() {
        shell.classList.remove('is-mobile-open');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
    }

    function openMobile() {
        shell.classList.add('is-mobile-open');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
        }
    }

    if (!isMobile()) {
        try {
            if (localStorage.getItem(storageKey) === '1') {
                setCollapsed(true);
            }
        } catch (e) {
            /* ignore */
        }
    }

    if (toggle) {
        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            if (isMobile()) {
                if (shell.classList.contains('is-mobile-open')) {
                    closeMobile();
                } else {
                    openMobile();
                }
                return;
            }
            setCollapsed(!shell.classList.contains('is-collapsed'));
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeMobile);
    }

    shell.querySelectorAll('[data-pv-nav-toggle]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            const item = button.closest('[data-pv-nav-item]');
            if (!item) {
                return;
            }
            const open = item.classList.contains('is-open');
            item.classList.toggle('is-open', !open);
            button.setAttribute('aria-expanded', open ? 'false' : 'true');
        });
    });

    function closeMenus(except) {
        shell.querySelectorAll('[data-pv-menu].is-open').forEach(function (menu) {
            if (except && menu === except) {
                return;
            }
            menu.classList.remove('is-open');
            const btn = menu.querySelector('[data-pv-menu-button]');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    shell.querySelectorAll('[data-pv-menu]').forEach(function (menu) {
        const button = menu.querySelector('[data-pv-menu-button]');
        if (!button) {
            return;
        }
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            const open = menu.classList.contains('is-open');
            closeMenus();
            if (!open) {
                menu.classList.add('is-open');
                button.setAttribute('aria-expanded', 'true');
            }
        });
    });

    document.addEventListener('click', function () {
        closeMenus();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMenus();
            closeMobile();
        }
    });

    mq.addEventListener('change', function () {
        closeMobile();
        if (isMobile()) {
            shell.classList.remove('is-collapsed');
        }
    });
});
