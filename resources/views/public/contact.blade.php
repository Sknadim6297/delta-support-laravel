@extends('public.layout')

@section('title', 'Contact | Delta Support')

@section('meta')
    <meta name="description" content="Discover destinations, travel packages and business opportunities with Delta Support.">
@endsection

@section('content')


    <!-- BANNER -->
    <section class="contact-banner">
      <div class="container">
        <div class="banner-content">
          <span class="banner-tag">
            <i class="bi bi-chat-square-text"></i> CONTACT DELTA SUPPORT
          </span>

          <h1>Get In <span>Touch</span></h1>

          <p>
            Have a question about our services, packages or business plan? We're
            here to help. Connect with our team for information, assistance and
            general enquiries.
          </p>

          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
              <li class="breadcrumb-item active" aria-current="page">
                Contact Us
              </li>
            </ol>
          </nav>
        </div>
      </div>
    </section>

    <!-- CONTACT DETAILS + FORM -->
    <section class="contact-section">
      <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width: 650px">
          <span class="section-tag">We Are Here For You</span>
          <h2 class="section-title">Let's Start A Conversation</h2>
          <p class="section-desc">
            Tell us what you are looking for. Our team can help you find the
            right information about our services and opportunities.
          </p>
        </div>

        <div class="row g-4 align-items-stretch">
          <!-- CONTACT INFORMATION -->
          <div class="col-lg-5">
            <div class="contact-info-card">
              <h3>Contact Information</h3>
              <p class="intro">
                Have questions or need more information? Reach out to Delta
                Support using the details below.
              </p>

              <!-- Replace with actual company information -->
              <div class="contact-detail">
                <div class="detail-icon"><i class="bi bi-envelope"></i></div>
                <div>
                  <small>Email Address</small>
                  <a href="mailto:info@@yourdomain.com">info@@yourdomain.com</a>
                </div>
              </div>

              <div class="contact-detail">
                <div class="detail-icon"><i class="bi bi-telephone"></i></div>
                <div>
                  <small>Phone Number</small>
                  <span class="value">Add your company phone number</span>
                </div>
              </div>

              <div class="contact-detail">
                <div class="detail-icon"><i class="bi bi-geo-alt"></i></div>
                <div>
                  <small>Office Address</small>
                  <span class="value">Add your company address</span>
                </div>
              </div>

              <div class="contact-detail">
                <div class="detail-icon"><i class="bi bi-clock"></i></div>
                <div>
                  <small>Working Hours</small>
                  <span class="value">Add your business hours</span>
                </div>
              </div>

              <hr class="border-light opacity-25 my-4" />

              <h6 class="fw-bold mb-3">Follow Us</h6>
              <div class="contact-social">
                <a href="#" aria-label="Facebook"
                  ><i class="bi bi-facebook"></i
                ></a>
                <a href="#" aria-label="Instagram"
                  ><i class="bi bi-instagram"></i
                ></a>
                <a href="#" aria-label="LinkedIn"
                  ><i class="bi bi-linkedin"></i
                ></a>
              </div>
            </div>
          </div>

          <!-- ENQUIRY FORM -->
          <div class="col-lg-7">
            <div class="contact-form-card">
              <h3>Send Us An Enquiry</h3>
              <p class="form-intro">
                Fill in the details below and tell us how we can assist you.
              </p>

              <form id="contactForm">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label for="fullName" class="form-label">Full Name *</label>
                    <input
                      type="text"
                      class="form-control"
                      id="fullName"
                      name="fullName"
                      placeholder="Enter your full name"
                      required
                    />
                  </div>

                  <div class="col-md-6">
                    <label for="email" class="form-label"
                      >Email Address *</label
                    >
                    <input
                      type="email"
                      class="form-control"
                      id="email"
                      name="email"
                      placeholder="Enter your email"
                      required
                    />
                  </div>

                  <div class="col-md-6">
                    <label for="phone" class="form-label">Phone Number *</label>
                    <input
                      type="tel"
                      class="form-control"
                      id="phone"
                      name="phone"
                      placeholder="Enter your phone number"
                      pattern="[0-9+\s()-]{7,20}"
                      required
                    />
                  </div>

                  <div class="col-md-6">
                    <label for="subject" class="form-label"
                      >Enquiry Type *</label
                    >
                    <select
                      class="form-select"
                      id="subject"
                      name="subject"
                      required
                    >
                      <option value="" selected disabled>
                        Select enquiry type
                      </option>
                      <option value="Packages">Travel Packages</option>
                      <option value="Business Plan">Business Plan</option>
                      <option value="Services">General Services</option>
                      <option value="Other">Other Enquiry</option>
                    </select>
                  </div>

                  <div class="col-12">
                    <label for="message" class="form-label"
                      >Your Message *</label
                    >
                    <textarea
                      class="form-control"
                      id="message"
                      name="message"
                      rows="5"
                      placeholder="Write your message here..."
                      required
                    ></textarea>
                  </div>

                  <div class="col-12">
                    <button type="submit" class="btn-main w-100 py-3">
                      Send Enquiry <i class="bi bi-send"></i>
                    </button>

                    <p class="privacy-note mt-3 mb-0">
                      <i class="bi bi-shield-check me-1"></i>
                      Please provide accurate contact details so we can respond
                      to your enquiry.
                    </p>

                    <div
                      id="formMessage"
                      class="small mt-3"
                      role="status"
                      aria-live="polite"
                    ></div>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- BOTTOM CTA -->
    <section class="bottom-cta">
      <div class="container">
        <div class="bottom-cta-box">
          <div>
            <h3>Looking For More Information?</h3>
            <p>Explore our packages or learn more about our business plan.</p>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('public.packages') }}" class="btn-main"
              >View Packages <i class="bi bi-arrow-right"></i
            ></a>
            <a
              href="{{ route('public.business-plan') }}"
              class="btn btn-outline-primary fw-bold px-3 py-2"
            >
              Business Plan
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- FOOTER -->
    @endsection

@push('scripts')
<script>
document.getElementById('contactForm')?.addEventListener('submit', function (event) {
    event.preventDefault();
    const message = document.getElementById('formMessage');
    if (message) {
        message.textContent = 'This enquiry form is not connected to a mailbox yet.';
    }
});
</script>
@endpush