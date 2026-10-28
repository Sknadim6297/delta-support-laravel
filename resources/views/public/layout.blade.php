<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Delta Support')</title>
    @yield('meta')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/site.css', 'resources/js/site.js'])
    @stack('styles')
    @include('partials.brand-tokens')
</head>
<body>
    @include('public.partials.header')
    @yield('content')
    @include('public.partials.footer')
    <script>
        const year = document.getElementById('year');
        if (year) year.textContent = new Date().getFullYear();
    </script>
    @stack('scripts')
</body>
</html>