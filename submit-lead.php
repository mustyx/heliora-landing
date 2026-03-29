<?php
/* ════════════════════════════════════════════════════════
   Heliora Consulting — Lead Form Handler
   POST endpoint: submit-lead.php
   Returns JSON { success: bool, message: string }
   ════════════════════════════════════════════════════════ */

// Only accept AJAX POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    http_response_code(405);
    exit('Method Not Allowed');
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/zoho.php';

// ── Rate limiting (simple IP-based) ─────────────────────
function checkRateLimit(string $ip): bool {
    $cacheFile = sys_get_temp_dir() . '/heliora_rl_' . md5($ip) . '.json';
    $limit     = 5;      // max submissions per window
    $window    = 3600;   // seconds (1 hour)
    $now       = time();

    $data = ['count' => 0, 'start' => $now];
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true) ?: $data;
        if ($now - $data['start'] > $window) {
            $data = ['count' => 0, 'start' => $now];  // reset window
        }
    }

    if ($data['count'] >= $limit) return false;

    $data['count']++;
    file_put_contents($cacheFile, json_encode($data));
    return true;
}

// ── Helper: sanitise & validate ──────────────────────────
function sanitise(string $val): string {
    return trim(htmlspecialchars(strip_tags($val), ENT_QUOTES, 'UTF-8'));
}

function respond(bool $success, string $message, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// ── IP detection ─────────────────────────────────────────
$ip = $_SERVER['HTTP_CF_CONNECTING_IP']    // Cloudflare
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '0.0.0.0';
$ip = explode(',', $ip)[0];  // take first if multiple

// Rate limit check
if (!checkRateLimit($ip)) {
    respond(false, 'Too many requests. Please try again later.', 429);
}

// ── Validate required fields ─────────────────────────────
$required = ['first_name', 'last_name', 'email', 'service', 'message'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        respond(false, 'Please fill in all required fields.', 400);
    }
}

$email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
if (!$email) {
    respond(false, 'Please enter a valid email address.', 400);
}

// ── Honeypot anti-spam ───────────────────────────────────
if (!empty($_POST['website'])) {  // hidden field bots fill
    // Silently succeed to not reveal the honeypot
    respond(true, 'Thank you! We will be in touch shortly.');
}

// ── Build lead array ─────────────────────────────────────
$validServices = [
    'minigrid_design', 'owners_engineer', 'feasibility_energy_audit', 'shs_design',
    'esia', 'monitoring_compliance', 'ci_solar', 'multiple', 'other'
];
$service = in_array($_POST['service'], $validServices, true) ? $_POST['service'] : 'other';

$validScales = ['under_50kw','50kw_500kw','500kw_2mw','above_2mw','undecided',''];
$scale = in_array($_POST['project_scale'] ?? '', $validScales, true) ? ($_POST['project_scale'] ?? '') : '';

$validClientTypes = ['mda','epc_contractor','project_developer','development_agency','ci_client','off_grid_developer','other',''];
$clientType = in_array($_POST['client_type'] ?? '', $validClientTypes, true) ? ($_POST['client_type'] ?? '') : '';

$lead = [
    'first_name'   => sanitise(substr($_POST['first_name'],  0, 100)),
    'last_name'    => sanitise(substr($_POST['last_name'],   0, 100)),
    'email'        => $email,
    'phone'        => sanitise(substr($_POST['phone']   ?? '', 0, 50)),
    'company'      => sanitise(substr($_POST['company'] ?? '', 0, 255)),
    'service'      => $service,
    'project_scale'=> $scale,
    'client_type'  => $clientType,
    'message'      => sanitise(substr($_POST['message'],     0, 5000)),
    'source'       => sanitise(substr($_POST['source']      ?? 'website_contact_form', 0, 100)),
    'page_url'     => sanitise(substr($_POST['page_url']    ?? '', 0, 500)),
    'utm_source'   => sanitise(substr($_POST['utm_source']  ?? '', 0, 100)),
    'utm_medium'   => sanitise(substr($_POST['utm_medium']  ?? '', 0, 100)),
    'utm_campaign' => sanitise(substr($_POST['utm_campaign']?? '', 0, 100)),
    'ip_address'   => $ip,
    'user_agent'   => sanitise(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)),
];

// ── Save to database ─────────────────────────────────────
try {
    $pdo = getDB();
    $stmt = $pdo->prepare('
        INSERT INTO leads
          (first_name, last_name, email, phone, company, service, project_scale,
           client_type, message, source, page_url, utm_source, utm_medium, utm_campaign,
           ip_address, user_agent)
        VALUES
          (:first_name, :last_name, :email, :phone, :company, :service, :project_scale,
           :client_type, :message, :source, :page_url, :utm_source, :utm_medium, :utm_campaign,
           :ip_address, :user_agent)
    ');
    $stmt->execute($lead);
    $lead['id'] = (int) $pdo->lastInsertId();

} catch (PDOException $e) {
    error_log('Lead save failed: ' . $e->getMessage());
    respond(false, 'An error occurred. Please email us directly at info@helioraconsulting.com', 500);
}

// ── Send emails ───────────────────────────────────────────
$autoRespondSent = sendAutoRespond($lead);
$notifySent      = sendAdminNotification($lead);

// Log email results
try {
    $logStmt = $pdo->prepare('INSERT INTO email_log (lead_id, email_to, subject, type, status) VALUES (?,?,?,?,?)');
    $logStmt->execute([$lead['id'], $lead['email'],  'Auto-respond',    'autorespond',  $autoRespondSent ? 'sent' : 'failed']);
    $logStmt->execute([$lead['id'], ADMIN_EMAIL,     'Admin notification', 'notification', $notifySent ? 'sent' : 'failed']);
} catch (Exception $e) {
    error_log('Email log failed: ' . $e->getMessage());
}

// ── Push to Zoho CRM (non-blocking: ignore failures) ─────
try {
    pushLeadToZoho($lead);
} catch (Exception $e) {
    error_log('Zoho push failed: ' . $e->getMessage());
}

// ── Success response ──────────────────────────────────────
respond(true, 'Thank you! We\'ll be in touch within 24 hours. Please check your email for confirmation.');
