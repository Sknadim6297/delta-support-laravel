@extends('public.layout')

@section('title', 'Packages | Delta Support')

@section('meta')
    <meta name="description" content="Discover destinations, travel packages and business opportunities with Delta Support.">
@endsection

@section('content')


    <!-- BANNER -->
    <section class="page-banner">
      <div class="container">
        <div class="banner-content">
          <span class="banner-tag"
            ><i class="bi bi-suitcase-lg"></i> OUR PACKAGES</span
          >
          <h1>Explore Our <span>Packages</span></h1>
          <p>
            Discover package options featuring travel experiences at Digha, Goa
            and Gangtok. Review the available details and contact our team
            before making a decision.
          </p>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
              <li class="breadcrumb-item active" aria-current="page">
                Packages
              </li>
            </ol>
          </nav>
        </div>
      </div>
    </section>

    <!-- PACKAGE OPTIONS -->

<section class="packages-section">
  <div class="container">

    <div class="mx-auto mb-12 text-center" style="max-width:700px">
      <span class="section-tag">Choose Your Package</span>
      <h2 class="section-title">Find The Option That Suits You</h2>
      <p class="section-desc">
        Packages published by Delta Support are listed here with the USD price
        and tour details saved by the administrator.
      </p>
    </div>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      @forelse($packages as $package)
        <article class="destination-card">
          <div class="destination-image">
            @if($package->image_path)
              <img src="{{ route('public.packages.image', $package) }}" alt="{{ $package->name }}" loading="lazy">
            @else
              <div class="h-48 w-full bg-gradient-to-br from-[#092747] to-[#0878d1]"></div>
            @endif
          </div>
          <div class="destination-body">
            <span class="destination-category">{{ $package->typeLabel() }}</span>
            <h3>{{ $package->name }}</h3>
            <p class="package-price mt-2"><strong>USD {{ $package->price_usd }}</strong></p>
            @if($package->description)
              <p>{{ $package->description }}</p>
            @endif
            <p><strong>Destinations:</strong> {{ $package->destinations ?: 'Not set' }}</p>
            <p><strong>Duration:</strong> {{ $package->duration ?: 'Not set' }}</p>
            @if($package->benefits)
              <p>{{ $package->benefits }}</p>
            @endif
          </div>
        </article>
      @empty
        <p class="text-slate-600">No packages have been published yet.</p>
      @endforelse
    </div>

  </div>
</section>


    <!-- CTA -->
    <section class="cta-section">
      <div class="container">
        <div class="cta-box">
          <div class="cta-grid grid items-center gap-6 lg:grid-cols-12">
            <div class="lg:col-span-8">
              <span class="text-sm font-bold text-amber-400"
                >NEED MORE INFORMATION?</span
              >
              <h2>Let's Discuss Your Package</h2>
              <p>
                Contact our team to clarify travel schedules, package terms and
                the details you need before making a decision.
              </p>
            </div>
            <div class="lg:col-span-4 lg:text-right">
              <a href="{{ route('public.contact') }}" class="btn-orange">
                Contact Us <i class="bi bi-arrow-up-right"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- FOOTER -->
    @endsection
