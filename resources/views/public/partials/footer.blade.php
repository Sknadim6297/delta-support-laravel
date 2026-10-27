<!-- FOOTER -->
    <footer class="footer">
      <div class="container">
        <div class="grid grid-cols-2 gap-6 md:grid-cols-12">
          <div class="col-span-2 md:col-span-6 lg:col-span-4">
            <a
              href="{{ route('home') }}"
              class="navbar-brand mb-4 inline-flex items-center text-white"
            >
              <span class="brand-icon"
                ><i class="bi bi-airplane-engines"></i
              ></span>
              DELTA <span class="ms-2 text-sky-300">SUPPORT</span>
            </a>
            <p>
              Explore destinations, discover new experiences and connect with
              Delta Support for service and business enquiries.
            </p>
            <div class="mt-4">
              <a href="#" class="social-link" aria-label="Facebook"
                ><i class="bi bi-facebook"></i
              ></a>
              <a href="#" class="social-link" aria-label="Instagram"
                ><i class="bi bi-instagram"></i
              ></a>
              <a href="#" class="social-link" aria-label="LinkedIn"
                ><i class="bi bi-linkedin"></i
              ></a>
            </div>
          </div>

          <div class="col-span-1 md:col-span-3 lg:col-span-2">
            <h5>Quick Links</h5>
            <ul class="footer-links">
              <li><a href="{{ route('home') }}">Home</a></li>
              <li><a href="{{ route('public.about') }}">About Us</a></li>
              <li><a href="{{ route('public.packages') }}">Packages</a></li>
              <li><a href="{{ route('public.business-plan') }}">Business Plan</a></li>
              <li><a href="{{ route('public.contact') }}">Contact</a></li>
            </ul>
          </div>

          <div class="col-span-1 md:col-span-3 lg:col-span-2">
            <h5>Explore</h5>
            <ul class="footer-links">
              <li><a href="{{ route('public.packages') }}">Beach Getaways</a></li>
              <li><a href="{{ route('public.packages') }}">Mountain Escapes</a></li>
              <li><a href="{{ route('public.packages') }}">City Explorations</a></li>
              <li><a href="{{ route('public.business-plan') }}">Business Enquiry</a></li>
            </ul>
          </div>

          <div class="col-span-2 md:col-span-6 lg:col-span-4">
            <h5>Stay Connected</h5>
            <p>
              Contact us to learn more about our services and available
              opportunities.
            </p>
            <a href="{{ route('public.contact') }}" class="btn-main"
              >Contact Our Team <i class="bi bi-arrow-right ms-2"></i
            ></a>
          </div>
        </div>

        <div
          class="copyright flex flex-col justify-between gap-2 md:flex-row"
        >
          <span
            >© <span id="year"></span> Delta Support. All Rights Reserved.</span
          >
          <span>Designed for a world of possibilities.</span>
        </div>
      </div>
    </footer>

    