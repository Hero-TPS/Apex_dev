<?php
// index.php — Public brochure landing page
require_once __DIR__ . '/config.php';
include ROOT_DIR . '/includes/header_public.php';
?>

<div class="at-page">

    <!-- ═══════════ HERO ═══════════ -->
    <section class="at-hero">

        <img
            class="at-hero-logo"
            src="<?= htmlspecialchars(BASE_URL) ?>/assets/images/android-chrome-512x512.png"
            alt="<?= htmlspecialchars(BUSINESS_NAME) ?> Logo"
        >

        <h1><?= htmlspecialchars(BUSINESS_NAME) ?></h1>

        <p class="at-hero-tagline">Reliable Private Hire Transport — Cape Town and surrounds</p>

        <p class="at-hero-contact">
            <a href="tel:<?= htmlspecialchars(BUSINESS_PHONE) ?>">
                <?= htmlspecialchars(BUSINESS_PHONE) ?>
            </a>
        </p>

        <div class="at-cta-group">
            <a class="at-btn at-btn-primary"
               href="https://wa.me/<?= htmlspecialchars(BUSINESS_WHATSAPP) ?>"
               target="_blank" rel="noopener">
                📱 WhatsApp Us
            </a>
            <a class="at-btn at-btn-outline" href="#contact">
                ✉️ Make an Enquiry
            </a>
        </div>

    </section>

    <!-- ═══════════ SERVICES ═══════════ -->
    <div class="at-services-wrap">
        <div class="at-section">
            <h2 class="at-section-title">Our Services</h2>
            <div class="at-section-underline"></div>

            <div class="at-grid">

                <div class="at-card">
                    <span class="at-card-icon">✈️</span>
                    <h3>Airport Transfers</h3>
                    <p>Punctual door-to-door transfers to and from Cape Town International and beyond.</p>
                </div>

                <div class="at-card">
                    <span class="at-card-icon">🚗</span>
                    <h3>Point-to-Point Rides</h3>
                    <p>Comfortable private hire for any destination — local errands or cross-town trips.</p>
                </div>

                <div class="at-card">
                    <span class="at-card-icon">💼</span>
                    <h3>Corporate &amp; Events</h3>
                    <p>Professional transport for business meetings, corporate functions, and special events.</p>
                </div>

                <div class="at-card">
                    <span class="at-card-icon">🧳</span>
                    <h3>Tours &amp; Package Transport</h3>
                    <p>Guided area tours and reliable transport for goods and packages throughout the region.</p>
                </div>

            </div>
        </div>
    </div>

    <!-- ═══════════ ABOUT ═══════════ -->
    <div class="at-about-wrap">
        <div class="at-section">
            <h2 class="at-section-title">About Us</h2>
            <div class="at-section-underline"></div>

            <div class="at-about-inner">

                <div class="at-about-text">
                    <p>
                        <?= htmlspecialchars(BUSINESS_NAME) ?> is an owner-operated private transport
                        service built on reliability, trust, and getting you there on time — every time.
                        Based in the Helderberg Area, we are proud to serve our local community and
                        visitors with a personal, professional touch.
                    </p>
                    <p>
                        Our registered Comfort Class sedan offers spacious legroom and a generous
                        boot — perfect for airport runs, corporate travel, or leisure trips.
                        Early bookings are essential; message us with your requirements and
                        we'll secure your booking and plan your trip accordingly.
                    </p>
                </div>

                <div class="at-trust-badges">

                    <div class="at-badge">
                        <span class="at-badge-icon">✅</span>
                        <div class="at-badge-text">
                            <strong>PrDP Licensed &amp; Registered</strong>
                            <span>Public Transport Drivers Licence &amp; registered vehicle</span>
                        </div>
                    </div>

                    <div class="at-badge">
                        <span class="at-badge-icon">⏱️</span>
                        <div class="at-badge-text">
                            <strong>Always On Time</strong>
                            <span>Punctuality is our promise — your schedule is our priority</span>
                        </div>
                    </div>

                    <div class="at-badge">
                        <span class="at-badge-icon">🛋️</span>
                        <div class="at-badge-text">
                            <strong>Comfort Class Sedan</strong>
                            <span>4-seater with spacious legroom, large trunk &amp; climate control</span>
                        </div>
                    </div>

                    <div class="at-badge">
                        <span class="at-badge-icon">📍</span>
                        <div class="at-badge-text">
                            <strong>Helderberg Based</strong>
                            <span>Serving the local area and greater Western Cape</span>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>

    <!-- ═══════════ CONTACT ═══════════ -->
    <div class="at-contact-wrap" id="contact">
        <div class="at-section">
            <h2 class="at-section-title">Get in Touch</h2>
            <div class="at-section-underline"></div>

            <div class="at-contact-inner">

                <!-- Contact details -->
                <div class="at-contact-info">
                    <h3>Ready when you are.</h3>

                    <div class="at-contact-detail">
                        <span class="icon">📱</span>
                        <a href="tel:<?= htmlspecialchars(BUSINESS_PHONE) ?>">
                            <?= htmlspecialchars(BUSINESS_PHONE) ?>
                        </a>
                    </div>

                    <div class="at-contact-detail">
                        <span class="icon">💬</span>
                        <a href="https://wa.me/<?= htmlspecialchars(BUSINESS_WHATSAPP) ?>"
                           target="_blank" rel="noopener">
                            WhatsApp Us
                        </a>
                    </div>

                    <div class="at-contact-detail">
                        <span class="icon">✉️</span>
                        <a href="mailto:<?= htmlspecialchars(BUSINESS_EMAIL) ?>">
                            <?= htmlspecialchars(BUSINESS_EMAIL) ?>
                        </a>
                    </div>

                    <div class="at-contact-note">
                        ⚡ <strong>Early bookings are essential.</strong><br>
                        Message us with your requirements for a quote so we can secure
                        your booking and plan your trip accordingly.
                    </div>
                </div>

                <!-- Enquiry form -->
                <form id="enquiry-form" class="at-form">

                    <div class="at-field">
                        <label for="at-name">Full Name *</label>
                        <input type="text" id="at-name" name="name"
                               placeholder="Your name" required>
                    </div>

                    <div class="at-field">
                        <label for="at-phone">Phone Number</label>
                        <input type="tel" id="at-phone" name="phone"
                               placeholder="+27 82 000 0000">
                    </div>

                    <div class="at-field">
                        <label for="at-email">Email Address</label>
                        <input type="email" id="at-email" name="email"
                               placeholder="you@example.com">
                    </div>

                    <div class="at-field">
                        <label for="at-message">Message / Trip Details *</label>
                        <textarea id="at-message" name="message" rows="5"
                                  placeholder="Date, pickup location, destination, number of passengers…"
                                  required></textarea>
                    </div>

                    <div id="contact-result"></div>

                    <button type="submit" class="at-btn at-btn-primary at-btn-submit">
                        ✉️ Send Enquiry
                    </button>

                </form>

            </div>
        </div>
    </div>

    <!-- ═══════════ FOOTER ═══════════ -->
    <footer class="at-footer">
        <span>
            &copy; <?= date('Y') . ' ' . htmlspecialchars(BUSINESS_NAME) ?>.
            All rights reserved. &nbsp;|&nbsp; Looking forward to being of service to you!
        </span>
        <a href="<?= htmlspecialchars(BASE_URL) ?>/login.php">Staff Login</a>
    </footer>

</div><!-- /.at-page -->

<script>
document.getElementById('enquiry-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = this;
    var result = document.getElementById('contact-result');
    var btn = form.querySelector('.at-btn-submit');

    var data = new FormData(form);
    btn.disabled = true;
    result.textContent = 'Sending\u2026';

    fetch('<?= BASE_URL ?>/contact.php', {
        method: 'POST',
        body: data
    })
    .then(function (res) { return res.json(); })
    .then(function (json) {
        if (json.success) {
            result.style.color = '#27ae60';
            result.textContent = json.message || 'Thank you! We will be in touch soon.';
            form.reset();
        } else {
            result.style.color = '#e74c3c';
            result.textContent = json.message || 'Something went wrong. Please try again.';
        }
        btn.disabled = false;
    })
    .catch(function () {
        result.style.color = '#e74c3c';
        result.textContent = 'Could not send your enquiry. Please try again later.';
        btn.disabled = false;
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer_public.php'; ?>
