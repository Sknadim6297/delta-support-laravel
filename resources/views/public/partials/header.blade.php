<!-- TOPBAR -->
    <div class="topbar">
      <div
        class="container d-flex justify-content-between align-items-center flex-wrap gap-2 py-2"
      >
        <div><i class="bi bi-geo-alt me-1"></i> Your Journey, Our Support</div>
        <div class="d-flex gap-3 flex-wrap">
          <a href="{{ route('public.contact') }}"><i class="bi bi-envelope me-1"></i> Contact Us</a>
          <span><i class="bi bi-clock me-1"></i> Your Travel Partner</span>
        </div>
      </div>
    </div>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg sticky-top">
      <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="{{ route('home') }}">
          <img src="{{ asset('site/img/logo.png') }}" class="brand-logo" alt="Delta Support Logo" />
          <span class="brand-name">
            DELTA <span class="text-primary">SUPPORT</span>
          </span>
        </a>

        <button
          class="navbar-toggler border-0 shadow-none"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#mainNav"
          aria-controls="mainNav"
          aria-expanded="false"
          aria-label="Toggle navigation"
        >
          <i class="bi bi-list fs-2"></i>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
          <ul class="navbar-nav ms-auto align-items-lg-center">
            <li class="nav-item">
              <a class="nav-link @if(request()->routeIs('home')) active @endif" href="{{ route('home') }}">Home</a>
            </li>
            <li class="nav-item">
              <a class="nav-link @if(request()->routeIs('public.about')) active @endif" href="{{ route('public.about') }}">About</a>
            </li>
            <li class="nav-item">
              <a class="nav-link @if(request()->routeIs('public.packages')) active @endif" href="{{ route('public.packages') }}">Packages</a>
            </li>
            <li class="nav-item">
              <a class="nav-link @if(request()->routeIs('public.business-plan')) active @endif" href="{{ route('public.business-plan') }}">Business Plan</a>
            </li>
            <li class="nav-item">
              <a class="nav-link @if(request()->routeIs('public.contact')) active @endif" href="{{ route('public.contact') }}">Contact</a>
            </li>

            <li class="nav-item">
              <a class="nav-link @if(request()->routeIs('member.register')) active @endif" href="{{ route('member.register') }}">Register</a>
            </li>

            <li class="nav-item">
              <a class="nav-link @if(request()->routeIs('public.login', 'login')) active @endif" href="{{ route('public.login') }}">Login</a>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    