document.addEventListener('DOMContentLoaded', function () {
    const wrapper = document.querySelector('main.wrapper');
    const toggle = document.querySelector('.lh-toggle-sidebar');
    const overlay = document.querySelector('.lh-sidebar-overlay');

    if (toggle && wrapper) {
        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            const collapsed = wrapper.classList.contains('sb-collapse');
            wrapper.classList.toggle('sb-collapse', !collapsed);
            wrapper.classList.toggle('sb-default', collapsed);
            if (overlay) {
                overlay.style.display = window.innerWidth < 992 && !collapsed ? 'block' : 'none';
            }
        });
    }

    if (overlay && wrapper) {
        overlay.addEventListener('click', function () {
            wrapper.classList.add('sb-default');
            wrapper.classList.remove('sb-collapse');
            overlay.style.display = 'none';
        });
    }

    document.querySelectorAll('.lh-drop-toggle').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            const item = link.closest('.lh-sb-item');
            if (!item) {
                return;
            }
            const open = item.classList.contains('active') || item.classList.contains('load-active');
            item.classList.toggle('active', !open);
            item.classList.toggle('load-active', !open);
        });
    });

    document.querySelectorAll('[data-lux-drop]').forEach(function (drop) {
        const button = drop.querySelector('[data-lux-drop-button]');
        if (!button) {
            return;
        }
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            const open = drop.classList.contains('is-open');
            document.querySelectorAll('[data-lux-drop].is-open').forEach(function (other) {
                other.classList.remove('is-open');
                const otherButton = other.querySelector('[data-lux-drop-button]');
                if (otherButton) {
                    otherButton.setAttribute('aria-expanded', 'false');
                }
            });
            drop.classList.toggle('is-open', !open);
            button.setAttribute('aria-expanded', !open ? 'true' : 'false');
        });
    });

    document.addEventListener('click', function () {
        document.querySelectorAll('[data-lux-drop].is-open').forEach(function (drop) {
            drop.classList.remove('is-open');
        });
    });
});
