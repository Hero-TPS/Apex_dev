<?php
// modules/Itineraries/edit.php
$page_title    = 'Edit Itinerary';
$page_subtitle = 'Edit Itinerary';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/modules/Itineraries/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Itineraries', 'url' => BASE_URL . '/modules/Itineraries/'],
    ['label' => 'Edit Itinerary'],
]);
include ROOT_DIR . '/includes/header.php';

if (!itineraryMigrationReady($pdo)) {
    echo itineraryMigrationNotice();
    include ROOT_DIR . '/includes/footer.php';
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo '<div class="error-message">Invalid itinerary ID.</div>';
    include ROOT_DIR . '/includes/footer.php';
    exit;
}

$itn = fetchItinerary($pdo, $id);
if (!$itn) {
    echo '<div class="error-message">Itinerary not found.</div>';
    include ROOT_DIR . '/includes/footer.php';
    exit;
}

$stopsData    = fetchItineraryStops($pdo, $id);
$destinations = fetchData($pdo, 'destinations', 'name ASC');
$costs        = fetchData($pdo, 'costs', 'amount ASC');
$timeOptions  = generateTimeOptions();

include __DIR__ . '/form.php';
include ROOT_DIR . '/includes/footer.php';
