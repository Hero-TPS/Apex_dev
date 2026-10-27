<?php
// modules/Itineraries/add.php
$page_title    = 'Add Itinerary';
$page_subtitle = 'Add Itinerary';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/modules/Itineraries/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Itineraries', 'url' => BASE_URL . '/modules/Itineraries/'],
    ['label' => 'Add Itinerary'],
]);
include ROOT_DIR . '/includes/header.php';

if (!itineraryMigrationReady($pdo)) {
    echo itineraryMigrationNotice();
    include ROOT_DIR . '/includes/footer.php';
    exit;
}

$contacts     = fetchData($pdo, 'contacts', 'name ASC');
$destinations = fetchData($pdo, 'destinations', 'name ASC');
$costs        = fetchData($pdo, 'costs', 'amount ASC');
$timeOptions  = generateTimeOptions();

$itn       = null;
$stopsData = [];

include __DIR__ . '/form.php';
include ROOT_DIR . '/includes/footer.php';
