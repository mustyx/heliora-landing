<?php
/* ════════════════════════════════════════════════════════════════════
   Heliora Consulting — Lead scoring and qualification gate
   ════════════════════════════════════════════════════════════════════
   Implements Section 03 of the H2 2026 Meta Campaign Strategy v1.2.

   WHY THIS FILE EXISTS RATHER THAN ZOHO SCORING RULES
     Zoho's native scoring rules can only SUM field values. They cannot
     gate. They also cap at six fields, against Section 03's seven. The
     model below needs both, so it lives here - in git, next to the
     whitelists that define its own inputs, and testable.

   THE BUG THIS FIXES
     The original model summed authority and decision horizon alongside
     everything else. Three signals a stranger can satisfy without any
     buying intent - target client type (20), project scale (20) and
     service fit (20) - total exactly 60, which is the MQL threshold.
     So a curious junior with no mandate, no timeline and no project
     scored as a marketing-qualified lead, and the BD coordinator spent
     real time on it.

     Section 03 never actually asked for a pure sum. Its own words:
       "SQL: 75+ points PLUS a real problem, relevant authority, and a
        next-step scoping call."
     Authority is stated as a REQUIREMENT, not a contributor. The gate
     below restores that intent; the summing implementation was the
     deviation. This is a bug fix, not a policy change.

   KNOWN GAP - MUST BE CLOSED BEFORE THE INSTANT FORM GOES LIVE
     Leads from Meta's Instant Form never reach submit-lead.php, so this
     scorer never sees them. Section 06 folds the Instant Form into C01
     specifically to compare website against form on lead QUALITY. If
     form leads arrive unscored, that comparison will not show the form
     performing worse - it will show it producing no qualified leads at
     all, and a surface that may be working gets killed on an artefact.
     Before enabling the Instant Form half, either add a Zoho function
     that scores records arriving without a Qualification_Score, or map
     the form fields through Zoho's Meta Lead Ads sync so this same
     vocabulary applies.
   ════════════════════════════════════════════════════════════════════ */

/* ── Point values ─────────────────────────────────────────────────────
   Section 03's seven signals, worth 100 together. Six are captured by
   the form. Brief quality (5) is human judgement on the free-text brief
   and is deliberately NOT automated - see scoreLead() for how the
   ceiling is handled honestly.                                        */

const HELIORA_SCORE_CLIENT_TYPE = 20;
const HELIORA_SCORE_SERVICE_FIT = 20;
const HELIORA_SCORE_STAGE       = 15;
const HELIORA_SCORE_HORIZON     = 10;
const HELIORA_SCORE_AUTHORITY   = 10;
const HELIORA_SCORE_BRIEF       = 5;   // manual, awarded by BD at T+15min

/* ── Gate vocabularies ────────────────────────────────────────────────
   These are the preconditions. A lead missing either one cannot be an
   MQL or an SQL no matter how high it sums.                           */

/** Authority values that represent a real relationship to the decision.
 *  Section 03: "Decision-maker, project owner, technical evaluator, or
 *  mandated adviser". Note what is absent: 'influencer' and
 *  'gathering_info'. Both are legitimate people worth nurturing - they
 *  are simply not evidence anyone can commission work. */
const HELIORA_AUTHORITY_QUALIFIED = [
    'decision_maker', 'project_owner', 'technical_evaluator', 'mandated_adviser',
];

/** Section 03: "Needs engagement within six months." Anything longer is
 *  a real project on a real timeline - just not this half's pipeline. */
const HELIORA_HORIZON_QUALIFIED = [
    'immediate', 'within_3_months', 'within_6_months',
];

/** Stages that represent an actual project. 'exploring' means no site has
 *  been identified, which Section 03 lists as a disqualifier for
 *  mini-grid developers, and 'concept' is early by definition. Both score
 *  zero rather than being rejected - they are the nurture pool. */
const HELIORA_STAGE_QUALIFIED = [
    'feasibility', 'design', 'procurement', 'construction', 'operating',
];

/** Client types inside the ICP. 'other' is not disqualifying - it is
 *  unproven, and someone should read the brief before deciding. */
const HELIORA_CLIENT_TYPE_TARGET = [
    'mda', 'epc_contractor', 'project_developer',
    'development_agency', 'ci_client', 'off_grid_developer',
];

/** Every service is in scope; 'multiple' and 'other' are less certain but
 *  not off-strategy, so they earn partial credit rather than zero. */
const HELIORA_SERVICE_IN_SCOPE = [
    'minigrid_design', 'owners_engineer', 'feasibility_energy_audit',
    'esia', 'ci_solar', 'shs_design', 'monitoring_compliance',
];

/**
 * Score the project-scale signal.
 *
 * Section 03 is explicit and slightly awkward: "50kW-500kW = 15;
 * 500kW-2MW or above = 20". It gives no value for under_50kw or
 * undecided, so both score zero. Under 50kW is Solar Home System
 * territory, which the ICP table lists as a disqualifier unless the
 * enquiry is specifically SHS - handled in scoreLead(), not here.
 */
function scoreProjectScale(string $slug): int {
    switch ($slug) {
        case '500kw_2mw':
        case 'above_2mw':   return 20;
        case '50kw_500kw':  return 15;
        default:            return 0;   // under_50kw, undecided, blank
    }
}

/**
 * Score project stage. Full marks for a project that exists; zero for
 * one that does not yet.
 */
function scoreProjectStage(string $slug): int {
    return in_array($slug, HELIORA_STAGE_QUALIFIED, true) ? HELIORA_SCORE_STAGE : 0;
}

/**
 * Judge brief quality from the free-text message.
 *
 * Section 03 wants "specific location, stage, technology, and need" and
 * this is genuinely a human call. What follows is a deliberately crude
 * proxy for ONE thing only: whether the brief contains enough substance
 * to be worth reading. It is not an attempt to replace the T+15min
 * check, and it caps at the full 5 only on strong signals so a BD
 * coordinator adjusting it upward is the normal case, not an override.
 */
function scoreBriefQuality(string $message): int {
    $len = strlen(trim($message));
    if ($len < 40) return 0;    // "call me" - nothing to assess

    $score = 1;
    // A number with a unit implies someone has sized something.
    if (preg_match('/\d+\s*(kw|mw|kva|kwh|mwh)\b/i', $message)) $score += 2;
    // A Nigerian state or major city implies a real site.
    if (preg_match('/\b(lagos|abuja|kano|kaduna|rivers|port harcourt|ibadan|oyo|ogun|'
        . 'enugu|anambra|delta|edo|plateau|jos|sokoto|borno|maiduguri|benue|niger|'
        . 'katsina|bauchi|adamawa|taraba|kebbi|zamfara|yobe|gombe|nasarawa|kogi|'
        . 'kwara|osun|ondo|ekiti|imo|abia|ebonyi|cross river|akwa ibom|bayelsa|fct)\b/i',
        $message)) $score += 1;
    // Technical vocabulary a tyre-kicker does not reach for.
    if (preg_match('/\b(feasibility|single.?line|sld|protection|load profile|'
        . 'tariff|esia|epc|tender|bid|commissioning|interconnect|minigrid|mini.?grid|'
        . 'owner.?s engineer|due diligence|bankab)/i', $message)) $score += 1;

    return min($score, HELIORA_SCORE_BRIEF);
}

/**
 * Compute score and grade for a lead.
 *
 * Returns:
 *   score       int 0-100
 *   grade       'sql' | 'mql' | 'nurture' | 'disqualify' | 'unscored'
 *   reason      short human sentence for the CRM and the admin panel
 *   gates       which preconditions passed, for debugging a surprise
 *   components  per-signal points, so a score can always be explained
 *
 * 'unscored' is returned when the qualification fields were never asked
 * - an organic visitor on the lighter form. That is NOT a low score. A
 * lead nobody asked must never be filed alongside a lead that answered
 * badly, or the CRM will teach the wrong lesson about organic traffic.
 */
function scoreLead(array $lead): array {
    $clientType = (string) ($lead['client_type']      ?? '');
    $service    = (string) ($lead['service']          ?? '');
    $scale      = (string) ($lead['project_scale']    ?? '');
    $stage      = (string) ($lead['project_stage']    ?? '');
    $horizon    = (string) ($lead['decision_horizon'] ?? '');
    $authority  = (string) ($lead['authority']        ?? '');
    $message    = (string) ($lead['message']          ?? '');
    $company    = trim((string) ($lead['company']     ?? ''));
    $email      = trim((string) ($lead['email']       ?? ''));

    // ── Components ───────────────────────────────────────────────────
    $components = [
        'client_type'   => in_array($clientType, HELIORA_CLIENT_TYPE_TARGET, true)
                             ? HELIORA_SCORE_CLIENT_TYPE : 0,
        'project_scale' => scoreProjectScale($scale),
        'service_fit'   => in_array($service, HELIORA_SERVICE_IN_SCOPE, true)
                             ? HELIORA_SCORE_SERVICE_FIT
                             : (in_array($service, ['multiple', 'other'], true) ? 10 : 0),
        'project_stage' => scoreProjectStage($stage),
        'horizon'       => in_array($horizon, HELIORA_HORIZON_QUALIFIED, true)
                             ? HELIORA_SCORE_HORIZON : 0,
        'authority'     => in_array($authority, HELIORA_AUTHORITY_QUALIFIED, true)
                             ? HELIORA_SCORE_AUTHORITY : 0,
        'brief_quality' => scoreBriefQuality($message),
    ];
    $score = array_sum($components);

    // ── Preconditions ────────────────────────────────────────────────
    // These are the fix. Each is a gate, not a contributor.
    $gates = [
        'has_authority'   => in_array($authority, HELIORA_AUTHORITY_QUALIFIED, true),
        'has_horizon'     => in_array($horizon,   HELIORA_HORIZON_QUALIFIED, true),
        'has_real_stage'  => in_array($stage,     HELIORA_STAGE_QUALIFIED, true),
        // Section 03 gates MQL on "valid organization/contact details".
        'has_org_contact' => $company !== '' && $email !== '',
        /* The ICP table lists "under 50kW unless SHS" as a disqualifier.
           Implemented as a gate rather than a hard disqualify: a sub-50kW
           enquiry against a large-scale service is more likely a mis-set
           dropdown than a bad lead, and wrongly disqualifying means a real
           project never reaches a human. Caps the grade at nurture and says
           why, so the T+15min check can correct it in seconds. */
        'scale_in_scope'  => !($scale === 'under_50kw'
            && !in_array($service, ['shs_design', 'multiple', 'other'], true)),
    ];

    // ── Was the lead ever asked? ─────────────────────────────────────
    // Distinguished from a bad answer. If nothing was captured, say so.
    $asked = ($stage !== '' || $horizon !== '' || $authority !== '');
    if (!$asked) {
        return [
            'score'      => $score,
            'grade'      => 'unscored',
            'reason'     => 'Qualification not captured (organic visit). Score is partial; qualify manually.',
            'gates'      => $gates,
            'components' => $components,
        ];
    }

    // ── Hard disqualifiers ───────────────────────────────────────────
    // Section 03: "consumer installation, recruitment, vendor spam,
    // unrelated services, or unverifiable identity."
    if (isDisqualified($lead)) {
        return [
            'score'      => $score,
            'grade'      => 'disqualify',
            'reason'     => 'Matches a Section 03 disqualifier. Do not route to BD.',
            'gates'      => $gates,
            'components' => $components,
        ];
    }

    // ── Grade, gated ─────────────────────────────────────────────────
    // SQL needs 75+ AND authority AND horizon AND a live project AND to be
    // contactable AND to be in scope on scale.
    //
    // has_org_contact is required here even though Section 03 states it only
    // under MQL. SQL is a superset of MQL, not a parallel track: a lead with
    // no organisation cannot be sales-qualified, and reading the thresholds
    // as independent would let a nameless contact outrank a named one.
    if ($score >= 75
        && $gates['has_authority'] && $gates['has_horizon']
        && $gates['has_real_stage'] && $gates['has_org_contact']
        && $gates['scale_in_scope']) {
        return [
            'score'      => $score,
            'grade'      => 'sql',
            'reason'     => 'Score ' . $score . ' with decision authority, timeline inside six months, and a live project stage.',
            'gates'      => $gates,
            'components' => $components,
        ];
    }

    // MQL needs 60+ AND contactable AND at least one of authority or
    // horizon. Requiring both here would collapse MQL into SQL; requiring
    // neither is the bug. One real buying signal is the honest middle.
    if ($score >= 60 && $gates['has_org_contact'] && $gates['scale_in_scope']
        && ($gates['has_authority'] || $gates['has_horizon'])) {
        $missing = !$gates['has_authority'] ? 'no decision authority' : 'no timeline inside six months';
        return [
            'score'      => $score,
            'grade'      => 'mql',
            'reason'     => 'Score ' . $score . ' and contactable, but ' . $missing . '. Establish that on the call.',
            'gates'      => $gates,
            'components' => $components,
        ];
    }

    // Everything else nurtures. Note a 90-point lead with no authority
    // and no timeline lands HERE, which is the whole point of the fix.
    $why = [];
    if (!$gates['has_authority']) $why[] = 'no decision authority';
    if (!$gates['has_horizon'])   $why[] = 'no timeline inside six months';
    if (!$gates['has_real_stage']) $why[] = 'project not yet at a real stage';
    if (!$gates['has_org_contact']) $why[] = 'incomplete organisation or contact details';
    if (!$gates['scale_in_scope']) $why[] = 'under 50kW against a large-scale service — check the scale field is right';

    return [
        'score'      => $score,
        'grade'      => 'nurture',
        'reason'     => $score >= 60
            ? 'Score ' . $score . ' but gated: ' . implode(', ', $why) . '. Credible, not yet actionable.'
            : 'Score ' . $score . '. ' . ($why ? implode(', ', $why) . '.' : 'Early stage.'),
        'gates'      => $gates,
        'components' => $components,
    ];
}

/**
 * Section 03 hard disqualifiers.
 *
 * Kept deliberately narrow. A false positive here means a real project
 * never reaches a human, which is far more expensive than a few minutes
 * spent dismissing a bad lead. When in doubt this returns false and lets
 * the T+15min check decide.
 */
function isDisqualified(array $lead): bool {
    $haystack = strtolower(
        ($lead['message'] ?? '') . ' ' . ($lead['company'] ?? '') . ' ' . ($lead['service'] ?? '')
    );

    // Recruitment. Guarded against false positives: "cv" as a standalone
    // word only, since it appears inside ordinary words otherwise.
    if (preg_match('/\b(cv|resume|curriculum vitae|internship|job (application|opening|vacanc)|'
        . 'seeking (a )?(job|employment|position)|apply for (a )?(job|role|position)|'
        . 'my qualification|employment opportunit|graduate trainee|siwes|industrial attachment)\b/', $haystack)) {
        return true;
    }

    // Vendor and supplier pitches - people selling TO Heliora.
    if (preg_match('/\b(we (supply|manufacture|distribute|sell)|our (products|catalogue|price list)|'
        . 'become (a )?(supplier|vendor|distributor)|quotation for our|dealership|'
        . 'partnership proposal for (our|my) (product|panel|inverter|batter))\b/', $haystack)) {
        return true;
    }

    // Consumer household installation. Section 03 excludes it, and the
    // ICP table names "household installation" explicitly. Requires BOTH
    // a domestic cue and the absence of any commercial framing, so a
    // facilities manager mentioning "my home office" is not caught.
    $domestic = preg_match('/\b(my (house|home|flat|apartment|bedroom|residence)|'
        . 'my (2|3|4|two|three|four) bedroom|for my family|domestic use|'
        . 'household use|residential apartment)\b/', $haystack);
    $commercial = preg_match('/\b(company|ltd|limited|plc|factory|plant|hospital|hotel|school|'
        . 'university|estate|telecom|bank|office|industrial|commercial|tender|project|'
        . 'ministry|agency|programme|developer|contractor|epc)\b/', $haystack);
    if ($domestic && !$commercial) {
        return true;
    }

    return false;
}

/**
 * Map an internal grade to the Zoho Lead_Status picklist.
 *
 * VERIFIED against the live org, 9 Aug 2026. Valid values are:
 *   -None-, Attempted to Contact, Contact in Future, Contacted,
 *   Junk Lead, Lost Lead, Not Contacted, Pre-Qualified, Not Qualified
 *
 * There is no "MQL" or "SQL" option, and Lead_Status is a strict enum -
 * an unrecognised value makes Zoho reject the whole record. So the grade
 * itself travels in Qualification_Score and the reason text, while
 * Lead_Status carries only what the picklist can express.
 */
function mapGradeToLeadStatus(string $grade): string {
    switch ($grade) {
        case 'sql':        return 'Pre-Qualified';
        case 'mql':        return 'Not Contacted';
        case 'nurture':    return 'Contact in Future';
        case 'disqualify': return 'Not Qualified';
        default:           return 'Not Contacted';   // unscored
    }
}
