<div class="topbar">
    <div class="container flex flex-wrap items-center justify-between gap-2 py-2">
        <div><i class="bi bi-geo-alt me-1"></i> Your Journey, Our Support</div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('public.contact') }}"><i class="bi bi-envelope me-1"></i> Contact Us</a>
            <span><i class="bi bi-clock me-1"></i> Your Travel Partner</span>
        </div>
    </div>
</div>

<nav class="navbar sticky top-0 z-40">
    <div class="container flex flex-wrap items-center justify-between">
        <a class="navbar-brand flex items-center" href="{{ route('home') }}">
            <img src="{{ \App\Support\BrandTokens::logoUrl() }}" class="brand-logo" alt="Delta Support Logo" />
            <span class="brand-name">
                DELTA <span class="text-brand-500">SUPPORT</span>
            </span>
        </a>

        <button
            class="border-0 bg-transparent p-2 text-navy-900 lg:hidden"
            type="button"
            data-nav-toggle
            aria-controls="mainNav"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <i class="bi bi-list text-3xl"></i>
        </button>

        <div class="nav-panel basis-full lg:w-auto lg:basis-auto" id="mainNav">
            <ul class="flex flex-col lg:ms-auto lg:flex-row lg:items-center">
                <li>
                    <a class="nav-link @if(request()->routeIs('home')) active @endif" href="{{ route('home') }}">Home</a>
                </li>
                <li>
                    <a class="nav-link @if(request()->routeIs('public.about')) active @endif" href="{{ route('public.about') }}">About</a>
                </li>
                <li>
                    <a class="nav-link @if(request()->routeIs('public.packages')) active @endif" href="{{ route('public.packages') }}">Packages</a>
                </li>
                <li>
                    <a class="nav-link @if(request()->routeIs('public.business-plan')) active @endif" href="{{ route('public.business-plan') }}">Business Plan</a>
                </li>
                <li>
                    <a class="nav-link @if(request()->routeIs('public.contact')) active @endif" href="{{ route('public.contact') }}">Contact</a>
                </li>
                <li>
                    <a class="nav-link @if(request()->routeIs('member.register')) active @endif" href="{{ route('member.register') }}">Register</a>
                </li>
                <li>
                    <a class="nav-link @if(request()->routeIs('public.login', 'login')) active @endif" href="{{ route('public.login') }}">Login</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
