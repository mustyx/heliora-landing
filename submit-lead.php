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
require_once __DIR__ . '/includes/meta-capi.php';

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

function respond(bool $success, string $message, int $code = 200, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
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
    respond(true, 'Thank you. We will be in touch shortly.');
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

// ── Deduplication keys ───────────────────────────────────
// Generated HERE, at the single point where a submission becomes real.
// event_id is shared with the browser Lead event so Meta counts one
// conversion; lead_uid is the opaque public id used across Meta, GA4 and
// the CRM so the auto-increment id is never exposed.
$leadUid = bin2hex(random_bytes(16));                  // 32 hex chars
try {
    $eventId = sprintf(
        '%08x-%04x-4%03x-%04x-%012x',
        random_int(0, 0xffffffff), random_int(0, 0xffff), random_int(0, 0xfff),
        random_int(0, 0x3fff) | 0x8000, random_int(0, 0xffffffffffff)
    );
} catch (Exception $e) {
    $eventId = str_replace('.', '', uniqid('', true));
}

// Browser identifiers. _fbc may be absent on first click; rebuild it from
// fbclid when we have one, which is what the Pixel would have done.
$fbp = sanitise(substr($_POST['fbp'] ?? '', 0, 128));
$fbc = sanitise(substr($_POST['fbc'] ?? '', 0, 255));
$fbclid = sanitise(substr($_POST['fbclid'] ?? '', 0, 255));
if ($fbc === '' && $fbclid !== '') {
    $fbc = 'fb.1.' . (time() * 1000) . '.' . $fbclid;
}

$consentState = in_array($_POST['consent_state'] ?? '', ['accepted','declined','unset'], true)
    ? $_POST['consent_state'] : 'unset';

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
    // ── added 9 Aug 2026: dedup keys + full attribution ──
    'lead_uid'         => $leadUid,
    'event_id'         => $eventId,
    'utm_content'      => sanitise(substr($_POST['utm_content']      ?? '', 0, 255)),
    'utm_term'         => sanitise(substr($_POST['utm_term']         ?? '', 0, 255)),
    'meta_campaign_id' => sanitise(substr($_POST['meta_campaign_id'] ?? '', 0, 64)),
    'meta_adset_id'    => sanitise(substr($_POST['meta_adset_id']    ?? '', 0, 64)),
    'meta_ad_id'       => sanitise(substr($_POST['meta_ad_id']       ?? '', 0, 64)),
    'placement'        => sanitise(substr($_POST['placement']        ?? '', 0, 100)),
    'site_source_name' => sanitise(substr($_POST['site_source_name'] ?? '', 0, 100)),
    'fbp'              => $fbp,
    'fbc'              => $fbc,
    'gclid'            => sanitise(substr($_POST['gclid']     ?? '', 0, 255)),
    'li_fat_id'        => sanitise(substr($_POST['li_fat_id'] ?? '', 0, 255)),
    'consent_state'    => $consentState,
];

// ── Save to database ─────────────────────────────────────
try {
    $pdo = getDB();
    $stmt = $pdo->prepare('
        INSERT INTO leads
          (first_name, last_name, email, phone, company, service, project_scale,
           client_type, message, source, page_url, utm_source, utm_medium, utm_campaign,
           ip_address, user_agent,
           lead_uid, event_id, utm_content, utm_term, meta_campaign_id, meta_adset_id,
           meta_ad_id, placement, site_source_name, fbp, fbc, gclid, li_fat_id, consent_state)
        VALUES
          (:first_name, :last_name, :email, :phone, :company, :service, :project_scale,
           :client_type, :message, :source, :page_url, :utm_source, :utm_medium, :utm_campaign,
           :ip_address, :user_agent,
           :lead_uid, :event_id, :utm_content, :utm_term, :meta_campaign_id, :meta_adset_id,
           :meta_ad_id, :placement, :site_source_name, :fbp, :fbc, :gclid, :li_fat_id, :consent_state)
    ');
    $stmt->execute($lead);
    $lead['id'] = (int) $pdo->lastInsertId();

} catch (PDOException $e) {
    error_log('Lead save failed: ' . $e->getMessage());
    respond(false, 'An error occurred. Please email us directly at contact@helioraconsulting.com', 500);
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

// ── Meta Conversions API ─────────────────────────────────
// The server is the source of truth for the Lead event. The browser fires
// the same event with the same event_id purely so Meta can deduplicate and
// enrich; if the browser event is lost to the redirect, an ad blocker or
// ITP, this one still lands. Failures here never affect the visitor.
$capi = ['status' => 'not_attempted', 'detail' => ''];
try {
    $capi = sendMetaLeadEvent($lead);
} catch (Throwable $e) {
    error_log('Meta CAPI threw: ' . $e->getMessage());
    $capi = ['status' => 'error', 'detail' => $e->getMessage()];
}

try {
    $pdo->prepare('UPDATE leads SET capi_status = ?, capi_response = ? WHERE id = ?')
        ->execute([$capi['status'], substr($capi['detail'], 0, 2000), $lead['id']]);
} catch (Exception $e) {
    error_log('CAPI status write failed: ' . $e->getMessage());
}

// ── Success response ──────────────────────────────────────
// event_id goes back to the browser so fbq can fire with the SAME id.
// Never let the browser invent one - that would break deduplication and
// double-count every lead.
respond(
    true,
    'Thank you. We will be in touch within 24 hours. Please check your email for confirmation.',
    200,
    ['event_id' => $lead['event_id'], 'lead_uid' => $lead['lead_uid']]
);
