<?php
/* ════════════════════════════════════════════════════════
   Heliora Consulting — Email Functions (raw SMTP)
   Authenticates directly with Namecheap Private Email
   via SMTP — no external libraries required.
   ════════════════════════════════════════════════════════ */

require_once __DIR__ . '/../config/config.php';

/**
 * Send auto-respond email to the lead
 */
function sendAutoRespond(array $lead): bool {
    $to      = $lead['email'];
    $name    = htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name']);
    $service = formatService($lead['service']);

    $subject = 'We received your request — Heliora Consulting';
    $body    = buildAutoRespondEmail($name, $service);

    return sendEmail($to, $name, $subject, $body);
}

/**
 * Send internal notification to admin
 */
function sendAdminNotification(array $lead): bool {
    $to      = ADMIN_EMAIL;
    $subject = '🔔 New Lead: ' . htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name'])
               . ' — ' . formatService($lead['service']);
    $body    = buildAdminNotificationEmail($lead);

    return sendEmail($to, APP_NAME . ' Admin', $subject, $body);
}

/**
 * Core email sender — raw SMTP with STARTTLS + AUTH LOGIN
 * Works with Namecheap Private Email (mail.helioraconsulting.com:587)
 */
function sendEmail(string $to, string $toName, string $subject, string $htmlBody): bool {
    $host     = SMTP_HOST;
    $port     = (int) SMTP_PORT;
    $user     = SMTP_USER;
    $pass     = SMTP_PASS;
    $from     = SMTP_FROM;
    $fromName = SMTP_NAME;
    $ehlo     = 'helioraconsulting.com';

    try {
        // ── Connect ───────────────────────────────────────────
        $prefix  = ($port === 465) ? 'ssl://' : '';
        $socket  = @fsockopen($prefix . $host, $port, $errno, $errstr, 15);
        if (!$socket) {
            error_log("SMTP connect failed [{$host}:{$port}]: {$errstr} ({$errno})");
            return false;
        }
        stream_set_timeout($socket, 15);

        $read = smtpRead($socket);
        if (!smtpOk($read, '220')) { fclose($socket); return false; }

        // ── EHLO ──────────────────────────────────────────────
        smtpSend($socket, "EHLO {$ehlo}");
        smtpReadAll($socket); // consume multi-line EHLO

        // ── STARTTLS (port 587) ────────────────────────────────
        if ($port === 587) {
            smtpSend($socket, 'STARTTLS');
            $r = smtpRead($socket);
            if (!smtpOk($r, '220')) { fclose($socket); return false; }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                // fall back to any TLS
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }
            smtpSend($socket, "EHLO {$ehlo}");
            smtpReadAll($socket);
        }

        // ── AUTH LOGIN ─────────────────────────────────────────
        smtpSend($socket, 'AUTH LOGIN');
        smtpRead($socket); // 334 Username
        smtpSend($socket, base64_encode($user));
        smtpRead($socket); // 334 Password
        smtpSend($socket, base64_encode($pass));
        $authResp = smtpRead($socket);
        if (!smtpOk($authResp, '235')) {
            error_log("SMTP auth failed: {$authResp}");
            fclose($socket);
            return false;
        }

        // ── Envelope ──────────────────────────────────────────
        smtpSend($socket, "MAIL FROM:<{$from}>");
        smtpRead($socket);
        smtpSend($socket, "RCPT TO:<{$to}>");
        $rcptResp = smtpRead($socket);
        if (!smtpOk($rcptResp, '250') && !smtpOk($rcptResp, '251')) {
            error_log("SMTP RCPT failed: {$rcptResp}");
            fclose($socket);
            return false;
        }

        // ── DATA ──────────────────────────────────────────────
        smtpSend($socket, 'DATA');
        smtpRead($socket); // 354

        $enc = function(string $s): string {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        };

        $msg  = "From: {$enc($fromName)} <{$from}>\r\n";
        $msg .= "To: {$enc($toName)} <{$to}>\r\n";
        $msg .= "Subject: {$enc($subject)}\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n";
        $msg .= "X-Mailer: Heliora-Mailer/2.0\r\n";
        $msg .= "\r\n";
        $msg .= chunk_split(base64_encode($htmlBody), 76, "\r\n");
        $msg .= "\r\n.\r\n";

        fputs($socket, $msg);
        $dataResp = smtpRead($socket);

        smtpSend($socket, 'QUIT');
        fclose($socket);

        $ok = smtpOk($dataResp, '250');
        if (!$ok) error_log("SMTP DATA failed: {$dataResp}");
        return $ok;

    } catch (\Throwable $e) {
        error_log('SMTP exception: ' . $e->getMessage());
        return false;
    }
}

/* ── SMTP helpers ──────────────────────────────────────── */
function smtpSend($sock, string $cmd): void   { fputs($sock, $cmd . "\r\n"); }
function smtpRead($sock): string              { return (string) fgets($sock, 512); }
function smtpOk(string $resp, string $code): bool { return substr(trim($resp), 0, 3) === $code; }
function smtpReadAll($sock): void {
    // Read until a line where the 4th char is a space (final EHLO line)
    while (($line = fgets($sock, 512)) !== false) {
        if (isset($line[3]) && $line[3] === ' ') break;
    }
}

/**
 * Auto-respond email HTML template — light/orange brand
 */
function buildAutoRespondEmail(string $name, string $service): string {
    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>We received your request — Heliora Consulting</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f7;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f7;padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e8e8ed;">

  <!-- Header -->
  <tr>
    <td style="background:#1b2e6e;padding:36px 48px;text-align:center;">
      <table cellpadding="0" cellspacing="0" style="margin:0 auto;">
        <tr>
          <td style="background:#f47c20;width:44px;height:44px;border-radius:10px;text-align:center;vertical-align:middle;font-family:Georgia,serif;font-size:20px;font-weight:bold;color:#ffffff;">H</td>
          <td style="padding-left:12px;text-align:left;">
            <div style="color:#ffffff;font-size:17px;font-weight:700;letter-spacing:0.02em;">HELIORA</div>
            <div style="color:#f47c20;font-size:9px;letter-spacing:0.2em;text-transform:uppercase;margin-top:2px;">Consulting Limited</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Orange accent bar -->
  <tr><td style="background:#f47c20;height:3px;font-size:0;line-height:0;">&nbsp;</td></tr>

  <!-- Body -->
  <tr>
    <td style="padding:48px 48px 40px;">
      <h1 style="color:#1d1d1f;font-size:26px;font-weight:800;margin:0 0 16px;line-height:1.2;letter-spacing:-0.02em;">
        Thank you, {$name}.
      </h1>
      <p style="color:#515154;font-size:16px;line-height:1.7;margin:0 0 12px;">
        We have received your consultation request regarding <strong style="color:#f47c20;">{$service}</strong>.
      </p>
      <p style="color:#515154;font-size:16px;line-height:1.7;margin:0 0 36px;">
        A senior Heliora engineer will review your project details and contact you within <strong style="color:#1d1d1f;">24 business hours</strong>.
      </p>

      <!-- Divider -->
      <table cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:32px;">
        <tr><td style="border-top:1px solid #e8e8ed;font-size:0;">&nbsp;</td></tr>
      </table>

      <!-- What to expect -->
      <p style="color:#86868b;font-size:11px;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;margin:0 0 20px;">What to Expect</p>

      <table cellpadding="0" cellspacing="0" width="100%">
        <tr>
          <td style="padding:0 0 16px;">
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="width:36px;height:36px;background:#fff3e8;border-radius:8px;text-align:center;vertical-align:middle;color:#f47c20;font-weight:800;font-size:13px;border:1px solid rgba(244,124,32,0.2);">1</td>
                <td style="padding-left:14px;">
                  <div style="color:#1d1d1f;font-size:14px;font-weight:700;margin-bottom:2px;">Discovery Call</div>
                  <div style="color:#86868b;font-size:13px;line-height:1.5;">A focused call to understand your project scope and objectives.</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:0 0 16px;">
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="width:36px;height:36px;background:#fff3e8;border-radius:8px;text-align:center;vertical-align:middle;color:#f47c20;font-weight:800;font-size:13px;border:1px solid rgba(244,124,32,0.2);">2</td>
                <td style="padding-left:14px;">
                  <div style="color:#1d1d1f;font-size:14px;font-weight:700;margin-bottom:2px;">Fixed-Scope Proposal</div>
                  <div style="color:#86868b;font-size:13px;line-height:1.5;">A written proposal with defined deliverables, timeline, and transparent fees within 1–2 weeks.</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td>
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="width:36px;height:36px;background:#fff3e8;border-radius:8px;text-align:center;vertical-align:middle;color:#f47c20;font-weight:800;font-size:13px;border:1px solid rgba(244,124,32,0.2);">3</td>
                <td style="padding-left:14px;">
                  <div style="color:#1d1d1f;font-size:14px;font-weight:700;margin-bottom:2px;">Technical Delivery</div>
                  <div style="color:#86868b;font-size:13px;line-height:1.5;">Senior consultant-led execution with regular progress updates and full QA review.</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- Divider -->
      <table cellpadding="0" cellspacing="0" width="100%" style="margin:36px 0 28px;">
        <tr><td style="border-top:1px solid #e8e8ed;font-size:0;">&nbsp;</td></tr>
      </table>

      <p style="color:#86868b;font-size:13px;line-height:1.6;margin:0;">
        For urgent matters, please reply directly to this email and we will respond as quickly as possible.
      </p>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background:#f5f5f7;padding:24px 48px;border-top:1px solid #e8e8ed;">
      <p style="color:#aeaeb2;font-size:11px;margin:0;line-height:1.7;">
        &copy; {$year} Heliora Consulting Limited. All rights reserved.<br/>
        Abuja, FCT, Nigeria &middot; helioraconsulting.com<br/>
        You are receiving this email because you submitted a consultation request on our website.
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

/**
 * Admin notification email HTML template
 */
function buildAdminNotificationEmail(array $lead): string {
    $name    = htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name']);
    $email   = htmlspecialchars($lead['email']);
    $phone   = htmlspecialchars($lead['phone'] ?? 'Not provided');
    $company = htmlspecialchars($lead['company'] ?? 'Not provided');
    $service = formatService($lead['service']);
    $scale   = formatScale($lead['project_scale'] ?? '');
    $message = nl2br(htmlspecialchars($lead['message']));
    $utm     = htmlspecialchars(
                 implode(' / ', array_filter([
                   $lead['utm_source']   ?? '',
                   $lead['utm_medium']   ?? '',
                   $lead['utm_campaign'] ?? '',
                 ])) ?: 'Direct'
               );
    $time    = date('D, d M Y H:i T');
    $year    = date('Y');
    $adminUrl = APP_URL . '/admin/';

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/><title>New Lead Alert</title></head>
<body style="margin:0;padding:0;background:#0a0a0a;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0a0a;padding:32px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#1a1a1a;border-radius:12px;overflow:hidden;border:1px solid #333;">
  <tr>
    <td style="background:#d4a827;padding:20px 32px;">
      <h1 style="margin:0;color:#050d1a;font-size:20px;font-weight:700;">🔔 New Lead — Heliora Consulting</h1>
      <p style="margin:4px 0 0;color:#7a5a00;font-size:13px;">{$time}</p>
    </td>
  </tr>
  <tr>
    <td style="padding:32px;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="padding:10px 0;border-bottom:1px solid #333;color:#999;font-size:13px;width:140px;">Name</td><td style="padding:10px 0;border-bottom:1px solid #333;color:#fff;font-size:14px;font-weight:600;">{$name}</td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #333;color:#999;font-size:13px;">Email</td><td style="padding:10px 0;border-bottom:1px solid #333;"><a href="mailto:{$email}" style="color:#d4a827;font-size:14px;">{$email}</a></td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #333;color:#999;font-size:13px;">Phone</td><td style="padding:10px 0;border-bottom:1px solid #333;color:#fff;font-size:14px;">{$phone}</td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #333;color:#999;font-size:13px;">Company</td><td style="padding:10px 0;border-bottom:1px solid #333;color:#fff;font-size:14px;">{$company}</td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #333;color:#999;font-size:13px;">Service</td><td style="padding:10px 0;border-bottom:1px solid #333;color:#f47c20;font-size:14px;font-weight:600;">{$service}</td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #333;color:#999;font-size:13px;">Project Scale</td><td style="padding:10px 0;border-bottom:1px solid #333;color:#fff;font-size:14px;">{$scale}</td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #333;color:#999;font-size:13px;">Source</td><td style="padding:10px 0;border-bottom:1px solid #333;color:#fff;font-size:13px;">{$utm}</td></tr>
        <tr>
          <td style="padding:16px 0 0;color:#999;font-size:13px;vertical-align:top;">Message</td>
          <td style="padding:16px 0 0;color:#ddd;font-size:14px;line-height:1.6;">{$message}</td>
        </tr>
      </table>
      <table cellpadding="0" cellspacing="0" style="margin-top:28px;">
        <tr>
          <td style="background:#d4a827;border-radius:6px;margin-right:12px;">
            <a href="{$adminUrl}" style="display:inline-block;padding:12px 24px;color:#050d1a;font-weight:700;font-size:14px;text-decoration:none;">View in Admin Panel →</a>
          </td>
          <td style="width:12px;"></td>
          <td style="background:#333;border-radius:6px;">
            <a href="mailto:{$email}" style="display:inline-block;padding:12px 24px;color:#fff;font-weight:600;font-size:14px;text-decoration:none;">Reply to Lead</a>
          </td>
        </tr>
      </table>
    </td>
  </tr>
  <tr><td style="background:#111;padding:16px 32px;border-top:1px solid #333;">
    <p style="color:#555;font-size:11px;margin:0;">&copy; {$year} Heliora Consulting | Internal notification</p>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

/**
 * Format service slug to readable label
 */
function formatService(string $slug): string {
    $map = [
        'minigrid_design'          => 'Solar Mini-Grid Engineering Design',
        'owners_engineer'          => "Owner's Engineer Services",
        'feasibility_energy_audit' => 'Feasibility Study & Energy Audit',
        'shs_design'               => 'Solar Home System (SHS) Design',
        'esia'                     => 'ESIA & Environmental Compliance',
        'monitoring_compliance'    => 'Monitoring & Regulatory Compliance',
        'ci_solar'                 => 'C&I Solar Engineering',
        'multiple'                 => 'Multiple Services',
        'other'                    => 'Other / To Be Discussed',
    ];
    return $map[$slug] ?? ucwords(str_replace('_', ' ', $slug));
}

/**
 * Format project scale slug to readable label
 */
function formatScale(string $slug): string {
    $map = [
        'under_50kw'  => 'Under 50 kW',
        '50kw_500kw'  => '50 kW – 500 kW',
        '500kw_2mw'   => '500 kW – 2 MW',
        'above_2mw'   => 'Above 2 MW',
        'undecided'   => 'Not yet determined',
        ''            => 'Not provided',
    ];
    return $map[$slug] ?? 'Not provided';
}
