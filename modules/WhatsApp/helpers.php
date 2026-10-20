<?php
// modules/WhatsApp/helpers.php
// WhatsApp message templates: stored in the whatsapp_templates table, filled in with {placeholders}.
// Expects config.php and includes/helpers.php (and includes/logger.php) to be loaded by the caller.
//
// Template syntax:
//   {name}           replaced by the value. A line whose placeholder is empty is dropped entirely.
//   {if name}        a line of its own: the lines up to {else} / {/if} are kept only when "name" has a value.
//   {if !name}       same, but kept only when "name" is empty.
//   {else}, {/if}    each on a line of its own. {if} blocks may be nested.
// Runs of blank lines are collapsed to one, and blank lines at the start/end are removed.

/**
 * Where each template is used. Decides which placeholders are allowed.
 */
const WHATSAPP_PLACEMENTS = [
    'booking_menu' => 'Booking / client WhatsApp menu',
    'evening'      => "Tomorrow's Confirmations (dashboard)",
    'prebooking'   => 'Prebooking reminder',
    'driver'       => 'Message to driver',
    'booking_group' => 'Combined bookings message (client bookings page)',
];

/**
 * Languages a message can be sent in. English is the default; a message with no
 * Afrikaans text is sent in English.
 */
const WHATSAPP_LANGUAGES = [
    'en' => 'English',
    'af' => 'Afrikaans',
];

/**
 * Normalise a stored language value: 'af' stays 'af', anything else is English.
 * Used by: the message builders below, modules/Bookings/view.php
 */
function normalizeWhatsAppLanguage($language): string
{
    return $language === 'af' ? 'af' : 'en';
}

/**
 * Two-way language sync: ticking Afrikaans on a booking also makes the client Afrikaans.
 * Only ticking updates the client; a booking saved in English never changes the client.
 * Used by: modules/Bookings/api/index.php (add and update)
 */
function syncClientLanguageFromBooking(PDO $pdo, int $contactId, string $bookingLanguage): void
{
    if ($contactId <= 0 || normalizeWhatsAppLanguage($bookingLanguage) !== 'af') {
        return;
    }
    $stmt = $pdo->prepare("UPDATE contacts SET language = 'af' WHERE id = ? AND language <> 'af'");
    $stmt->execute([$contactId]);
    if ($stmt->rowCount() > 0) {
        logInfo('CLIENT', 'Client set to Afrikaans from a booking', ['client_id' => $contactId]);
    }
}

/**
 * The text of a template for a language: the Afrikaans text when asked for Afrikaans and
 * one has been written, otherwise the English text.
 * Used by: renderWhatsAppTemplateByKey(), getWhatsAppClientMessages(), modules/Bookings/view.php
 *
 * @param array $template  A whatsapp_templates row
 */
function getWhatsAppTemplateBody(array $template, string $language): string
{
    if (normalizeWhatsAppLanguage($language) === 'af' && trim((string) ($template['body_af'] ?? '')) !== '') {
        return $template['body_af'];
    }
    return $template['body'];
}

/**
 * Placeholders that only need the client (usable from the Clients pages).
 * Used by: getWhatsAppVariables(), isClientLevelWhatsAppBody()
 *
 * @return array<string,string> name => description
 */
function getWhatsAppClientVariables(): array
{
    return [
        'client_name'     => 'Client name',
        'client_phone'    => 'Client phone number as saved',
        'business_owner'  => 'Business owner name (config)',
        'business_name'   => 'Business name (config)',
        'payment_details' => 'Bank / EFT details (Maintenance → Invoice Payment Details)',
    ];
}

/**
 * Placeholders available for booking-based templates.
 * Used by: getWhatsAppVariables()
 *
 * @return array<string,string> name => description
 */
function getWhatsAppBookingVariables(): array
{
    return getWhatsAppClientVariables() + [
        'date'               => 'Trip date (dd/mm/yy)',
        'time'               => 'Trip start time (HH:MM)',
        'pickup'             => 'Pickup location',
        'destination'        => 'Destination',
        'cost'               => 'Cost with R, e.g. R350.00 (empty when the cost is 0)',
        'trip_cost'          => 'Cost with R, always filled, e.g. R0.00',
        'payment_received'   => 'Filled when payment has been received (use with {if})',
        'flight_number'      => 'Flight number',
        'flightradar_url'    => 'Flightradar24 tracking link for the flight number',
        'notes'              => 'Booking notes including the airport / after-hours notices',
        'passenger_name'     => 'Passenger name (empty when same as the client)',
        'passenger_phone'    => 'Passenger phone (empty when there is no separate passenger)',
        'driver_name'        => 'Allocated driver name',
        'driver_phone'       => 'Allocated driver phone',
        'is_update'          => 'Filled when the booking has been edited since it was created (use with {if})',
        'updated_at'         => 'Last edited date and time (only for an edited booking)',
        'created_at'         => 'Created date and time (only for a booking that was not edited)',
        'driver_notes'       => 'Driver notes',
        'waze_pickup'        => 'Waze link to the pickup',
        'waze_destination'   => 'Waze link to the destination',
        'is_eft'             => 'Filled for EFT bookings (use with {if})',
        'no_booking_fee'     => 'Filled when no booking fee applies (use with {if})',
        'has_booking_fee'    => 'Filled when a booking fee applies (use with {if})',
        'booking_fee'        => 'Apex booking fee with R',
        'settlement_amount'  => 'Amount owed between Apex and the driver, with R',
    ];
}

/**
 * Placeholders available for prebooking reminders.
 * Used by: getWhatsAppVariables()
 *
 * @return array<string,string> name => description
 */
function getWhatsAppPrebookingVariables(): array
{
    return [
        'client_name'     => 'Client name',
        'date'            => 'Trip date (dd/mm/yy)',
        'time'            => 'Trip start time, or TBC',
        'pickup'          => 'Pickup location, or TBC',
        'destination'     => 'Destination, or TBC',
        'cost'            => 'Cost with R, or TBC',
        'notes'           => 'Prebooking notes',
        'passenger_name'  => 'Passenger name (empty when same as the client)',
        'passenger_phone' => 'Passenger phone (empty when there is no separate passenger)',
        'business_owner'  => 'Business owner name (config)',
        'business_name'   => 'Business name (config)',
    ];
}

/**
 * Placeholders available for the combined bookings message (several bookings of one client).
 * Used by: getWhatsAppVariables()
 *
 * @return array<string,string> name => description
 */
function getWhatsAppGroupVariables(): array
{
    return getWhatsAppClientVariables() + [
        'trips'         => 'The trips, grouped by route with the dates and times under each (layout is fixed)',
        'booking_count' => 'Number of bookings in the message',
        'cost_each'     => 'Cost per trip with R, e.g. R350.00. Only filled when every trip costs the same (otherwise the line is left out)',
        'cost_total'    => 'Total of all the trips with R, e.g. R1,100.00. Only filled when the trips do not all cost the same (the prices are then shown on the date lines)',
    ];
}

/**
 * Allowed placeholders for a template placement.
 * Used by: validateWhatsAppTemplateBody(), modules/WhatsApp/partials/template_form.php
 *
 * @return array<string,string> name => description
 */
function getWhatsAppVariables(string $placement): array
{
    if ($placement === 'prebooking') {
        return getWhatsAppPrebookingVariables();
    }
    if ($placement === 'booking_group') {
        return getWhatsAppGroupVariables();
    }
    return getWhatsAppBookingVariables();
}

/**
 * True when a value counts as "filled" for {if} blocks and line dropping.
 */
function whatsAppValueFilled(array $vars, string $name): bool
{
    return isset($vars[$name]) && trim((string) $vars[$name]) !== '';
}

/**
 * Fill a template body with values. See the syntax notes at the top of this file.
 * Used by: renderWhatsAppTemplateByKey(), modules/Bookings/view.php, modules/Clients/*
 *
 * @param string               $body
 * @param array<string,string> $vars  placeholder name => value
 */
function renderWhatsAppTemplate(string $body, array $vars): string
{
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $body));
    $stack = [];
    $out = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if (preg_match('/^\{if (!?)(\w+)\}$/', $trimmed, $m)) {
            $filled = whatsAppValueFilled($vars, $m[2]);
            $stack[] = ['cond' => $m[1] === '!' ? !$filled : $filled, 'else' => false];
            continue;
        }
        if ($trimmed === '{else}') {
            if ($stack) {
                $stack[count($stack) - 1]['else'] = true;
            }
            continue;
        }
        if ($trimmed === '{/if}') {
            array_pop($stack);
            continue;
        }

        $active = true;
        foreach ($stack as $level) {
            if ($level['cond'] === $level['else']) {
                $active = false;
                break;
            }
        }
        if (!$active) {
            continue;
        }

        $dropLine = false;
        $rendered = preg_replace_callback('/\{(\w+)\}/', function ($m) use ($vars, &$dropLine) {
            $value = isset($vars[$m[1]]) ? (string) $vars[$m[1]] : '';
            if (trim($value) === '') {
                $dropLine = true;
                return '';
            }
            return $value;
        }, $line);

        if (!$dropLine) {
            $out[] = $rendered;
        }
    }

    $text = preg_replace("/\n{3,}/", "\n\n", implode("\n", $out));
    return trim($text);
}

/**
 * Names used in a template body (placeholders and {if} conditions).
 *
 * @return string[]
 */
function getWhatsAppBodyVariables(string $body): array
{
    preg_match_all('/\{(?:if\s+!?)?(\w+)\}/', $body, $matches);
    $names = array_diff($matches[1], ['else', 'if']);
    return array_values(array_unique($names));
}

/**
 * True when every placeholder in the body only needs the client, so the template
 * can be sent from the Clients pages (no booking involved).
 */
function isClientLevelWhatsAppBody(string $body): bool
{
    return empty(array_diff(getWhatsAppBodyVariables($body), array_keys(getWhatsAppClientVariables())));
}

/**
 * Check a template body before saving it.
 * Used by: modules/WhatsApp/api/index.php
 *
 * @return string|null  Error message, or null when the body is fine
 */
function validateWhatsAppTemplateBody(string $body, string $placement): ?string
{
    $allowed = getWhatsAppVariables($placement);
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $body));
    $stack = [];

    foreach ($lines as $index => $line) {
        $lineNo = $index + 1;
        $trimmed = trim($line);

        if (preg_match('/^\{if (!?)(\w+)\}$/', $trimmed, $m)) {
            if (!isset($allowed[$m[2]])) {
                return 'Line ' . $lineNo . ': unknown name {' . $m[2] . '} in {if}.';
            }
            $stack[] = false;
            continue;
        }
        if ($trimmed === '{else}') {
            if (!$stack) {
                return 'Line ' . $lineNo . ': {else} without a matching {if}.';
            }
            if ($stack[count($stack) - 1]) {
                return 'Line ' . $lineNo . ': an {if} block can only have one {else}.';
            }
            $stack[count($stack) - 1] = true;
            continue;
        }
        if ($trimmed === '{/if}') {
            if (!$stack) {
                return 'Line ' . $lineNo . ': {/if} without a matching {if}.';
            }
            array_pop($stack);
            continue;
        }
        if (preg_match('/\{(if\s|else\}|\/if\})/', $line)) {
            return 'Line ' . $lineNo . ': {if ...}, {else} and {/if} must each be on a line of their own.';
        }

        preg_match_all('/\{(\w+)\}/', $line, $matches);
        foreach ($matches[1] as $name) {
            if (!isset($allowed[$name])) {
                return 'Line ' . $lineNo . ': unknown placeholder {' . $name . '}.';
            }
        }
    }

    if ($stack) {
        return 'Missing {/if}: an {if} block is not closed.';
    }
    return null;
}

// --- Template storage ---

/**
 * One template by its key, or null when it does not exist.
 * Used by: renderWhatsAppTemplateByKey()
 */
function getWhatsAppTemplate(PDO $pdo, string $key): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM whatsapp_templates WHERE template_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * One template by its id, or null when it does not exist.
 * Used by: modules/WhatsApp/edit.php, modules/WhatsApp/api/index.php
 */
function getWhatsAppTemplateById(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM whatsapp_templates WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Every template, in menu order.
 * Used by: modules/WhatsApp/index.php
 */
function getWhatsAppTemplates(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT * FROM whatsapp_templates ORDER BY sort_order ASC, label ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Active templates that appear in the WhatsApp menu. Returns [] (and logs) when the
 * table is missing, so pages still load before the migration has been run.
 * Used by: modules/Bookings/view.php, modules/Clients/*
 */
function getWhatsAppMenuTemplates(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT * FROM whatsapp_templates WHERE placement = 'booking_menu' AND active = 1 ORDER BY sort_order ASC, label ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError('WHATSAPP', 'Failed to load WhatsApp menu templates', ['error' => $e->getMessage()]);
        return [];
    }
}

/**
 * Every booking-menu message filled in for one booking, in the booking's language, ready for
 * renderWhatsAppMenu(). Includes the client-level ones (bank details, pickup message, ...).
 * Used by: modules/Bookings/view.php, modules/Clients/bookings.php
 *
 * @param array $booking  Booking row (bookings.language) with client_name, client_phone,
 *                        pickup_location, destination and, if allocated, driver_name / driver_phone
 * @return array<int,array{key:string,label:string,text:string}>
 */
function getWhatsAppBookingMessages(PDO $pdo, array $booking): array
{
    $vars = buildBookingMessageVars($pdo, $booking);
    $language = normalizeWhatsAppLanguage($booking['language'] ?? 'en');
    $messages = [];
    foreach (getWhatsAppMenuTemplates($pdo) as $template) {
        $messages[] = [
            'key'   => $template['template_key'],
            'label' => $template['label'],
            'text'  => renderWhatsAppTemplate(getWhatsAppTemplateBody($template, $language), $vars),
        ];
    }
    return $messages;
}

/**
 * Menu templates that need nothing but the client, for the Clients pages.
 */
function getWhatsAppClientMenuTemplates(PDO $pdo): array
{
    return array_values(array_filter(
        getWhatsAppMenuTemplates($pdo),
        fn($tpl) => isClientLevelWhatsAppBody($tpl['body'])
    ));
}

/**
 * Render a stored template by key, in the given language. Logs and returns '' when it
 * cannot be found.
 * Used by: createWhatsAppMessage() and the other message builders below
 */
function renderWhatsAppTemplateByKey(PDO $pdo, string $key, array $vars, string $language = 'en'): string
{
    try {
        $template = getWhatsAppTemplate($pdo, $key);
    } catch (PDOException $e) {
        logError('WHATSAPP', 'Failed to load WhatsApp template', ['key' => $key, 'error' => $e->getMessage()]);
        return '';
    }
    if ($template === null) {
        logError('WHATSAPP', 'WhatsApp template not found', ['key' => $key]);
        return '';
    }
    return renderWhatsAppTemplate(getWhatsAppTemplateBody($template, $language), $vars);
}

/**
 * A unique template_key for a new custom template, built from its label.
 * Used by: modules/WhatsApp/api/index.php
 */
function generateWhatsAppTemplateKey(PDO $pdo, string $label): string
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $label), '_'));
    $base = substr('custom_' . ($slug !== '' ? $slug : 'template'), 0, 44);
    $key = $base;
    $suffix = 2;
    while (getWhatsAppTemplate($pdo, $key) !== null) {
        $key = $base . '_' . $suffix++;
    }
    return $key;
}

// --- Placeholder values ---

/**
 * Bank / EFT details text (the invoice_payment_details system variable), read once per request.
 */
function getWhatsAppPaymentDetails(PDO $pdo): string
{
    static $details = null;
    if ($details === null) {
        $details = trim((string) getSystemVariable($pdo, 'invoice_payment_details'));
    }
    return $details;
}

/**
 * Values for the client-level placeholders.
 * Used by: buildBookingMessageVars(), getWhatsAppClientMessages()
 *
 * @return array<string,string>
 */
function buildClientMessageVars(PDO $pdo, string $clientName, string $clientPhone = ''): array
{
    return [
        'client_name'     => trim($clientName),
        'client_phone'    => trim($clientPhone),
        'business_owner'  => BUSINESS_OWNER,
        'business_name'   => BUSINESS_NAME,
        'payment_details' => getWhatsAppPaymentDetails($pdo),
    ];
}

/**
 * Render the client-level templates for one client (Clients pages), in that client's language.
 *
 * @param array  $templates  From getWhatsAppClientMenuTemplates()
 * @param string $language   contacts.language ('en' or 'af')
 * @return array<int,array{key:string,label:string,text:string}>
 */
function getWhatsAppClientMessages(PDO $pdo, array $templates, string $clientName, string $clientPhone = '', string $language = 'en'): array
{
    $vars = buildClientMessageVars($pdo, $clientName, $clientPhone);
    $messages = [];
    foreach ($templates as $tpl) {
        $messages[] = [
            'key'   => $tpl['template_key'],
            'label' => $tpl['label'],
            'text'  => renderWhatsAppTemplate(getWhatsAppTemplateBody($tpl, $language), $vars),
        ];
    }
    return $messages;
}

/**
 * Values for every booking placeholder. Anything the booking row does not have comes out empty.
 * Used by: the booking message builders below, modules/Bookings/view.php
 *
 * @param array $b  Booking row with pickup_location / destination already resolved for was_swapped
 * @return array<string,string>
 */
function buildBookingMessageVars(PDO $pdo, array $b): array
{
    $tz = new DateTimeZone(TIME_ZONE);

    $date = '';
    $time = '';
    if (!empty($b['trip_date']) && !empty($b['start_time'])) {
        $start = new DateTime($b['trip_date'] . ' ' . $b['start_time'], $tz);
        $date = $start->format('d/m/y');
        $time = $start->format('H:i');
    }

    $clientName = trim($b['client_name'] ?? '');
    $pickup = (string) ($b['pickup_location'] ?? '');
    $cost = (float) ($b['cost'] ?? 0);

    // Passenger lines only when someone other than the client is being collected
    $passengerName = trim($b['passenger_name'] ?? '');
    $passengerPhone = trim($b['passenger_phone'] ?? '');
    if ($passengerName === '' || strcasecmp($passengerName, $clientName) === 0) {
        $passengerName = '';
        $passengerPhone = '';
    }

    $lang = normalizeWhatsAppLanguage($b['language'] ?? 'en');
    $notes = appendAirportPickupNotice($pdo, $pickup, $b['description'] ?? '', $lang);
    $notes = appendAfterHoursNotice($b['after_hours_charge'] ?? null, $notes, $lang, $pdo);

    $isUpdate = !empty($b['updated_at']);
    $updatedAt = $isUpdate ? (new DateTime($b['updated_at'], $tz))->format('d/m/y H:i') : '';
    $createdAt = (!$isUpdate && !empty($b['date_created']))
        ? (new DateTime($b['date_created'], $tz))->format('d/m/y H:i')
        : '';

    $flightNumber = trim($b['flight_number'] ?? '');
    $waze = buildWazeUrls($b);

    $settlement = calculateDriverSettlement($b);
    $fee = $settlement['fee'];
    $hasBookingFee = !$settlement['no_booking_fee'] && $fee !== null && $fee > 0;

    return buildClientMessageVars($pdo, $clientName, (string) ($b['client_phone'] ?? '')) + [
        'date'              => $date,
        'time'              => $time,
        'pickup'            => $pickup,
        'destination'       => (string) ($b['destination'] ?? ''),
        'cost'              => $cost > 0 ? 'R' . number_format($cost, 2) : '',
        'trip_cost'         => 'R' . number_format($cost, 2),
        'payment_received'  => !empty($b['payment_received']) ? '1' : '',
        'flight_number'     => $flightNumber,
        'flightradar_url'   => $flightNumber !== '' ? buildFlightradarUrl($flightNumber) : '',
        'notes'             => $notes,
        'passenger_name'    => $passengerName,
        'passenger_phone'   => $passengerPhone,
        'driver_name'       => trim($b['driver_name'] ?? ''),
        'driver_phone'      => trim($b['driver_phone'] ?? ''),
        'is_update'         => $isUpdate ? '1' : '',
        'updated_at'        => $updatedAt,
        'created_at'        => $createdAt,
        'driver_notes'      => (string) ($b['driver_notes'] ?? ''),
        'waze_pickup'       => $waze['pickup'],
        'waze_destination'  => $waze['destination'],
        'is_eft'            => $settlement['is_eft'] ? '1' : '',
        'no_booking_fee'    => $settlement['no_booking_fee'] ? '1' : '',
        'has_booking_fee'   => $hasBookingFee ? '1' : '',
        'booking_fee'       => $fee !== null ? 'R' . number_format($fee, 2) : '',
        'settlement_amount' => 'R' . number_format($settlement['amount'], 2),
    ];
}

/**
 * Values for the prebooking reminder placeholders.
 *
 * @param array $d  Needs client_name, trip_date. Optional: start_time, original_pickup,
 *                  original_destination, was_swapped, cost, description, passenger_name, passenger_phone
 * @return array<string,string>
 */
function buildPrebookingMessageVars(array $d): array
{
    $tz = new DateTimeZone(TIME_ZONE);
    $date = new DateTime($d['trip_date'], $tz);
    $tbc = normalizeWhatsAppLanguage($d['language'] ?? 'en') === 'af' ? 'Nog te bevestig' : 'TBC';

    $time = !empty($d['start_time'])
        ? (new DateTime($d['trip_date'] . ' ' . $d['start_time'], $tz))->format('H:i')
        : $tbc;

    $swapped = !empty($d['was_swapped']);
    $rawPickup = $d['original_pickup'] ?? '';
    $rawDest = $d['original_destination'] ?? '';
    $pickup = $swapped ? $rawDest : $rawPickup;
    $destination = $swapped ? $rawPickup : $rawDest;

    $cost = isset($d['cost']) ? (float) $d['cost'] : 0;

    $clientName = trim($d['client_name'] ?? '');
    $passengerName = trim($d['passenger_name'] ?? '');
    $passengerPhone = trim($d['passenger_phone'] ?? '');
    if ($passengerName === '' || strcasecmp($passengerName, $clientName) === 0) {
        $passengerName = '';
        $passengerPhone = '';
    }

    return [
        'client_name'     => $clientName,
        'date'            => $date->format('d/m/y'),
        'time'            => $time,
        'pickup'          => $pickup !== '' ? $pickup : $tbc,
        'destination'     => $destination !== '' ? $destination : $tbc,
        'cost'            => $cost > 0 ? 'R' . number_format($cost, 2) : $tbc,
        'notes'           => (string) ($d['description'] ?? ''),
        'passenger_name'  => $passengerName,
        'passenger_phone' => $passengerPhone,
        'business_owner'  => BUSINESS_OWNER,
        'business_name'   => BUSINESS_NAME,
    ];
}

// --- Combined message (several bookings of one client) ---

/**
 * The {trips} text: bookings grouped by route (pickup -> destination), the route printed once
 * with one line per date and time under it. A return trip has its own route, so it is its own
 * block. Blocks are in the order of their earliest booking; dates are in time order.
 * Used by: getWhatsAppGroupMessage()
 *
 * @param array[] $bookings  Rows with trip_date, start_time and pickup_location / destination
 *                           already resolved for was_swapped
 * When the trips do not all cost the same, each date line ends with its own price (a trip with
 * no cost shows none). When they all cost the same, no prices are shown here ({cost_each} covers it).
 * @param string  $language  'en' or 'af' (only the word between date and time changes)
 */
function buildWhatsAppTripsText(array $bookings, string $language = 'en'): string
{
    $showCosts = !hasSameWhatsAppTripCost($bookings);
    $tz = new DateTimeZone(TIME_ZONE);
    $atWord = normalizeWhatsAppLanguage($language) === 'af' ? 'om' : 'at';

    usort($bookings, fn($a, $b) => [(string) ($a['trip_date'] ?? ''), (string) ($a['start_time'] ?? '')]
        <=> [(string) ($b['trip_date'] ?? ''), (string) ($b['start_time'] ?? '')]);

    $routes = [];
    foreach ($bookings as $b) {
        $pickup = trim((string) ($b['pickup_location'] ?? ''));
        $destination = trim((string) ($b['destination'] ?? ''));
        $routeKey = mb_strtolower($pickup) . '|' . mb_strtolower($destination);

        if (!isset($routes[$routeKey])) {
            $routes[$routeKey] = ['title' => $pickup . ' -> ' . $destination, 'lines' => []];
        }

        if (empty($b['trip_date'])) {
            continue;
        }
        $start = new DateTime($b['trip_date'] . ' ' . (!empty($b['start_time']) ? $b['start_time'] : '00:00:00'), $tz);
        $line = '📅 ' . $start->format('d/m/y');
        if (!empty($b['start_time'])) {
            $line .= ' ' . $atWord . ' ' . $start->format('H:i');
        }
        if ($showCosts && (float) ($b['cost'] ?? 0) > 0) {
            $line .= ' – R' . number_format((float) $b['cost'], 2);
        }
        $routes[$routeKey]['lines'][] = $line;
    }

    $blocks = [];
    foreach ($routes as $route) {
        if ($route['lines']) {
            $blocks[] = '📍 ' . $route['title'] . "\n" . implode("\n", $route['lines']);
        }
    }
    return implode("\n\n", $blocks);
}

/**
 * True when every booking costs the same (to the cent).
 * Used by: buildWhatsAppTripsText(), buildGroupMessageVars()
 */
function hasSameWhatsAppTripCost(array $bookings): bool
{
    $costs = array_unique(array_map(fn($b) => (int) round(((float) ($b['cost'] ?? 0)) * 100), $bookings));
    return count($costs) <= 1;
}

/**
 * Values for the combined bookings message placeholders.
 * Used by: getWhatsAppGroupMessage()
 *
 * @param array[] $bookings  Same rows as buildWhatsAppTripsText()
 * @return array<string,string>
 */
function buildGroupMessageVars(PDO $pdo, string $clientName, string $clientPhone, array $bookings, string $language = 'en'): array
{
    // Same cost on every trip: one "each" cost. Different costs: the prices go on the date lines
    // ({trips}) and the template shows the total instead. The unused one is left empty so its
    // template line is dropped.
    $totalCents = array_sum(array_map(fn($b) => (int) round(((float) ($b['cost'] ?? 0)) * 100), $bookings));
    $same = hasSameWhatsAppTripCost($bookings);
    $costEach = ($same && $totalCents > 0) ? 'R' . number_format($totalCents / count($bookings) / 100, 2) : '';
    $costTotal = (!$same && $totalCents > 0) ? 'R' . number_format($totalCents / 100, 2) : '';

    return buildClientMessageVars($pdo, $clientName, $clientPhone) + [
        'trips'         => buildWhatsAppTripsText($bookings, $language),
        'booking_count' => (string) count($bookings),
        'cost_each'     => $costEach,
        'cost_total'    => $costTotal,
    ];
}

/**
 * The combined message for several bookings of one client, in the client's language.
 * Returns '' when there are no bookings or the 'booking_group' template is missing (logged).
 * Used by: modules/Clients/bookings.php, modules/Clients/api (Piece 2)
 *
 * @param array   $client    contacts row (name, phone, language)
 * @param array[] $bookings  Rows with trip_date, start_time, cost and pickup_location / destination
 *                           already resolved for was_swapped
 */
function getWhatsAppGroupMessage(PDO $pdo, array $client, array $bookings): string
{
    if (!$bookings) {
        return '';
    }
    $language = normalizeWhatsAppLanguage($client['language'] ?? 'en');
    $vars = buildGroupMessageVars($pdo, (string) ($client['name'] ?? ''), (string) ($client['phone'] ?? ''), $bookings, $language);
    return renderWhatsAppTemplateByKey($pdo, 'booking_group', $vars, $language);
}

// --- Message builders (templates: confirmation, evening_confirmation, prebooking_reminder, driver_notification) ---

/**
 * The booking confirmation message (new or updated booking), in the booking's language.
 * Used by: modules/Bookings/api/index.php
 *
 * @param array $bookingDetails  Booking row (with bookings.language) with pickup_location / destination resolved for was_swapped
 */
function createWhatsAppMessage(PDO $pdo, array $bookingDetails): string
{
    return renderWhatsAppTemplateByKey(
        $pdo,
        'confirmation',
        buildBookingMessageVars($pdo, $bookingDetails),
        normalizeWhatsAppLanguage($bookingDetails['language'] ?? 'en')
    );
}

/**
 * The evening confirmation message for tomorrow's booking, in the booking's language.
 * Used by: modules/Bookings/api/index.php (tomorrows_bookings)
 */
function createEveningConfirmationMessage(PDO $pdo, array $bookingDetails): string
{
    return renderWhatsAppTemplateByKey(
        $pdo,
        'evening_confirmation',
        buildBookingMessageVars($pdo, $bookingDetails),
        normalizeWhatsAppLanguage($bookingDetails['language'] ?? 'en')
    );
}

/**
 * The reminder message for a prebooking, in the client's language (a prebooking has no
 * language of its own, so the caller passes contacts.language as 'language').
 * Used by: modules/Prebookings/index.php, modules/Prebookings/api/index.php
 */
function createPrebookingWhatsAppMessage(PDO $pdo, array $prebookingDetails): string
{
    return renderWhatsAppTemplateByKey(
        $pdo,
        'prebooking_reminder',
        buildPrebookingMessageVars($prebookingDetails),
        normalizeWhatsAppLanguage($prebookingDetails['language'] ?? 'en')
    );
}

/**
 * The message sent to an allocated driver about a booking. Always English.
 * Used by: modules/Bookings/view.php
 */
function createDriverBookingMessage(PDO $pdo, array $bookingDetails): string
{
    $vars = buildBookingMessageVars($pdo, $bookingDetails);
    if ($vars['driver_name'] === '') {
        $vars['driver_name'] = 'Driver';
    }
    return renderWhatsAppTemplateByKey($pdo, 'driver_notification', $vars);
}

// --- WhatsApp menu ---

/**
 * HTML for the "💬 WhatsApp ▾" dropdown: one item per message plus a blank message.
 * Opening / closing and logging are handled by assets/js/whatsapp-menu.js, which the page must load.
 * Used by: modules/Bookings/view.php, modules/Clients/bookings.php
 *
 * @param string $waPhone     Digits-only number from formatPhoneNumberForWhatsApp()
 * @param array  $messages    Each: key, label, text
 * @param string $buttonClass CSS classes for the button (page-level or table-level style)
 * @param string $buttonLabel Text on the button
 */
function renderWhatsAppMenu(string $waPhone, array $messages, ?int $bookingId, ?int $contactId, string $buttonClass = 'page-action-btn whatsapp', string $buttonLabel = '💬 WhatsApp ▾'): string
{
    if ($waPhone === '') {
        return '<button type="button" class="' . e($buttonClass) . '" disabled title="No valid WhatsApp number on file">' . e($buttonLabel) . '</button>';
    }

    $ids = ($bookingId !== null ? ' data-booking-id="' . (int) $bookingId . '"' : '')
        . ($contactId !== null ? ' data-contact-id="' . (int) $contactId . '"' : '');

    $html = '<div class="at-wa-menu">';
    $html .= '<button type="button" class="' . e($buttonClass) . ' at-wa-menu-toggle" aria-haspopup="true" aria-expanded="false">' . e($buttonLabel) . '</button>';
    $html .= '<div class="at-wa-menu-list" hidden>';
    foreach ($messages as $m) {
        if (trim($m['text']) === '') {
            continue; // e.g. bank details not filled in yet — nothing to send
        }
        $html .= '<a class="at-wa-menu-item" target="_blank" rel="noopener"'
            . ' href="https://wa.me/' . e($waPhone) . '?text=' . e(urlencode($m['text'])) . '"'
            . ' data-wa-key="' . e($m['key']) . '" data-wa-msg="' . e($m['text']) . '"' . $ids . '>'
            . e($m['label']) . '</a>';
    }
    $html .= '<a class="at-wa-menu-item" target="_blank" rel="noopener"'
        . ' href="https://wa.me/' . e($waPhone) . '"'
        . ' data-wa-key="message" data-wa-msg=""' . $ids . '>Blank message</a>';
    $html .= '</div></div>';

    return $html;
}
