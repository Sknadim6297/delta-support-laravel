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

    const sidebar = document.getElementById('dsSidebar');
    const backdrop = document.querySelector('.ds-sidebar-backdrop');
    const toggle = document.querySelector('.header-toggle');
    const closeButton = sidebar ? sidebar.querySelector('.ds-sidebar-close') : null;

    function setDrawer(open) {
        if (!sidebar) {
            return;
        }

        sidebar.classList.toggle('is-open', open);
        if (backdrop) {
            backdrop.hidden = !open;
        }
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        document.body.classList.toggle('ds-nav-open', open);
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (window.innerWidth >= 1280) {
                return;
            }
            setDrawer(!sidebar.classList.contains('is-open'));
        });
    }

    if (closeButton) {
        closeButton.addEventListener('click', function () {
            setDrawer(false);
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setDrawer(false);
        });
    }

    if (sidebar) {
        sidebar.querySelectorAll('.ds-menu-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                const group = button.closest('.ds-group');
                const submenu = document.getElementById(button.getAttribute('aria-controls'));
                const open = button.getAttribute('aria-expanded') === 'true';
                button.setAttribute('aria-expanded', open ? 'false' : 'true');
                if (group) {
                    group.classList.toggle('is-open', !open);
                }
                if (submenu) {
                    submenu.hidden = open;
                }
            });
        });

        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 1280) {
                    setDrawer(false);
                }
            });
        });
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 1280) {
            setDrawer(false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar && sidebar.classList.contains('is-open')) {
            setDrawer(false);
            if (toggle) {
                toggle.focus();
            }
        }
    });

    const loader = document.querySelector('.loader-wrapper');
    if (loader) {
        loader.remove();
    }

    const goTop = document.querySelector('.go-top');
    if (goTop) {
        window.addEventListener('scroll', function () {
            goTop.style.display = window.scrollY > 100 ? 'grid' : 'none';
        });
        goTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    const menuSearch = document.querySelector('[data-menu-search]');
    if (menuSearch && sidebar) {
        menuSearch.addEventListener('input', function () {
            const query = menuSearch.value.trim().toLowerCase();
            sidebar.querySelectorAll('.ds-menu > li').forEach(function (item) {
                const text = item.textContent.toLowerCase();
                item.hidden = query !== '' && !text.includes(query);
            });
        });
    }
});
