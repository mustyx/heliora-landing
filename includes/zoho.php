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

    // ── Standard Zoho Lead fields ────────────────────────────
    $record = [
        'First_Name'   => $lead['first_name'],
        'Last_Name'    => $lead['last_name'],
        'Email'        => $lead['email'],
        'Phone'        => $lead['phone']    ?? '',
        'Company'      => $lead['company']  ?? 'Not provided',
        'Lead_Source'  => mapLeadSource($lead['utm_source'] ?? ''),
        'Description'  => buildLeadBrief($lead),
        'Lead_Status'  => 'Not Contacted',
    ];

    // ── Custom fields ────────────────────────────────────────
    // These carry the Section 03 scoring signals into the CRM, which is what
    // the lead-scoring rules gate on. Sent as human-readable labels, not the
    // internal slugs, because a BD coordinator working the T+15min check
    // should not have to translate "within_3_months" in their head. The Zoho
    // picklist values must match these labels exactly - see CRM_FIELDS below.
    //
    // NOTE ON FIELD NAMING: the original implementation used a "__c" suffix
    // (Service_Interest__c). That is Salesforce convention, not Zoho - Zoho
    // custom fields use a plain API name such as "Service_Interest". If the
    // custom values have never appeared on your Zoho records despite the
    // push otherwise succeeding, that suffix is the reason. The names below
    // are unsuffixed. Confirm each one against Setup > Developer Space >
    // APIs > API Names before relying on it.
    $custom = [
        'Service_Interest'   => $lead['service']       ?? '',
        'Client_Type'        => mapClientType($lead['client_type']   ?? ''),
        'Project_Scale'      => mapProjectScale($lead['project_scale'] ?? ''),
        'Project_Stage'      => mapProjectStage($lead['project_stage'] ?? ''),
        'Decision_Horizon'   => mapDecisionHorizon($lead['decision_horizon'] ?? ''),
        'Decision_Authority' => mapAuthority($lead['authority'] ?? ''),
        // Set so the CRM can tell "organic visitor, never asked" apart from
        // "paid lead who left it blank". Without it, a blank stage is
        // ambiguous and the scoring gate cannot be fair to either.
        'Qualification_Asked' => !empty($lead['qualification_required']) ? 'Yes' : 'No',
        // Attribution. Section 17 lists these as mandatory CRM fields; the
        // Friday feedback loop compares ads on cost per MQL, which is not
        // possible from campaign name alone.
        'Lead_UID'           => $lead['lead_uid']         ?? '',
        'UTM_Source'         => $lead['utm_source']       ?? '',
        'UTM_Medium'         => $lead['utm_medium']       ?? '',
        'UTM_Campaign'       => $lead['utm_campaign']     ?? '',
        'UTM_Content'        => $lead['utm_content']      ?? '',
        'Meta_Campaign_ID'   => $lead['meta_campaign_id'] ?? '',
        'Meta_AdSet_ID'      => $lead['meta_adset_id']    ?? '',
        'Meta_Ad_ID'         => $lead['meta_ad_id']       ?? '',
        // "Placement" alone is rejected by Zoho as a reserved keyword, so the
        // field is Ad_Placement. Same reason Lead_Score had to become
        // Qualification_Score - Zoho owns "Lead Score" for its own Zia scoring.
        'Ad_Placement'       => $lead['placement']        ?? '',
        'Consent_State'      => $lead['consent_state']    ?? '',
        'Website_Source'     => $lead['page_url']         ?? '',
        // Populated once the scoring gate computes it. Sent only when set, so
        // an unscored lead shows an empty field rather than a misleading 0 -
        // "not yet scored" and "scored zero" must not look identical.
        'Qualification_Score' => isset($lead['lead_score']) ? (string) (int) $lead['lead_score'] : '',
    ];

    // Drop empties. Zoho rejects the whole record if a picklist field is sent
    // an empty string it does not recognise, and an absent field is treated
    // as "not provided" - which is the truth for an organic lead that was
    // never asked. This one filter is why a lead is never lost to a blank.
    foreach ($custom as $key => $value) {
        if ($value !== '' && $value !== null) $record[$key] = $value;
    }

    $payload = ['data' => [$record]];

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

/* ════════════════════════════════════════════════════════════════════
   Slug → label maps for the Section 03 qualification signals
   ════════════════════════════════════════════════════════════════════
   The single source of truth for accepted slugs is the whitelist in
   submit-lead.php; these only decide how each one reads inside the CRM.
   An unrecognised slug returns '' and is then dropped from the payload
   rather than sent as a value Zoho's picklist would reject.

   CRM_FIELDS — CREATED AND VERIFIED IN THE LIVE ORG, 9 Aug 2026.
   No longer a to-do list. All 19 exist on the Leads module with these exact
   api_names, read back from the API after creation:

     Client_Type           picklist   Project_Stage        picklist
     Project_Scale         picklist   Decision_Horizon     picklist
     Decision_Authority    picklist   Qualification_Asked  picklist (Yes/No)
     Qualification_Score   integer(3) Service_Interest     text(50)
     Lead_UID              text(32)   Consent_State        text(20)
     UTM_Source            text(100)  UTM_Medium           text(100)
     UTM_Campaign          text(150)  UTM_Content          text(255)
     Meta_Campaign_ID      text(64)   Meta_AdSet_ID        text(64)
     Meta_Ad_ID            text(64)   Ad_Placement         text(100)
     Website_Source        textarea(2000)

   Two names differ from the obvious choice because Zoho reserves them:
     "Lead Score" -> Qualification_Score   (Zoho owns it for Zia scoring)
     "Placement"  -> Ad_Placement          (reserved keyword)

   Note there is no "__c" suffix on any of them. The original code used that
   Salesforce convention, so none of its custom fields ever existed and every
   custom value it sent was discarded.
   ════════════════════════════════════════════════════════════════════ */

/* ────────────────────────────────────────────────────────────────────
   VERIFIED AGAINST THE LIVE ORG on 9 Aug 2026.
   Every string below was read back from GET /crm/v8/settings/fields
   after the fields were created, not copied from a plan. Zoho caps a
   picklist value at 25 characters, which is why several read shorter
   than the form labels the visitor sees - the form is free to be
   wordier than the CRM. If you edit a value here, edit the picklist in
   Zoho to match in the same change: a value Zoho does not recognise
   makes it reject the ENTIRE record, not just that field.
   ──────────────────────────────────────────────────────────────────── */

function mapProjectStage(string $slug): string {
    return [
        'exploring'    => 'Exploring, no site',
        'concept'      => 'Concept defined',
        'feasibility'  => 'Feasibility / audit',
        'design'       => 'Engineering design',
        'procurement'  => 'Procurement / tender',
        'construction' => 'Under construction',
        'operating'    => 'Operating asset',
    ][$slug] ?? '';
}

function mapDecisionHorizon(string $slug): string {
    return [
        'immediate'        => 'Immediately',
        'within_3_months'  => 'Within 3 months',
        'within_6_months'  => 'Within 6 months',
        '6_to_12_months'   => '6-12 months',
        'beyond_12_months' => 'Beyond 12 months',
        'unsure'           => 'Not sure yet',
    ][$slug] ?? '';
}

function mapAuthority(string $slug): string {
    return [
        'decision_maker'      => 'Decision maker',
        'project_owner'       => 'Project owner / lead',
        'technical_evaluator' => 'Technical evaluator',
        'mandated_adviser'    => 'Mandated adviser',
        'influencer'          => 'Contributor',
        'gathering_info'      => 'Gathering information',
    ][$slug] ?? '';
}

function mapClientType(string $slug): string {
    return [
        'mda'                => 'Ministry / Agency (MDA)',
        'epc_contractor'     => 'EPC Contractor',
        'project_developer'  => 'Project Developer / IPP',
        'development_agency' => 'Development Agency',
        'ci_client'          => 'C&I Client',
        'off_grid_developer' => 'Off-Grid Developer',
        'other'              => 'Other',
    ][$slug] ?? '';
}

function mapProjectScale(string $slug): string {
    return [
        'under_50kw'  => 'Under 50kW',
        '50kw_500kw'  => '50kW - 500kW',
        '500kw_2mw'   => '500kW - 2MW',
        'above_2mw'   => 'Above 2MW',
        'undecided'   => 'Not yet determined',
    ][$slug] ?? '';
}

/**
 * Build the CRM Description.
 *
 * The prospect's own words come first and unedited - that free-text brief is
 * the 5-point "brief quality" signal in Section 03 and the thing the senior
 * consultant actually reads before the T+2h call. The structured qualifiers
 * are appended underneath so the whole picture is visible without clicking
 * through to the custom-field panel, which is where the T+15min check
 * currently loses time.
 */
function buildLeadBrief(array $lead): string {
    $brief = trim((string) ($lead['message'] ?? ''));

    $rows = array_filter([
        'Stage'     => mapProjectStage((string) ($lead['project_stage'] ?? '')),
        'Timeline'  => mapDecisionHorizon((string) ($lead['decision_horizon'] ?? '')),
        'Role'      => mapAuthority((string) ($lead['authority'] ?? '')),
        'Scale'     => mapProjectScale((string) ($lead['project_scale'] ?? '')),
        'Type'      => mapClientType((string) ($lead['client_type'] ?? '')),
    ]);

    if (!$rows) return $brief;

    $lines = ['', '── Qualification ──'];
    foreach ($rows as $label => $value) {
        $lines[] = $label . ': ' . $value;
    }
    return $brief . "\n" . implode("\n", $lines);
}

/**
 * Map utm_source to a Zoho Lead_Source picklist value.
 *
 * REWRITTEN 9 Aug 2026. The previous version returned 'Google AdWords',
 * 'LinkedIn', 'Internal', 'Email' and 'Word of mouth' - none of which exist
 * in this org's Lead_Source picklist. Zoho rejects a record outright when a
 * picklist value is unrecognised, so every lead from Google, LinkedIn, email
 * or a referral would have failed the push while Meta leads succeeded. That
 * asymmetry would have looked like a channel performance story rather than
 * the bug it was.
 *
 * The values below were read back from the live picklist. Lead_Source is
 * deliberately a COARSE bucket: exact channel lives in the UTM_Source custom
 * field, which is free text and loses nothing. Reporting reads UTM_Source;
 * Lead_Source just needs to be valid and roughly right.
 *
 * Valid options in this org, for reference when editing:
 *   Advertisement, Cold Call, Employee Referral, External Referral,
 *   OnlineStore, Twitter, Facebook, Partner, Public Relations,
 *   Sales Mail Alias, Seminar Partner, Seminar-Internal, Trade Show,
 *   Web Download, Web Research, Chat
 */
function mapLeadSource(string $utmSource): string {
    $map = [
        // Paid social. Facebook and Instagram both sit under the Meta buy;
        // Instagram has no option of its own, and Facebook is the truthful
        // bucket for it.
        'facebook'  => 'Facebook',
        'instagram' => 'Facebook',
        'meta'      => 'Facebook',
        'fb'        => 'Facebook',
        'twitter'   => 'Twitter',
        'x'         => 'Twitter',
        // Other paid channels have no dedicated option, so they land in the
        // generic paid bucket. UTM_Source still records which one it was.
        'google'    => 'Advertisement',
        'googleads' => 'Advertisement',
        'adwords'   => 'Advertisement',
        'bing'      => 'Advertisement',
        'linkedin'  => 'Advertisement',
        // Earned and owned.
        'referral'  => 'External Referral',
        'partner'   => 'Partner',
        'email'     => 'Sales Mail Alias',
        'newsletter'=> 'Sales Mail Alias',
        'organic'   => 'Web Research',
        'direct'    => 'Web Research',
    ];
    $source = strtolower(trim($utmSource));
    // 'Web Research' is the honest default for an arrival we cannot attribute:
    // someone found the site without a tagged link.
    return $map[$source] ?? 'Web Research';
}
