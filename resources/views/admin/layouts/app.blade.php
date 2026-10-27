<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Delta Support Admin')</title>
    <link rel="icon" href="{{ asset('site/img/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/css/panel.css', 'resources/js/panel.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="app-wrapper">
        <div class="loader-wrapper">
            <div class="loader_24"></div>
        </div>

        @include('admin.partials.sidebar')
        <div class="ds-sidebar-backdrop" hidden></div>

        <div class="app-content">
            @include('admin.partials.topbar')

            <main class="dashboard-content">
                @if (session('success'))
                    <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
                @endif
                @yield('content')
            </main>

            <footer>
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-9 col-12">
                            <p class="footer-text f-w-600 mb-0">Copyright © {{ date('Y') }} Delta Support</p>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <div class="go-top">
        <span class="progress-value">
            <i class="ph-bold ph-caret-up"></i>
        </span>
    </div>

    <script src="{{ asset('main/assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
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
