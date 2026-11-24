<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Delta Support Member')</title>
    <link rel="icon" href="{{ \App\Support\BrandTokens::logoUrl() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    @vite(['resources/css/panel.css', 'resources/css/panel-v2.css', 'resources/js/panel.js', 'resources/js/panel-v2.js'])
    @include('partials.brand-tokens')
    @stack('styles')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body data-panel-theme="v2">
    <div class="pv-app" data-pv-shell>
        <div class="pv-overlay" data-pv-overlay></div>
        @include('layouts.panel.sidebar-member')
        <div class="pv-main">
            @include('layouts.panel.header', [
                'pvTitle' => trim($__env->yieldContent('page-title')) ?: 'Member',
                'pvHome' => route('member.dashboard'),
                'pvName' => isset($member) ? $member->member_name : 'Member',
                'pvMeta' => isset($member) ? $member->member_id : null,
                'pvProfile' => route('member.profile'),
                'pvProfileLabel' => 'My Profile',
                'pvPassword' => route('member.change-password'),
                'pvLogout' => route('member.logout'),
            ])
            <div class="pv-body">
                @if (\App\Support\MemberContext::isPreview() && isset($member))
                    @include('member.partials.impersonation-banner')
                @endif
                <div class="ds-content @yield('content-class')">
                    @if (session('success'))
                        <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
                    @endif
                    @yield('content')
                </div>
            </div>
            @include('layouts.panel.footer', ['pvAudience' => 'Member'])
        </div>
    </div>

    <script>
        (function () {
            const memberSearchRoute = '{{ route('member.search-members') }}';
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
