<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Delta Support Admin')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    @vite(['resources/css/panel.css', 'resources/js/panel.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    @include('admin.partials.sidebar')
    <div class="main-wrapper">
        @include('admin.partials.topbar')

        <main class="dashboard-content">
            @if (session('success'))
                <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
            @endif
            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('assets/js/script.js') }}"></script>
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
