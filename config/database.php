<?php
/* ════════════════════════════════════════════════════════
   Heliora Consulting — Database Connection (PDO)
   ════════════════════════════════════════════════════════ */

require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            /* ── Align MySQL's clock with the application's ────────────────
               Added 9 Aug 2026 after finding leads stamped five hours early.

               The host's MySQL runs time_zone = SYSTEM, and the server sits in
               a US datacentre - US Eastern, UTC-4 in August. PHP is set to
               Africa/Lagos (UTC+1). So `created_at DEFAULT CURRENT_TIMESTAMP`
               and any NOW() were written in New York time while every PHP
               date() rendered Lagos time. A lead submitted at 16:25 Lagos
               stored as 11:25.

               That is worse than a cosmetic wrong number. Section 17 measures
               the 24-hour response promise in minutes from created_at, so
               every lead looked five hours older than it was - the SLA would
               have reported itself as comfortably met while actually being
               breached, and the error flatters us, which is the direction that
               goes unquestioned longest.

               +01:00 is hardcoded rather than named: Lagos has never observed
               daylight saving, so the offset is constant, and a fixed offset
               needs no timezone tables loaded on the server (named zones like
               'Africa/Lagos' fail on hosts where mysql.time_zone_name is
               unpopulated, which is common on shared hosting).

               Cheap enough to run on every connect, and it makes MySQL and PHP
               agree for CURRENT_TIMESTAMP, NOW(), and anything derived. */
            $pdo->exec("SET time_zone = '+01:00'");

        } catch (PDOException $e) {
            // Don't expose DB errors to users in production
            error_log('DB Connection failed: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Service temporarily unavailable.']));
        }
    }
    return $pdo;
}
