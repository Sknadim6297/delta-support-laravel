@extends('public.layout')

@section('title', 'Delta Support | Explore Your World')

@section('meta')
    <meta name="description" content="Discover destinations, travel packages and business opportunities with Delta Support.">
@endsection

@section('content')


    <!-- HERO -->
    <section class="hero">
      <div class="container">
        <div class="hero-content">
          <span class="eyebrow"
            ><i class="bi bi-globe-americas me-2"></i> Discover More, Experience
            More</span
          >
          <h1>Explore The World.<br />Make <span>Memories.</span></h1>
          <p>
            Discover amazing destinations, explore exciting travel experiences
            and find new opportunities with Delta Support. Your next journey
            starts here.
          </p>
          <div class="hero-actions">
            <a href="{{ route('public.packages') }}" class="btn-main"
              >Explore Packages <i class="bi bi-arrow-up-right ms-2"></i
            ></a>
            <a href="{{ route('public.about') }}" class="btn-outline-white"
              >Discover Delta Support</a
            >
          </div>
          <div class="hero-stats">
            <div><strong>Explore</strong><span>New Destinations</span></div>
            <div><strong>Discover</strong><span>Unique Experiences</span></div>
            <div><strong>Connect</strong><span>New Opportunities</span></div>
          </div>
        </div>
      </div>
    </section>

    <!-- ABOUT -->
    <section class="section-space">
      <div class="container">
        <div class="row g-5 align-items-center">
          <div class="col-lg-6">
            <div class="about-image">
              <div class="image-note">
                <h5 class="fw-bold mb-1">Discover Something New</h5>
                <p class="small mb-0">
                  Every destination has a story waiting to be explored.
                </p>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <span class="section-tag">About Delta Support</span>
            <h2 class="section-title">Your Journey Begins With Us</h2>
            <p class="section-desc">
              Delta Support is focused on helping people explore destinations,
              discover travel experiences and learn about available services and
              business opportunities.
            </p>
            <a
              href="{{ route('public.about') }}"
              class="btn-main mt-3 d-inline-flex align-items-center gap-2"
            >
              Read More
              <i class="bi bi-arrow-right"></i>
            </a>

            <div class="feature">
              <div class="feature-icon"><i class="bi bi-compass"></i></div>
              <div>
                <h6>Explore New Destinations</h6>
                <p>
                  Discover destinations, places and experiences for your next
                  adventure.
                </p>
              </div>
            </div>
            <div class="feature">
              <div class="feature-icon"><i class="bi bi-headset"></i></div>
              <div>
                <h6>Dedicated Support</h6>
                <p>
                  Get in touch with our team for information and assistance.
                </p>
              </div>
            </div>
            <div class="feature">
              <div class="feature-icon"><i class="bi bi-stars"></i></div>
              <div>
                <h6>New Experiences</h6>
                <p>
                  Find inspiration for holidays, adventures and memorable
                  journeys.
                </p>
              </div>
            </div>
            <a href="{{ route('public.contact') }}" class="btn-main mt-2"
              >Know More <i class="bi bi-arrow-right ms-2"></i
            ></a>
          </div>
        </div>
      </div>
    </section>

    <!-- PACKAGES -->
    <section class="section-space packages">
      <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width: 650px">
          <span class="section-tag">Explore Our World</span>
          <h2 class="section-title">Discover Our Packages</h2>
          <p class="section-desc">
            Find inspiration for your next getaway, from peaceful beaches to
            mountain adventures and city escapes.
          </p>
        </div>

        <div class="row g-4">
          <div class="col-md-6 col-lg-4">
            <div class="package-card">
              <img
                class="package-img"
                loading="lazy"
                src="https://images.unsplash.com/photo-1519046904884-53103b34b206?auto=format&fit=crop&w=800&q=80"
                alt="Tropical beach destination"
              />
              <div class="package-body">
                <div class="package-meta">
                  <i class="bi bi-sun"></i> Beach & Relaxation
                </div>
                <h4>Beach Getaways</h4>
                <p>
                  Discover beautiful coastlines, peaceful surroundings and
                  relaxing holiday experiences.
                </p>
                <a class="package-link" href="{{ route('public.contact') }}"
                  >Enquire Now <i class="bi bi-arrow-right ms-1"></i
                ></a>
              </div>
            </div>
          </div>

          <div class="col-md-6 col-lg-4">
            <div class="package-card">
              <img
                class="package-img"
                loading="lazy"
                src="https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=800&q=80"
                alt="Mountain travel destination"
              />
              <div class="package-body">
                <div class="package-meta">
                  <i class="bi bi-mountain"></i> Nature & Adventure
                </div>
                <h4>Mountain Escapes</h4>
                <p>
                  Experience scenic landscapes, fresh air and memorable mountain
                  adventures.
                </p>
                <a class="package-link" href="{{ route('public.contact') }}"
                  >Enquire Now <i class="bi bi-arrow-right ms-1"></i
                ></a>
              </div>
            </div>
          </div>

          <div class="col-md-6 col-lg-4">
            <div class="package-card">
              <img
                class="package-img"
                loading="lazy"
                src="https://images.unsplash.com/photo-1519608487953-e999c86e7455?auto=format&fit=crop&w=800&q=80"
                alt="City skyline at night"
              />
              <div class="package-body">
                <div class="package-meta">
                  <i class="bi bi-buildings"></i> City & Culture
                </div>
                <h4>City Explorations</h4>
                <p>
                  Discover famous landmarks, local culture, exciting attractions
                  and city life.
                </p>
                <a class="package-link" href="{{ route('public.contact') }}"
                  >Enquire Now <i class="bi bi-arrow-right ms-1"></i
                ></a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- BUSINESS PLAN -->
    <section class="section-space business-section" id="business">
      <div class="container">
        <div class="row align-items-center mb-5">
          <div class="col-lg-8">
            <span class="eyebrow">Business Plan</span>
            <h2 class="section-title mt-3">
              Discover Opportunities With Delta Support
            </h2>
            <p class="section-desc mb-0">
              Learn about our services, business model and available
              collaboration opportunities. Contact our team for the official
              plan and eligibility details.
            </p>
          </div>
          <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
            <a href="{{ route('public.contact') }}" class="btn-main"
              >Request Information <i class="bi bi-arrow-right ms-2"></i
            ></a>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-sm-6 col-lg-4">
            <div class="business-card">
              <div class="icon"><i class="bi bi-briefcase"></i></div>
              <h5>Business Overview</h5>
              <p>
                Understand our company, services, business objectives and
                operating approach.
              </p>
            </div>
          </div>
          <div class="col-sm-6 col-lg-4">
            <div class="business-card">
              <div class="icon"><i class="bi bi-diagram-3"></i></div>
              <h5>Business Structure</h5>
              <p>
                Learn about the proposed business structure, roles and
                participation requirements.
              </p>
            </div>
          </div>
          <div class="col-sm-6 col-lg-4">
            <div class="business-card">
              <div class="icon"><i class="bi bi-chat-dots"></i></div>
              <h5>Personal Assistance</h5>
              <p>
                Connect with our team to discuss services, terms, costs and
                other relevant details.
              </p>
            </div>
          </div>
        </div>

        <div class="business-note mt-4">
          <i class="bi bi-info-circle me-2"></i>
          Official business terms, fees, eligibility and any financial
          commitments should be disclosed clearly before participation. No
          income or return is guaranteed.
        </div>
      </div>
    </section>

    <!-- CONTACT -->
    <section class="contact-cta-section section-space">
      <div class="container">
        <div class="contact-cta-box">
          <div class="row align-items-center g-4">
            <div class="col-lg-8">
              <span class="cta-label">
                <i class="bi bi-headset me-2"></i>
                WE'RE HERE TO HELP
              </span>

              <h2 class="cta-title">
                Have Questions? Let's Start
                <span>A Conversation.</span>
              </h2>

              <p class="cta-desc">
                Have a question about our packages, services or business plan?
                Our team is here to assist you. Get in touch with Delta Support
                today!
              </p>

              <a href="{{ route('public.contact') }}" class="cta-btn">
                Contact Us
                <i class="bi bi-arrow-up-right ms-2"></i>
              </a>
            </div>

            <div class="col-lg-4 text-center d-none d-lg-block">
              <div class="cta-icon-wrap">
                <i class="bi bi-chat-dots"></i>
                <div class="cta-small-icon">
                  <i class="bi bi-headset"></i>
                </div>
              </div>
            </div>
          </div>

          <div class="cta-circle cta-circle-one"></div>
          <div class="cta-circle cta-circle-two"></div>
        </div>
      </div>
    </section>

    <!-- FOOTER -->
    @endsection
