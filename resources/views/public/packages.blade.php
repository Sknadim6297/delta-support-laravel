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

    <!-- SECTION HEADING -->
    <div class="text-center mx-auto mb-5" style="max-width:700px">
      <span class="section-tag">Choose Your Package</span>
      <h2 class="section-title">Find The Option That Suits You</h2>
      <p class="section-desc">
        Explore our package options and discover the destinations
        and payment details available with Delta Support.
      </p>
    </div>

    <!-- OPTION 1 -->
    <div class="text-center mb-4">
      <span class="section-tag">PACKAGE OPTION 01</span>
      <h3 class="fw-bold mt-2">Travel Experience Package</h3>
      <div class="package-price justify-content-center mt-2">
        <strong>₹10,000/-</strong>
        <span>/ package</span>
      </div>
    </div>

    <div class="row g-4">

      <!-- DIGHA -->
      <div class="col-md-6 col-lg-4">
        <div class="destination-card">
          <div class="destination-image">
            <img
              src="https://images.unsplash.com/photo-1500375592092-40eb2168fd21?auto=format&fit=crop&w=800&q=85"
              alt="Digha beach destination"
              loading="lazy">
            <span class="destination-year">FIRST YEAR</span>
          </div>

          <div class="destination-body">
            <span class="destination-category">
              <i class="bi bi-water"></i> Beach Destination
            </span>

            <h3>Digha</h3>
            <p class="destination-description">
              Enjoy a relaxing seaside getaway and explore the
              beautiful coastal surroundings.
            </p>

            <div class="destination-detail">
              <i class="bi bi-calendar3"></i>
              <div>
                <small>Travel Availability</small>
                <strong>After 30 Days</strong>
              </div>
            </div>

            <div class="destination-detail">
              <i class="bi bi-moon-stars"></i>
              <div>
                <small>Duration</small>
                <strong>2 Nights / 3 Days</strong>
              </div>
            </div>

            <div class="food-coupon">
              <i class="bi bi-ticket-perforated"></i>
              <div>
                <small>Food Coupon</small>
                <strong>₹1,000/-</strong>
              </div>
            </div>

            <a href="{{ route('public.contact') }}" class="destination-btn">
              Enquire Now <i class="bi bi-arrow-right"></i>
            </a>
          </div>
        </div>
      </div>

      <!-- GOA -->
      <div class="col-md-6 col-lg-4">
        <div class="destination-card">
          <div class="destination-image">
            <img
              src="https://images.unsplash.com/photo-1519046904884-53103b34b206?auto=format&fit=crop&w=800&q=85"
              alt="Goa tropical beach"
              loading="lazy">
            <span class="destination-year">SECOND YEAR</span>
          </div>

          <div class="destination-body">
            <span class="destination-category">
              <i class="bi bi-sun"></i> Holiday Destination
            </span>

            <h3>Goa</h3>
            <p class="destination-description">
              Discover tropical beaches, coastal views and a
              memorable holiday experience in Goa.
            </p>

            <div class="destination-detail">
              <i class="bi bi-calendar3"></i>
              <div>
                <small>Travel Schedule</small>
                <strong>Second Year</strong>
              </div>
            </div>

            <div class="destination-detail">
              <i class="bi bi-moon-stars"></i>
              <div>
                <small>Duration</small>
                <strong>2 Nights / 3 Days</strong>
              </div>
            </div>

            <div class="food-coupon">
              <i class="bi bi-ticket-perforated"></i>
              <div>
                <small>Food Coupon</small>
                <strong>₹1,000/-</strong>
              </div>
            </div>

            <a href="{{ route('public.contact') }}" class="destination-btn">
              Enquire Now <i class="bi bi-arrow-right"></i>
            </a>
          </div>
        </div>
      </div>

      <!-- GANGTOK -->
      <div class="col-md-6 col-lg-4">
        <div class="destination-card">
          <div class="destination-image">
            <img
              src="https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=800&q=85"
              alt="Gangtok mountain destination"
              loading="lazy">
            <span class="destination-year">THIRD YEAR</span>
          </div>

          <div class="destination-body">
            <span class="destination-category">
              <i class="bi bi-mountains"></i> Mountain Destination
            </span>

            <h3>Gangtok</h3>
            <p class="destination-description">
              Experience scenic mountain landscapes and the natural
              beauty of the Himalayan region.
            </p>

            <div class="destination-detail">
              <i class="bi bi-calendar3"></i>
              <div>
                <small>Travel Schedule</small>
                <strong>Third Year</strong>
              </div>
            </div>

            <div class="destination-detail">
              <i class="bi bi-moon-stars"></i>
              <div>
                <small>Duration</small>
                <strong>2 Nights / 3 Days</strong>
              </div>
            </div>

            <div class="food-coupon">
              <i class="bi bi-ticket-perforated"></i>
              <div>
                <small>Food Coupon</small>
                <strong>₹1,000/-</strong>
              </div>
            </div>

            <a href="{{ route('public.contact') }}" class="destination-btn">
              Enquire Now <i class="bi bi-arrow-right"></i>
            </a>
          </div>
        </div>
      </div>

    </div>

    <!-- OR DIVIDER -->
    <div class="or-divider">OR</div>

    <!-- OPTION 2 -->
    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="package-card">

          <div class="package-head">
            <span class="package-label">PACKAGE OPTION 02</span>
            <h3>Monthly Payment Option</h3>
            <div class="package-price">
              <strong>₹10,000</strong>
              <span>/ package</span>
            </div>
          </div>

          <div class="package-body">

            <div class="package-row">
              <div class="row-icon">
                <i class="bi bi-wallet2"></i>
              </div>
              <div class="flex-grow-1">
                <h6>Package Price</h6>
                <p>Total listed package price.</p>
                <span class="detail">₹10,000/-</span>
              </div>
            </div>

            <div class="package-row">
              <div class="row-icon">
                <i class="bi bi-water"></i>
              </div>
              <div class="flex-grow-1">
                <h6>First Year — Digha</h6>
                <p>Travel duration as shown in the package details.</p>
                <span class="detail">3 Nights / 4 Days</span>
              </div>
            </div>

            <div class="package-row">
              <div class="row-icon">
                <i class="bi bi-calendar-check"></i>
              </div>
              <div class="flex-grow-1">
                <h6>Monthly Payment</h6>
                <p>Listed monthly amount for 25 months.</p>
                <span class="detail">₹800 × 25 Months</span>
                <span class="detail">Total: ₹20,000/-</span>
              </div>
            </div>

          </div>

          <div class="package-action">
            <a href="{{ route('public.contact') }}" class="btn-main">
              Enquire About This Option
              <i class="bi bi-arrow-right"></i>
            </a>
          </div>

        </div>
      </div>

      <div class="col-lg-6">
        <div class="monthly-box">

          <span class="section-tag">Payment Breakdown</span>
          <h3 class="fw-bold mt-2 mb-3">Monthly Option Summary</h3>

          <p class="section-desc">
            Summary of the payment figures shown in the package details.
          </p>

          <div class="table-responsive">
            <table class="monthly-table">
              <thead>
                <tr>
                  <th>Description</th>
                  <th>Amount</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Package Price</td>
                  <td>₹10,000/-</td>
                </tr>
                <tr>
                  <td>Monthly Payment</td>
                  <td>₹800/-</td>
                </tr>
                <tr>
                  <td>Payment Duration</td>
                  <td>25 Months</td>
                </tr>
                <tr>
                  <td>₹800 × 25 Months</td>
                  <td>₹20,000/-</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="total-box">
            <span>Total of 25 monthly payments</span>
            <strong>₹20,000/-</strong>
          </div>

          <p class="plan-note">
            <i class="bi bi-info-circle me-1"></i>
            Please confirm payment terms, travel availability,
            inclusions and any additional charges before payment.
          </p>

        </div>
      </div>
    </div>

   

  </div>
</section>


    <!-- CTA -->
    <section class="cta-section">
      <div class="container">
        <div class="cta-box">
          <div class="row align-items-center g-4">
            <div class="col-lg-8">
              <span class="text-warning fw-bold small"
                >NEED MORE INFORMATION?</span
              >
              <h2>Let's Discuss Your Package</h2>
              <p>
                Contact our team to clarify travel schedules, package terms and
                the details you need before making a decision.
              </p>
            </div>
            <div class="col-lg-4 text-lg-end">
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
