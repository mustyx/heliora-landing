<?php
/* ════════════════════════════════════════════════════════
   Heliora Consulting — Zoho CRM Integration
   Uses Zoho CRM REST API v2 with OAuth2
   ════════════════════════════════════════════════════════

   SETUP STEPS:
   1. Go to https://api-console.zoho.com
   2. Create a "Self Client" application
   3. Generate a grant token with scope:
      ZohoCRM.modules.leads.CREATE,ZohoCRM.modules.leads.READ
   4. Exchange for a refresh token (one-time, see README)
   5. Set ZOHO_CLIENT_ID, ZOHO_CLIENT_SECRET, ZOHO_REFRESH_TOKEN
      in config.php and set ZOHO_ENABLED=true
   ════════════════════════════════════════════════════════ */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Push a lead to Zoho CRM Leads module
 * Returns Zoho lead ID on success, null on failure
 */
function pushLeadToZoho(array $lead): ?string {
    if (!ZOHO_ENABLED || empty(ZOHO_CLIENT_ID)) {
        return null;
    }

    $accessToken = getZohoAccessToken();
    if (!$accessToken) {
        error_log('Zoho: Could not obtain access token');
        return null;
    }

    $payload = [
        'data' => [[
            'First_Name'   => $lead['first_name'],
            'Last_Name'    => $lead['last_name'],
            'Email'        => $lead['email'],
            'Phone'        => $lead['phone']    ?? '',
            'Company'      => $lead['company']  ?? 'Not provided',
            'Lead_Source'  => mapLeadSource($lead['utm_source'] ?? ''),
            'Description'  => $lead['message'],
            'Lead_Status'  => 'Not Contacted',
            // Custom fields — match names in YOUR Zoho account:
            'Service_Interest__c' => $lead['service']         ?? '',
            'Project_Budget__c'   => $lead['project_budget']  ?? '',
            'UTM_Source__c'       => $lead['utm_source']      ?? '',
            'UTM_Medium__c'       => $lead['utm_medium']      ?? '',
            'UTM_Campaign__c'     => $lead['utm_campaign']    ?? '',
            'Website_Source__c'   => $lead['page_url']        ?? '',
        ]]
    ];

    $ch = curl_init(ZOHO_API_DOMAIN . '/crm/v2/Leads');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Zoho-oauthtoken ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 201) {
        error_log("Zoho: API call failed. HTTP {$httpCode}. Response: {$response}");
        return null;
    }

    $data = json_decode($response, true);
    $zohoId = $data['data'][0]['details']['id'] ?? null;

    if ($zohoId) {
        // Update DB with Zoho lead ID
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare('UPDATE leads SET zoho_lead_id=?, zoho_synced_at=NOW() WHERE id=?');
            $stmt->execute([$zohoId, $lead['id']]);
        } catch (Exception $e) {
            error_log('Zoho: DB update failed: ' . $e->getMessage());
        }
    }

    return $zohoId;
}

/**
 * Get or refresh Zoho access token
 * Tokens are cached in a temp file (valid 60 min)
 */
function getZohoAccessToken(): ?string {
    $cacheFile = sys_get_temp_dir() . '/heliora_zoho_token.json';

    // Try cached token
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached && isset($cached['access_token'], $cached['expires_at'])) {
            if (time() < $cached['expires_at'] - 60) {
                return $cached['access_token'];
            }
        }
    }

    // Refresh token
    $ch = curl_init(ZOHO_ACCOUNTS_URL . '/oauth/v2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'refresh_token',
            'client_id'     => ZOHO_CLIENT_ID,
            'client_secret' => ZOHO_CLIENT_SECRET,
            'refresh_token' => ZOHO_REFRESH_TOKEN,
        ]),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return null;

    $data = json_decode($response, true);
    if (!isset($data['access_token'])) {
        error_log('Zoho: Token refresh failed: ' . $response);
        return null;
    }

    // Cache the token
    file_put_contents($cacheFile, json_encode([
        'access_token' => $data['access_token'],
        'expires_at'   => time() + (int)($data['expires_in'] ?? 3600),
    ]));

    return $data['access_token'];
}

/**
 * Map UTM source to Zoho lead source values
 */
function mapLeadSource(string $utmSource): string {
    $map = [
        'google'    => 'Google AdWords',
        'facebook'  => 'Facebook',
        'linkedin'  => 'LinkedIn',
        'instagram' => 'Internal',
        'twitter'   => 'Internal',
        'email'     => 'Email',
        'referral'  => 'Word of mouth',
        'organic'   => 'Web Download',
    ];
    $source = strtolower($utmSource);
    return $map[$source] ?? 'Web Download';
}
