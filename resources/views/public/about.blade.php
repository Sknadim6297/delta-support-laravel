@extends('public.layout')

@section('title', 'About | Delta Support')

@section('meta')
    <meta name="description" content="Discover destinations, travel packages and business opportunities with Delta Support.">
@endsection

@section('content')


    <!-- ABOUT BANNER -->
    <section class="page-banner">
      <div class="container">
        <div class="banner-content">
          <span class="banner-tag"
            ><i class="bi bi-buildings"></i> GET TO KNOW US</span
          >
          <h1>About <span>Delta Support</span></h1>
          <p>
            Discover who we are, what we stand for and how we aim to create
            meaningful experiences through our services, commitment and vision.
          </p>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
              <li class="breadcrumb-item active" aria-current="page">
                About Us
              </li>
            </ol>
          </nav>
        </div>
      </div>
    </section>

    <!-- COMPANY INTRODUCTION -->
    <section class="section-space">
      <div class="container">
        <div class="grid items-center gap-12 lg:grid-cols-2">
          <div>
            <div class="about-visual">
              <div class="about-image-label">
                <h5>Discover More With Delta Support</h5>
                <p>
                  Building connections, supporting experiences and opening doors
                  to new possibilities.
                </p>
              </div>
            </div>
          </div>

          <div>
            <span class="section-tag">Who We Are</span>
            <h2 class="section-title">
              A Company Focused On Experiences And Opportunities
            </h2>

            <p class="section-desc">
              Delta Support aims to help people discover services, explore new
              experiences and learn about opportunities that align with their
              needs and interests.
            </p>

            <p class="section-desc">
              We believe that every journey should be inspiring, convenient and
              memorable. Our approach is built around clear communication,
              dependable support and a commitment to continuous improvement.
            </p>

            <div class="check-item">
              <i class="bi bi-check-circle-fill"></i>
              <div>
                <h6>Customer-Focused Approach</h6>
                <p>
                  We aim to understand enquiries and provide clear, useful
                  information.
                </p>
              </div>
            </div>

            <div class="check-item">
              <i class="bi bi-check-circle-fill"></i>
              <div>
                <h6>Commitment To Quality</h6>
                <p>
                  We value professionalism, transparency and consistent service.
                </p>
              </div>
            </div>

            <div class="check-item">
              <i class="bi bi-check-circle-fill"></i>
              <div>
                <h6>Continuous Improvement</h6>
                <p>
                  We seek better ways to support customers and develop our
                  services.
                </p>
              </div>
            </div>

            <a href="{{ route('public.contact') }}" class="btn-main mt-4">
              Connect With Us <i class="bi bi-arrow-right"></i>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- VISION & MISSION -->
    <section class="section-space section-light">
      <div class="container">
        <div class="mx-auto mb-12 text-center" style="max-width: 650px">
          <span class="section-tag">Our Direction</span>
          <h2 class="section-title">Vision & Mission</h2>
          <p class="section-desc">
            The principles that guide our work, our services and our
            relationship with the people we serve.
          </p>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
          <div>
            <div class="vm-card">
              <div class="vm-icon"><i class="bi bi-eye"></i></div>
              <h3>Our Vision</h3>
              <p>
                To build a trusted and forward-looking company that helps people
                discover meaningful experiences, access useful services and
                explore new possibilities with confidence.
              </p>
            </div>
          </div>

          <div>
            <div class="vm-card">
              <div class="vm-icon"><i class="bi bi-bullseye"></i></div>
              <h3>Our Mission</h3>
              <p>
                To provide clear information, responsive assistance and a
                professional experience while continually improving our services
                in line with customer needs.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CORE VALUES -->
    <section class="section-space">
      <div class="container">
        <div class="mx-auto mb-12 text-center" style="max-width: 650px">
          <span class="section-tag">What Matters To Us</span>
          <h2 class="section-title">Our Core Values</h2>
          <p class="section-desc">
            Our values shape how we communicate, work and serve our customers.
          </p>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <div class="value-card">
              <div class="value-icon"><i class="bi bi-shield-check"></i></div>
              <h5>Integrity</h5>
              <p>
                We believe in honest communication, responsible practices and
                clear information.
              </p>
            </div>
          </div>

          <div>
            <div class="value-card">
              <div class="value-icon"><i class="bi bi-people"></i></div>
              <h5>Customer Focus</h5>
              <p>
                We aim to understand customer needs and provide helpful,
                respectful support.
              </p>
            </div>
          </div>

          <div>
            <div class="value-card">
              <div class="value-icon"><i class="bi bi-lightbulb"></i></div>
              <h5>Innovation</h5>
              <p>
                We welcome fresh ideas and look for practical ways to improve
                our services.
              </p>
            </div>
          </div>

          <div>
            <div class="value-card">
              <div class="value-icon"><i class="bi bi-award"></i></div>
              <h5>Quality</h5>
              <p>
                We strive for professionalism, consistency and a positive
                customer experience.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CONTACT CTA -->
    <section class="cta-section">
      <div class="container">
        <div class="cta-box">
          <div class="cta-grid grid items-center gap-6 lg:grid-cols-12">
            <div class="lg:col-span-8">
              <span class="cta-label">LET'S CONNECT</span>
              <h2>Ready To Discover More With Us?</h2>
              <p>
                Have questions about Delta Support, our services or business
                plan? Connect with our team to learn more.
              </p>
            </div>
            <div class="lg:col-span-4 lg:text-right">
              <a href="{{ route('public.contact') }}" class="btn-orange">
                Contact Our Team <i class="bi bi-arrow-up-right"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- FOOTER -->
    @endsection
