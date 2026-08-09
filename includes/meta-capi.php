<?php
/* ════════════════════════════════════════════════════════════════════
   Heliora Consulting — Meta Conversions API
   ────────────────────────────────────────────────────────────────────
   Sends the server-confirmed Lead event to Meta.

   WHY THIS EXISTS
   The browser-only fbq('track','Lead') that used to run just before the
   redirect to thank-you.html was unreliable: the navigation frequently
   cancelled the request, and ad blockers and ITP suppressed the rest.
   Meta was therefore being told about a fraction of real leads, which is
   fatal when the account is already starved of conversion volume.

   This module makes the SERVER the source of truth. The browser still
   fires a Lead, but both events carry the SAME event_id, so Meta
   deduplicates and counts one conversion. If the browser event is lost,
   the CAPI event still lands.

   DEDUPLICATION CONTRACT
     - event_id is generated once, server-side, at accepted-form time.
     - It is returned to the browser and reused verbatim as fbq eventID.
     - Never generate an event_id in the browser.

   CONSENT
   Nothing is sent unless the visitor accepted cookies. A lead captured
   under declined consent is still stored and still emailed to the team;
   it is simply never forwarded to Meta.

   CONFIG (config/config.php, or environment variables)
     META_PIXEL_ID        e.g. '1071599118775013'
     META_CAPI_TOKEN      Events Manager > Settings > Conversions API >
                          Generate access token
     META_TEST_EVENT_CODE optional, e.g. 'TEST12345'. Set while validating
                          in Events Manager > Test events, then REMOVE it.
     META_CAPI_ENABLED    optional bool, defaults to true when a token
                          is present.
   ════════════════════════════════════════════════════════════════════ */

if (!function_exists('heliora_cfg')) {
    /**
     * Read a setting from a constant, then the environment, then a default.
     * Lets this file ship before config/config.php is updated on the server
     * without causing a fatal error.
     */
    function heliora_cfg(string $key, $default = '') {
        if (defined($key)) {
            $v = constant($key);
            if ($v !== '' && $v !== null) return $v;
        }
        $env = getenv($key);
        if ($env !== false && $env !== '') return $env;
        return $default;
    }
}

/** SHA-256 of a normalised value, as Meta requires. Empty in, empty out. */
function metaHash(?string $value): ?string {
    $value = trim((string) $value);
    if ($value === '') return null;
    return hash('sha256', mb_strtolower($value, 'UTF-8'));
}

/**
 * Normalise a phone number to E.164-ish digits before hashing.
 * Nigerian numbers are commonly entered as 0803..., +234803... or 234803...
 * Meta matches far better when these all collapse to the same string.
 */
function metaHashPhone(?string $phone): ?string {
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if ($digits === '') return null;
    // strncmp rather than str_starts_with: works on PHP 7.x too, in case the
    // host is ever rolled back below the PHP 8 the deploy notes assume.
    if (strncmp($digits, '0', 1) === 0)        $digits = '234' . substr($digits, 1);
    elseif (strncmp($digits, '234', 3) === 0)  { /* already country-coded */ }
    elseif (strlen($digits) === 10)            $digits = '234' . $digits;
    return hash('sha256', $digits);
}

/**
 * Send the Lead event to Meta.
 *
 * @param array $lead Must contain event_id. Optionally email, phone,
 *                    first_name, last_name, fbp, fbc, ip_address,
 *                    user_agent, page_url, service, client_type,
 *                    project_scale, lead_uid, consent_state.
 * @return array{status:string, detail:string}
 *         status is one of: sent | skipped_consent | skipped_config |
 *                           failed | error
 */
function sendMetaLeadEvent(array $lead): array {

    // ── Consent gate ────────────────────────────────────────────────
    if (($lead['consent_state'] ?? '') !== 'accepted') {
        return ['status' => 'skipped_consent',
                'detail' => 'Visitor did not accept marketing cookies.'];
    }

    // ── Config gate ─────────────────────────────────────────────────
    $pixelId = (string) heliora_cfg('META_PIXEL_ID', '');
    $token   = (string) heliora_cfg('META_CAPI_TOKEN', '');
    $enabled = heliora_cfg('META_CAPI_ENABLED', null);
    if ($enabled === null) $enabled = ($token !== '');
    $enabled = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);

    if (!$enabled || $pixelId === '' || $token === '') {
        return ['status' => 'skipped_config',
                'detail' => 'META_PIXEL_ID or META_CAPI_TOKEN not configured.'];
    }
    if (empty($lead['event_id'])) {
        return ['status' => 'error',
                'detail' => 'No event_id supplied - refusing to send an undeduplicable event.'];
    }

    // ── user_data: hashed PII plus unhashed browser identifiers ─────
    $userData = array_filter([
        'em'                => metaHash($lead['email']      ?? null),
        'ph'                => metaHashPhone($lead['phone']  ?? null),
        'fn'                => metaHash($lead['first_name'] ?? null),
        'ln'                => metaHash($lead['last_name']  ?? null),
        'country'           => metaHash('ng'),
        // fbp / fbc must NOT be hashed.
        'fbp'               => $lead['fbp'] ?: null,
        'fbc'               => $lead['fbc'] ?: null,
        'client_ip_address' => $lead['ip_address'] ?: null,
        'client_user_agent' => $lead['user_agent'] ?: null,
    ], fn($v) => $v !== null && $v !== '');

    // Meta requires at least one matching identifier.
    if (!array_intersect_key($userData, array_flip(['em','ph','fbp','fbc','client_ip_address']))) {
        return ['status' => 'error', 'detail' => 'No usable match keys in user_data.'];
    }

    $event = [
        'event_name'       => 'Lead',
        'event_time'       => time(),
        'event_id'         => $lead['event_id'],
        'action_source'    => 'website',
        'event_source_url' => $lead['page_url'] ?: heliora_cfg('APP_URL', 'https://helioraconsulting.com'),
        'user_data'        => $userData,
        'custom_data'      => array_filter([
            'content_name'     => 'Solar Project Readiness Review',
            'content_category' => $lead['service']       ?? null,
            'client_type'      => $lead['client_type']   ?? null,
            'project_scale'    => $lead['project_scale'] ?? null,
            'lead_uid'         => $lead['lead_uid']      ?? null,
        ], fn($v) => $v !== null && $v !== ''),
    ];

    $payload = ['data' => [$event], 'access_token' => $token];

    $testCode = (string) heliora_cfg('META_TEST_EVENT_CODE', '');
    if ($testCode !== '') $payload['test_event_code'] = $testCode;

    // ── POST to the Graph API ───────────────────────────────────────
    $url = 'https://graph.facebook.com/v21.0/' . rawurlencode($pixelId) . '/events';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        // Kept short on purpose: a slow Meta response must never delay the
        // visitor's confirmation. Losing one CAPI event is recoverable;
        // a form that appears to hang is not.
        CURLOPT_TIMEOUT        => 4,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $err) {
        error_log('[Meta CAPI] transport error: ' . $err);
        return ['status' => 'failed', 'detail' => 'curl: ' . $err];
    }
    if ($code < 200 || $code >= 300) {
        error_log('[Meta CAPI] HTTP ' . $code . ' - ' . substr((string) $body, 0, 500));
        return ['status' => 'failed', 'detail' => 'HTTP ' . $code . ': ' . substr((string) $body, 0, 300)];
    }

    $decoded = json_decode((string) $body, true);
    $received = $decoded['events_received'] ?? 0;
    if ($received < 1) {
        error_log('[Meta CAPI] accepted but events_received=0 - ' . substr((string) $body, 0, 300));
        return ['status' => 'failed', 'detail' => 'events_received=0'];
    }

    return ['status' => 'sent', 'detail' => substr((string) $body, 0, 300)];
}
