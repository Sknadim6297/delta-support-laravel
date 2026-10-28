document.addEventListener('DOMContentLoaded', function () {
    function copyText(text) {
        function legacyCopy() {
            return new Promise(function (resolve, reject) {
                const area = document.createElement('textarea');
                area.value = text;
                area.setAttribute('readonly', '');
                area.style.position = 'fixed';
                area.style.top = '0';
                area.style.left = '0';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.focus();
                area.select();
                area.setSelectionRange(0, area.value.length);

                try {
                    if (document.execCommand('copy')) {
                        resolve();
                    } else {
                        reject(new Error('Copy failed'));
                    }
                } catch (error) {
                    reject(error);
                } finally {
                    area.remove();
                }
            });
        }

        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).catch(legacyCopy);
        }

        return legacyCopy();
    }

    function showCopyStatus(message) {
        const status = document.querySelector('[data-copy-status]');
        if (!status) {
            return;
        }

        status.hidden = false;
        status.textContent = message;
    }

    document.querySelectorAll('[data-copy-text]').forEach(function (button) {
        button.addEventListener('click', function () {
            const text = button.getAttribute('data-copy-text') || '';
            copyText(text).then(function () {
                showCopyStatus('Referral link copied.');
            }).catch(function () {
                const link = document.getElementById('referral-link');
                if (link) {
                    const selection = window.getSelection();
                    const range = document.createRange();
                    range.selectNodeContents(link);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
                showCopyStatus('The referral link is selected. Press Ctrl+C to copy it.');
            });
        });
    });

    document.querySelectorAll('[data-share-url]').forEach(function (button) {
        button.addEventListener('click', function () {
            const url = button.getAttribute('data-share-url') || '';
            const payload = {
                title: button.getAttribute('data-share-title') || 'Referral link',
                text: button.getAttribute('data-share-text') || url,
                url: url,
            };

            if (navigator.share) {
                navigator.share(payload).catch(function (error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }

                    copyText(url).then(function () {
                        showCopyStatus('The referral link was copied so you can share it.');
                    }).catch(function () {
                        showCopyStatus('Sharing is unavailable in this browser.');
                    });
                });
                return;
            }

            copyText(url).then(function () {
                showCopyStatus('Sharing is not available in this browser. The referral link was copied instead.');
            }).catch(function () {
                showCopyStatus('Sharing is unavailable in this browser.');
            });
        });
    });

    document.querySelectorAll('.wrapper [data-toggle-password]').forEach(function (button) {
        button.remove();
    });

    document.querySelectorAll('.wrapper input[type="password"]').forEach(function (input) {
        if (input.closest('.ds-password')) {
            return;
        }

        const wrap = document.createElement('span');
        wrap.className = 'ds-password';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'ds-password-toggle';
        toggle.setAttribute('aria-label', 'Show password');
        toggle.innerHTML = '<i class="ri-eye-line" aria-hidden="true"></i>';
        wrap.appendChild(toggle);

        toggle.addEventListener('click', function () {
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            const icon = toggle.querySelector('i');
            if (icon) {
                icon.className = showing ? 'ri-eye-line' : 'ri-eye-off-line';
            }
            input.focus();
        });
    });

    document.querySelectorAll('[data-error-for]').forEach(function (error) {
        if (error.matches('input, select, textarea, button')) {
            return;
        }
        error.hidden = error.textContent.trim() === '';
    });

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
        sidebar.hidden = window.innerWidth < 1280 && !open;
        if (backdrop) {
            backdrop.hidden = !open;
        }
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        if (open && closeButton && window.innerWidth < 1280) {
            closeButton.focus();
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
        if (window.innerWidth < 1280) {
            sidebar.hidden = !sidebar.classList.contains('is-open');
        }

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
        if (!sidebar) {
            return;
        }
        if (window.innerWidth >= 1280) {
            setDrawer(false);
        } else if (!sidebar.classList.contains('is-open')) {
            sidebar.hidden = true;
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
        goTop.hidden = true;
        window.addEventListener('scroll', function () {
            goTop.hidden = window.scrollY <= 100;
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

    const headerTitle = document.querySelector('.ds-lux-title, .ds-header-title');
    const pageHeading = document.querySelector('.ds-content h1, .ds-content h4');
    if (pageHeading) {
        pageHeading.classList.add('ds-page-title');
        const heading = pageHeading.textContent.trim();
        if (headerTitle && ['Admin', 'Member', 'Panel'].includes(headerTitle.textContent.trim())) {
            headerTitle.textContent = heading;
        }
        const crumb = document.querySelector('.lh-breadcrumb ul li:last-child');
        if (crumb && ['Admin', 'Member', 'Panel'].includes(crumb.textContent.trim())) {
            crumb.textContent = heading;
        }
    }

    document.querySelectorAll('.ds-content td span, .ds-content dd span').forEach(function (badge) {
        const value = badge.textContent.trim().toLowerCase();
        if (value.includes('inactive') || value.includes('unpaid') || value.includes('deactivat')) {
            badge.classList.add('status-inactive');
        } else if (value.includes('pending') || value.includes('waiting')) {
            badge.classList.add('status-pending');
        } else if (value.includes('reject') || value.includes('failed')) {
            badge.classList.add('status-danger');
        } else if (value.includes('active') || value.includes('approv') || value.includes('paid') || value.includes('success')) {
            badge.classList.add('status-active');
        }
    });

    initializeGenealogyWorkspace();
});

function initializeGenealogyWorkspace() {
    const workspace = document.querySelector('[data-genealogy]');
    if (!workspace || workspace.dataset.controlsBound === 'true') {
        return;
    }

    workspace.dataset.controlsBound = 'true';

    const canvas = workspace.querySelector('#treeContainer');
    const stage = workspace.querySelector('[data-tree-stage]');
    const tree = workspace.querySelector('.member-tree');
    const zoomLabel = workspace.querySelector('[data-tree-zoom]');
    const dialog = document.getElementById('treeDetailDialog');
    const searchForm = workspace.querySelector('[data-genealogy-search]');
    const minScale = 0.4;
    const maxScale = 1.6;
    const step = 0.1;
    let scale = 1;

    if (searchForm) {
        searchForm.addEventListener('submit', function () {
            const input = searchForm.querySelector('input[name="member_id"]');
            if (input) {
                input.value = input.value.trim();
            }
        });
    }

    workspace.addEventListener('click', function (event) {
        const detail = event.target.closest('[data-tree-detail]');
        if (!detail || !dialog || dialog.open) {
            return;
        }

        ['name', 'member', 'status'].forEach(function (key) {
            const target = dialog.querySelector('[data-detail="' + key + '"]');
            if (target) {
                target.textContent = detail.dataset[key] || '';
            }
        });

        const count = dialog.querySelector('[data-detail="count"]');
        if (count) {
            const value = Number(detail.dataset.count || 0);
            count.textContent = value === 1 ? '1 direct referral' : value + ' direct referrals';
        }

        const profile = dialog.querySelector('[data-detail="profile"]');
        if (profile) {
            if (detail.dataset.profile) {
                profile.href = detail.dataset.profile;
                profile.hidden = false;
            } else {
                profile.hidden = true;
                profile.removeAttribute('href');
            }
        }

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    });

    if (!tree || !canvas || !stage) {
        workspace.querySelectorAll('[data-tree-action]').forEach(function (button) {
            button.disabled = true;
        });
        return;
    }

    const scaler = workspace.querySelector('[data-tree-scaler]');

    function applyScale() {
        scale = Math.round(scale * 100) / 100;
        if (!scaler) {
            return;
        }

        scaler.style.transform = 'none';
        scaler.style.marginLeft = '0px';
        scaler.style.marginRight = '0px';
        scaler.style.marginBottom = '0px';
        const width = scaler.offsetWidth;
        const height = scaler.offsetHeight;
        scaler.style.transformOrigin = 'top center';
        scaler.style.transform = 'scale(' + scale + ')';
        const extraWidth = (scale - 1) * width;
        scaler.style.marginLeft = (extraWidth / 2) + 'px';
        scaler.style.marginRight = (extraWidth / 2) + 'px';
        scaler.style.marginBottom = ((scale - 1) * height) + 'px';
        tree.dataset.treeScale = String(scale);
        scaler.dataset.treeScale = String(scale);
        if (zoomLabel) {
            zoomLabel.textContent = Math.round(scale * 100) + '%';
        }
        workspace.querySelectorAll('[data-tree-action="zoom-in"]').forEach(function (button) {
            button.disabled = scale >= maxScale - 0.001;
        });
        workspace.querySelectorAll('[data-tree-action="zoom-out"]').forEach(function (button) {
            button.disabled = scale <= minScale + 0.001;
        });
    }

    function loadLazy(node) {
        if (!node) {
            return Promise.resolve();
        }
        const list = node.querySelector(':scope > .tree-children[data-tree-lazy]');
        const endpoint = workspace.dataset.treeChildren;
        if (!list || !endpoint || list.dataset.treeLoaded === 'true' || list.dataset.treeLoaded === 'pending') {
            return Promise.resolve();
        }
        list.dataset.treeLoaded = 'pending';
        const url = endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + 'member_id=' + encodeURIComponent(list.dataset.treeLazy || '');
        return fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Unable to load this branch.');
            }
            return response.json();
        }).then(function (data) {
            list.innerHTML = data.html || '';
            list.dataset.treeLoaded = 'true';
            list.removeAttribute('data-tree-lazy');
        }).catch(function () {
            list.dataset.treeLoaded = '';
        });
    }

    function setExpanded(node, expanded) {
        if (!node) {
            return;
        }
        const children = node.querySelector(':scope > .tree-children');
        const toggle = node.querySelector(':scope > .tree-member [data-tree-toggle]');
        if (!children || !toggle) {
            return;
        }
        children.hidden = !expanded;
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    function allNodes() {
        return tree.querySelectorAll('[data-member-node]');
    }

    function expandAll(expanded) {
        if (!expanded) {
            allNodes().forEach(function (node) {
                setExpanded(node, false);
            });
            return;
        }
        const loadNext = function (guard) {
            const pending = Array.prototype.map.call(tree.querySelectorAll('[data-tree-lazy]'), function (list) {
                return loadLazy(list.closest('[data-member-node]'));
            });
            if (pending.length === 0 || guard > 32) {
                allNodes().forEach(function (node) {
                    setExpanded(node, true);
                });
                return;
            }
            Promise.all(pending).then(function () {
                loadNext(guard + 1);
            });
        };
        loadNext(0);
    }

    function centerTree() {
        canvas.scrollTop = 0;
        canvas.scrollLeft = Math.max(0, (canvas.scrollWidth - canvas.clientWidth) / 2);
    }

    function fitTree() {
        scaler.style.transform = 'none';
        scaler.style.marginLeft = '0px';
        scaler.style.marginRight = '0px';
        scaler.style.marginBottom = '0px';
        const naturalWidth = Math.max(scaler.offsetWidth, 1);
        const naturalHeight = Math.max(scaler.offsetHeight, 1);
        const styles = window.getComputedStyle(stage);
        const padX = parseFloat(styles.paddingLeft) + parseFloat(styles.paddingRight);
        const padY = parseFloat(styles.paddingTop) + parseFloat(styles.paddingBottom);
        const availableWidth = Math.max(80, canvas.clientWidth - padX);
        const availableHeight = Math.max(80, canvas.clientHeight - padY);
        const next = Math.min(availableWidth / naturalWidth, availableHeight / naturalHeight, 1);
        scale = Math.max(0.2, Math.round(next * 100) / 100);
        applyScale();
        canvas.scrollLeft = 0;
        canvas.scrollTop = 0;
    }

    tree.addEventListener('click', function (event) {
        const toggle = event.target.closest('[data-tree-toggle]');
        if (!toggle) {
            return;
        }
        event.preventDefault();
        const node = toggle.closest('[data-member-node]');
        const expanded = toggle.getAttribute('aria-expanded') !== 'true';
        const ready = expanded ? loadLazy(node) : Promise.resolve();
        ready.then(function () {
            setExpanded(node, expanded);
        });
    });

    workspace.querySelectorAll('[data-tree-action]').forEach(function (button) {
        button.addEventListener('click', function () {
            const action = button.dataset.treeAction;
            if (action === 'expand') {
                expandAll(true);
            } else if (action === 'collapse') {
                expandAll(false);
            } else if (action === 'zoom-in' && scale < maxScale) {
                scale = Math.min(maxScale, Math.round((scale + step) * 100) / 100);
                applyScale();
            } else if (action === 'zoom-out' && scale > minScale) {
                scale = Math.max(minScale, Math.round((scale - step) * 100) / 100);
                applyScale();
            } else if (action === 'fit') {
                fitTree();
            } else if (action === 'reset') {
                scale = 1;
                applyScale();
                centerTree();
            }
        });
    });

    allNodes().forEach(function (node, index) {
        setExpanded(node, index === 0);
    });
    applyScale();
}
