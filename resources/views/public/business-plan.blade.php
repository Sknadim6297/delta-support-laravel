@extends('public.layout')

@section('title', 'Business Plan | Delta Support')

@section('meta')
    <meta name="description" content="Discover destinations, travel packages and business opportunities with Delta Support.">
@endsection

@section('content')


    <!-- BUSINESS PLAN BANNER -->
    <section class="business-banner">
      <div class="container">
        <span class="banner-tag">
          <i class="bi bi-briefcase me-2"></i> BUSINESS PLAN
        </span>
        <h1>Explore Our <span>Business Opportunities</span></h1>
        <p>
          Discover our business activities, services and opportunities with
          Delta Support.
        </p>
      </div>
    </section>

    <!-- BUSINESS IMAGES -->
    <section class="business-gallery py-12">
      <div class="container">
        <div class="mb-12 text-center">
          <span class="section-tag">OUR BUSINESS</span>
          <h2 class="section-title">Explore Our Business Areas</h2>
          <p class="section-desc">
            Discover different services and business activities.
          </p>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <!-- IMAGE 1 -->
          <div>
            <div class="business-image-card">
              <img
                src="img/business-1.jpg"
                alt="Travel and tourism"
                loading="lazy"
                onerror="this.remove()"
              />
              <div class="business-image-content">
                <i class="bi bi-airplane-engines"></i>
                <h5>Travel & Tourism</h5>
                <p>Explore travel experiences and destinations.</p>
              </div>
            </div>
          </div>

          <!-- IMAGE 2 -->
          <div>
            <div class="business-image-card">
              <img
                src="img/business-2.jpg"
                alt="Hotel management"
                loading="lazy"
                onerror="this.remove()"
              />
              <div class="business-image-content">
                <i class="bi bi-building"></i>
                <h5>Hotel Management</h5>
                <p>Discover hospitality and accommodation services.</p>
              </div>
            </div>
          </div>

          <!-- IMAGE 3 -->
          <div>
            <div class="business-image-card">
              <img
                src="img/business-3.jpg"
                alt="Transport services"
                loading="lazy"
                onerror="this.remove()"
              />
              <div class="business-image-content">
                <i class="bi bi-car-front"></i>
                <h5>Transport Services</h5>
                <p>Explore transportation and mobility services.</p>
              </div>
            </div>
          </div>

          <!-- IMAGE 4 -->
          <div>
            <div class="business-image-card">
              <img
                src="img/business-4.jpg"
                alt="Business and technology"
                loading="lazy"
                onerror="this.remove()"
              />
              <div class="business-image-content">
                <i class="bi bi-graph-up-arrow"></i>
                <h5>Business Services</h5>
                <p>Learn about business activities and services.</p>
              </div>
            </div>
          </div>

          <!-- IMAGE 5 -->
          <div>
            <div class="business-image-card">
              <img
                src="img/business-5.jpg"
                alt="Travel destinations"
                loading="lazy"
                onerror="this.remove()"
              />
              <div class="business-image-content">
                <i class="bi bi-globe-asia-australia"></i>
                <h5>Global Destinations</h5>
                <p>Discover places and travel experiences.</p>
              </div>
            </div>
          </div>

          <!-- IMAGE 6 -->
          <div>
            <div class="business-image-card">
              <img
                src="img/business-6.jpg"
                alt="Customer support"
                loading="lazy"
                onerror="this.remove()"
              />
              <div class="business-image-content">
                <i class="bi bi-headset"></i>
                <h5>Customer Support</h5>
                <p>Get assistance and information about our services.</p>
              </div>
            </div>
          </div>

          <!-- IMAGE 7 -->
          <div>
            <div class="business-image-card">
              <img
                src="img/business-7.jpg"
                alt="Business networking"
                loading="lazy"
                onerror="this.remove()"
              />
              <div class="business-image-content">
                <i class="bi bi-people"></i>
                <h5>Business Network</h5>
                <p>Learn about collaboration and connections.</p>
              </div>
            </div>
          </div>

          <!-- IMAGE 8 -->
          <div>
            <div class="business-image-card">
              <img
                src="img/business-8.jpg"
                alt="Business opportunities"
                loading="lazy"
                onerror="this.remove()"
              />
              <div class="business-image-content">
                <i class="bi bi-lightbulb"></i>
                <h5>New Opportunities</h5>
                <p>Explore our business information and services.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <!-- FOOTER -->
    @endsection
