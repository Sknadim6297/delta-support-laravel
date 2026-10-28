<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Delta Support Admin')</title>
    <link rel="icon" href="{{ \App\Support\BrandTokens::logoUrl() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('lux/assets/css/vendor/bootstrap.min.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link href="{{ asset('lux/assets/css/style.css') }}" rel="stylesheet">
    @vite(['resources/css/panel.css', 'resources/css/lux-shell.css', 'resources/css/lux-pages.css', 'resources/js/panel.js', 'resources/js/lux-shell.js'])
    @include('partials.brand-tokens')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body data-lh-mode="light">
    <main class="wrapper sb-default">
        @include('partials.lux-bar', [
            'luxTitle' => trim($__env->yieldContent('page-title')) ?: 'Admin',
            'luxName' => auth()->user()->name ?? 'Admin',
            'luxMeta' => auth()->user()->email,
            'luxProfile' => route('admin.settings.profile'),
            'luxProfileLabel' => 'Admin Profile',
            'luxPassword' => route('admin.change-password.index'),
            'luxLogout' => route('admin.logout'),
        ])

        @include('admin.partials.sidebar')

        <div class="lh-main-content">
            <div class="container-fluid">
                <div class="lh-page-title">
                    <div class="lh-breadcrumb">
                        <h5>@yield('page-title', 'Admin')</h5>
                        <ul>
                            <li><a href="{{ route('dashboard') }}">Home</a></li>
                            <li>@yield('page-title', 'Admin')</li>
                        </ul>
                    </div>
                </div>
                <div class="ds-content @yield('content-class')">
                    @if (session('success'))
                        <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
                    @endif
                    @yield('content')
                </div>
            </div>
        </div>

        <footer>
            <div class="container-fluid">
                <div class="copyright">
                    <p>© {{ date('Y') }} Delta Support</p>
                    <p>Administrator</p>
                </div>
            </div>
        </footer>
    </main>

    <script>
        (function () {
            const memberSearchRoute = '{{ route('admin.genealogy.search-members') }}';
            const memberAutocompleteSelector = '[data-member-autocomplete]';

            function getOrCreateSuggestionList() {
                let list = document.getElementById('member-autocomplete-list');
                if (!list) {
                    list = document.createElement('datalist');
                    list.id = 'member-autocomplete-list';
                    document.body.appendChild(list);
                }
                return list;
            }

            function attachMemberAutocomplete(input) {
                if (!input || input.dataset.memberAutocompleteBound === 'true') {
                    return;
                }

                input.dataset.memberAutocompleteBound = 'true';
                let timer = null;

                input.addEventListener('input', function () {
                    clearTimeout(timer);
                    const query = (input.value || '').trim();
                    if (!query) {
                        getOrCreateSuggestionList().innerHTML = '';
                        return;
                    }

                    timer = setTimeout(function () {
                        fetch(memberSearchRoute + '?member_id=' + encodeURIComponent(query), {
                            headers: { Accept: 'application/json' }
                        })
                            .then(function (response) {
                                return response.json().then(function (data) {
                                    if (!response.ok) {
                                        throw new Error(data.message || 'Unable to load suggestions.');
                                    }
                                    return data;
                                });
                            })
                            .then(function (rows) {
                                const list = getOrCreateSuggestionList();
                                list.innerHTML = '';
                                if (!Array.isArray(rows)) {
                                    input.setAttribute('list', 'member-autocomplete-list');
                                    return;
                                }

                                rows.forEach(function (row) {
                                    const option = document.createElement('option');
                                    option.value = row.member_id;
                                    option.label = row.member_id + ' - ' + row.member_name + ' (' + (row.status || 'active') + ')';
                                    list.appendChild(option);
                                });

                                input.setAttribute('list', 'member-autocomplete-list');
                            })
                            .catch(function () {
                                getOrCreateSuggestionList().innerHTML = '';
                            });
                    }, 180);
                });

                input.addEventListener('change', function () {
                    if (input.value) {
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
            }

            document.querySelectorAll(memberAutocompleteSelector).forEach(function (input) {
                attachMemberAutocomplete(input);
            });
        })();
    </script>
    @yield('scripts')
</body>
</html>
