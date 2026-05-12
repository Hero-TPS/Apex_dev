<?php
// index.php — Public home page
// NOTE: Add to config.php:
// define('BUSINESS_PHONE', '+27821234567');    // Display format
// define('BUSINESS_WHATSAPP', '27821234567'); // Digits only, no + or spaces
// define('BUSINESS_EMAIL', 'you@example.com');

require_once __DIR__ . '/config.php';
include ROOT_DIR . '/includes/header_public.php';
?>

<!-- Hero -->
<section class="pub-hero">
    <h1><?= htmlspecialchars(BUSINESS_NAME) ?></h1>
    <p>Reliable Private Hire Transport — Helderberg Area</p>
    <a href="https://wa.me/<?= htmlspecialchars(BUSINESS_WHATSAPP) ?>" class="pub-btn pub-btn-wa" target="_blank" rel="noopener">📱 WhatsApp Us</a>
    <a href="#contact" class="pub-btn pub-btn-enquiry">✉️ Make an Enquiry</a>
</section>

<!-- Services -->
<section class="pub-section">
    <h2>Our Services</h2>
    <div class="pub-cards">
        <div class="pub-card">
            <div class="pub-card-icon">✈️</div>
            <h3>Airport Transfers</h3>
        </div>
        <div class="pub-card">
            <div class="pub-card-icon">🚗</div>
            <h3>Private Hire</h3>
        </div>
        <div class="pub-card">
            <div class="pub-card-icon">🛣️</div>
            <h3>Long Distance</h3>
        </div>
        <div class="pub-card">
            <div class="pub-card-icon">💼</div>
            <h3>Corporate &amp; Events</h3>
        </div>
    </div>
</section>

<!-- About -->
<section class="pub-section">
    <h2>About Us</h2>
    <p>Owner-operated, personal transport service. Built on reliability, trust, and getting you there on time.</p>
</section>

<!-- Contact -->
<section class="pub-section" id="contact">
    <h2>Contact Us</h2>
    <div class="pub-contact-links">
        <a href="tel:<?= htmlspecialchars(BUSINESS_PHONE) ?>">📞 <?= htmlspecialchars(BUSINESS_PHONE) ?></a>
        <a href="mailto:<?= htmlspecialchars(BUSINESS_EMAIL) ?>">✉️ <?= htmlspecialchars(BUSINESS_EMAIL) ?></a>
    </div>
    <form class="pub-form" id="enquiry-form">
        <div class="pub-form-group">
            <label for="enq-name">Name <span aria-hidden="true">*</span></label>
            <input type="text" id="enq-name" name="name" required>
        </div>
        <div class="pub-form-group">
            <label for="enq-phone">Phone</label>
            <input type="tel" id="enq-phone" name="phone">
        </div>
        <div class="pub-form-group">
            <label for="enq-email">Email</label>
            <input type="email" id="enq-email" name="email">
        </div>
        <div class="pub-form-group">
            <label for="enq-message">Message <span aria-hidden="true">*</span></label>
            <textarea id="enq-message" name="message" required></textarea>
        </div>
        <button type="submit" class="pub-form-submit">Send Enquiry</button>
        <div id="contact-result"></div>
    </form>
</section>

<!-- Page footer -->
<footer class="pub-footer">
    <p>&copy; <?= date('Y') . ' ' . htmlspecialchars(BUSINESS_NAME) ?></p>
    <a href="<?= BASE_URL ?>/login.php" class="pub-staff-link">Staff Login</a>
</footer>

<script>
document.getElementById('enquiry-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = this;
    var result = document.getElementById('contact-result');
    var btn = form.querySelector('.pub-form-submit');

    var data = new FormData(form);
    btn.disabled = true;
    result.textContent = 'Sending…';

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
