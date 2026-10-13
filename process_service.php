<?php
/**
 * process_service.php
 * Receives the service request (POST), validates it on the server,
 * calculates the cost independently of JavaScript and shows a confirmation.
 */

date_default_timezone_set('Africa/Nairobi');

/* ---------- Service and vehicle data (keep in sync with js/script.js) ---------- */
const SERVICES = [
    'oil_change'         => ['label' => 'Engine Oil Change',  'price' => 3000],
    'brake_inspection'   => ['label' => 'Brake Inspection',   'price' => 2000],
    'wheel_alignment'    => ['label' => 'Wheel Alignment',    'price' => 2500],
    'computer_diagnosis' => ['label' => 'Computer Diagnosis', 'price' => 3500],
    'full_service'       => ['label' => 'Full Service',       'price' => 8000],
    'tyre_balancing'     => ['label' => 'Tyre Balancing',     'price' => 2000],  // individualization requirement (digit 4)
];
const VEHICLE_TYPES = [
    'saloon' => 'Saloon Car',
    'suv'    => 'SUV',
    'pickup' => 'Pickup',
    'van'    => 'Van',
];
const OLDEST_YEAR = 1980;

/* ---------- Helper functions ---------- */

// Reads a trimmed POST value, or an empty string if it is missing.
function postValue(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
}

// Escapes text before printing it in HTML (prevents XSS).
function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function ksh(int $amount): string
{
    return 'KSh ' . number_format($amount);
}

// Renders the page wrapper (header + footer) around the given content.
function renderPage(string $title, string $content): void
{
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . e($title) . ' | AutoCare Garage</title>'
       . '<link rel="stylesheet" href="css/style.css"></head><body>'
       . '<header class="site-header"><div class="container">'
       . '<a class="logo" href="index.html">AutoCare Garage</a>'
       . '<nav><ul class="nav-links"><li><a href="index.html">Home</a></li>'
       . '<li><a class="active" href="service.html">Book a Service</a></li></ul></nav>'
       . '</div></header><main class="container">' . $content . '</main>'
       . '<footer class="site-footer">&copy; 2026 AutoCare Garage. All rights reserved.</footer>'
       . '</body></html>';
}

/* ---------- 1. Only accept POST requests ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderPage('Invalid Request',
        '<div class="confirmation failed"><h1>NO REQUEST RECEIVED</h1>'
      . '<p class="thanks">Please use the service booking form.</p>'
      . '<p class="thanks"><a class="btn" href="service.html">Go to Booking Form</a></p></div>');
    exit;
}

/* ---------- 2. Retrieve submitted values ---------- */
$customerName = postValue('customer_name');
$phone        = postValue('phone');
$email        = postValue('email');
$regNumber    = strtoupper(postValue('reg_number'));
$vehicleType  = postValue('vehicle_type');
$vehicleModel = postValue('vehicle_model');
$vehicleYear  = filter_var(postValue('vehicle_year'), FILTER_VALIDATE_INT);
$serviceDate  = postValue('service_date');
$jsTotal      = (int) postValue('js_total');
$submitted    = (isset($_POST['services']) && is_array($_POST['services'])) ? $_POST['services'] : [];

/* ---------- 3. Server-side validation ---------- */
$errors = [];
$currentYear = (int) date('Y');

if (!preg_match("/^[\p{L} .'-]{2,}$/u", $customerName)) {
    $errors[] = 'A valid customer name is required.';
}
if (!preg_match('/^\d{10}$/', $phone)) {
    $errors[] = 'Phone number must contain exactly 10 digits.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if (!preg_match('/^[A-Za-z0-9 -]{4,10}$/', $regNumber)) {
    $errors[] = 'A valid vehicle registration number is required.';
}
if (!isset(VEHICLE_TYPES[$vehicleType])) {
    $errors[] = 'Please select a valid vehicle type.';
}
if ($vehicleModel === '') {
    $errors[] = 'Vehicle model is required.';
}
if ($vehicleYear === false || $vehicleYear < OLDEST_YEAR || $vehicleYear > $currentYear) {
    $errors[] = 'Year of manufacture must be between ' . OLDEST_YEAR . ' and ' . $currentYear . '.';
}

$dateObject = DateTime::createFromFormat('Y-m-d', $serviceDate);
if (!$dateObject || $dateObject->format('Y-m-d') !== $serviceDate) {
    $errors[] = 'A valid preferred service date is required.';
} elseif ($serviceDate < date('Y-m-d')) {
    $errors[] = 'Service date cannot be in the past.';
}

// Keep only known services (ignores duplicates and unknown values)
$selectedServices = [];
foreach ($submitted as $key) {
    if (is_string($key) && isset(SERVICES[$key])) {
        $selectedServices[$key] = SERVICES[$key];
    } else {
        $errors[] = 'An unknown service was submitted.';
        break;
    }
}
if (empty($selectedServices)) {
    $errors[] = 'Select at least one service.';
}

if (!empty($errors)) {
    $list = '';
    foreach (array_unique($errors) as $message) {
        $list .= '<li>' . e($message) . '</li>';
    }
    renderPage('Request Error',
        '<div class="confirmation failed"><h1>REQUEST NOT COMPLETED</h1>'
      . '<p>Please correct the following and try again:</p><ul>' . $list . '</ul>'
      . '<p class="thanks"><a class="btn" href="service.html">Back to Booking Form</a></p></div>');
    exit;
}

/* ---------- 4. Independent cost calculation (JavaScript total is NOT trusted) ---------- */
$totalCost = 0;
$serviceList = '';
foreach ($selectedServices as $service) {
    $totalCost += $service['price'];
    $serviceList .= '<li>' . e($service['label']) . ' - ' . ksh($service['price']) . '</li>';
}
$reference = 'AC' . date('ymd') . random_int(1000, 9999);

/* ---------- 5. Build the confirmation ---------- */
$rows = [
    'Reference'        => $reference,
    'Customer'         => $customerName,
    'Phone'            => $phone,
    'Email'            => $email,
    'Vehicle'          => $regNumber,
    'Vehicle Type'     => VEHICLE_TYPES[$vehicleType],
    'Model'            => $vehicleModel . ' (' . $vehicleYear . ')',
    'Service Date'     => $dateObject->format('d F Y'),
];

$table = '<table>';
foreach ($rows as $label => $value) {
    $table .= '<tr><td>' . e($label) . '</td><td>' . e($value) . '</td></tr>';
}
$table .= '<tr class="total-row"><td>ESTIMATED SERVICE COST</td><td>' . ksh($totalCost) . '</td></tr></table>';

$note = ($jsTotal !== $totalCost)
    ? '<p class="info">The cost shown was recalculated by the server and differs from the page estimate.</p>'
    : '';

renderPage('Service Request Received',
    '<div class="confirmation success"><h1>SERVICE REQUEST RECEIVED</h1>' . $table
  . '<h3>Selected Services:</h3><ul>' . $serviceList . '</ul>' . $note
  . '<p class="thanks">Thank you for choosing AutoCare Garage, ' . e($customerName) . '.</p>'
  . '<p class="thanks"><a class="btn" href="index.html">Back to Home</a></p></div>');
