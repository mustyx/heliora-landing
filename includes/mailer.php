<?php
/* ════════════════════════════════════════════════════════
   Heliora Consulting — Email Functions (native PHP mail)
   Uses Namecheap Private Email SMTP via PHP mail().
   For production, swap with PHPMailer for SMTP auth.
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
 * Core email sender using PHP mail() with proper headers
 * For reliable delivery on Namecheap, configure cPanel Email Routing
 */
function sendEmail(string $to, string $toName, string $subject, string $htmlBody): bool {
    $from     = SMTP_FROM;
    $fromName = SMTP_NAME;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "X-Mailer: Heliora-Mailer/1.0\r\n";
    $headers .= "X-Priority: 1\r\n";

    $encodedTo = "=?UTF-8?B?" . base64_encode($toName) . "?= <{$to}>";
    $result = @mail($encodedTo, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);

    if (!$result) {
        error_log("Mail failed to: {$to} | Subject: {$subject}");
    }
    return (bool) $result;
}

/**
 * Auto-respond email HTML template
 */
function buildAutoRespondEmail(string $name, string $service): string {
    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>We received your request</title>
</head>
<body style="margin:0;padding:0;background:#050d1a;font-family:'Inter',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#050d1a;padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#0a1628;border-radius:16px;overflow:hidden;border:1px solid #1a3a6e;">

  <!-- Header -->
  <tr>
    <td style="background:linear-gradient(135deg,#0a1628,#122850);padding:40px 48px;text-align:center;border-bottom:1px solid #1a3a6e;">
      <table cellpadding="0" cellspacing="0" style="margin:0 auto;">
        <tr>
          <td style="background:#d4a827;width:48px;height:48px;border-radius:10px;text-align:center;vertical-align:middle;font-family:Georgia,serif;font-size:22px;font-weight:bold;color:#050d1a;">H</td>
          <td style="padding-left:12px;text-align:left;">
            <div style="color:#ffffff;font-size:18px;font-weight:600;letter-spacing:-0.3px;">Heliora</div>
            <div style="color:#d4a827;font-size:10px;letter-spacing:3px;text-transform:uppercase;">Consulting</div>
          </td>
        </tr>
      </table>
      <div style="margin-top:24px;width:40px;height:3px;background:#d4a827;margin-left:auto;margin-right:auto;border-radius:2px;"></div>
    </td>
  </tr>

  <!-- Body -->
  <tr>
    <td style="padding:48px 48px 32px;">
      <h1 style="color:#ffffff;font-size:28px;font-weight:700;margin:0 0 16px;line-height:1.3;">
        Thank you, {$name}!
      </h1>
      <p style="color:#9fb3c8;font-size:16px;line-height:1.7;margin:0 0 24px;">
        We've received your consultation request regarding <strong style="color:#d4a827;">{$service}</strong>. A senior engineer from our team will review your project details and reach out within <strong style="color:#ffffff;">24 business hours</strong>.
      </p>
      <p style="color:#9fb3c8;font-size:16px;line-height:1.7;margin:0 0 32px;">
        In the meantime, here's what to expect from our process:
      </p>

      <!-- Steps -->
      <table cellpadding="0" cellspacing="0" width="100%">
        <tr>
          <td style="padding:0 0 16px;">
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="width:40px;height:40px;background:rgba(212,168,39,0.15);border-radius:8px;text-align:center;vertical-align:middle;color:#d4a827;font-weight:700;font-size:14px;">1</td>
                <td style="padding-left:14px;color:#9fb3c8;font-size:14px;line-height:1.6;"><strong style="color:#fff;display:block;margin-bottom:2px;">Discovery Call</strong>A 30-minute call to understand your project and goals.</td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:0 0 16px;">
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="width:40px;height:40px;background:rgba(212,168,39,0.15);border-radius:8px;text-align:center;vertical-align:middle;color:#d4a827;font-weight:700;font-size:14px;">2</td>
                <td style="padding-left:14px;color:#9fb3c8;font-size:14px;line-height:1.6;"><strong style="color:#fff;display:block;margin-bottom:2px;">Tailored Proposal</strong>A clear scope and fixed-fee proposal within 48 hours.</td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:0 0 0;">
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="width:40px;height:40px;background:rgba(212,168,39,0.15);border-radius:8px;text-align:center;vertical-align:middle;color:#d4a827;font-weight:700;font-size:14px;">3</td>
                <td style="padding-left:14px;color:#9fb3c8;font-size:14px;line-height:1.6;"><strong style="color:#fff;display:block;margin-bottom:2px;">Engineering Excellence</strong>Delivery with full transparency and regular reporting.</td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- CTA -->
      <table cellpadding="0" cellspacing="0" style="margin:40px 0 32px;">
        <tr>
          <td style="background:#d4a827;border-radius:8px;">
            <a href="https://helioraconsulting.com" style="display:inline-block;padding:14px 32px;color:#050d1a;font-weight:700;font-size:15px;text-decoration:none;">Visit Our Website →</a>
          </td>
        </tr>
      </table>

      <p style="color:#829ab1;font-size:13px;line-height:1.6;margin:0;">
        Questions? Reply to this email or WhatsApp us at <a href="https://wa.me/1234567890" style="color:#d4a827;text-decoration:none;">+1 (234) 567-890</a>.
      </p>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background:#050d1a;padding:24px 48px;border-top:1px solid #1a3a6e;">
      <p style="color:#4a6080;font-size:12px;margin:0;line-height:1.6;">
        &copy; {$year} Heliora Consulting. All rights reserved.<br/>
        You are receiving this because you submitted a request on helioraconsulting.com.
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
    $budget  = formatBudget($lead['project_budget'] ?? '');
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
        <tr><td style="padding:10px 0;border-bottom:1px solid #333;color:#999;font-size:13px;">Service</td><td style="padding:10px 0;border-bottom:1px solid #333;color:#d4a827;font-size:14px;font-weight:600;">{$service}</td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #333;color:#999;font-size:13px;">Budget</td><td style="padding:10px 0;border-bottom:1px solid #333;color:#fff;font-size:14px;">{$budget}</td></tr>
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
        'structural_engineering'  => 'Structural Engineering',
        'project_management'      => 'Project Management',
        'technical_due_diligence' => 'Technical Due Diligence',
        'energy_sustainability'   => 'Energy & Sustainability',
        'mechanical_process'      => 'Mechanical & Process',
        'other'                   => 'Other / Multiple Services',
    ];
    return $map[$slug] ?? ucwords(str_replace('_', ' ', $slug));
}

/**
 * Format budget slug to readable label
 */
function formatBudget(string $slug): string {
    $map = [
        'under_50k'  => 'Under $50K',
        '50k_250k'   => '$50K – $250K',
        '250k_1m'    => '$250K – $1M',
        '1m_5m'      => '$1M – $5M',
        'over_5m'    => 'Over $5M',
        'undecided'  => 'Not yet determined',
    ];
    return $map[$slug] ?? 'Not provided';
}
