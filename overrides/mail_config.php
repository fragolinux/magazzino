<?php
/**
 * SMTP config override for Docker deployments.
 * Values are read from container environment variables.
 */

$smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
$smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
$smtpUser = getenv('SMTP_USER') ?: '';
$smtpPass = getenv('SMTP_PASS') ?: '';
$smtpFrom = getenv('SMTP_FROM') ?: $smtpUser;
$smtpFromName = getenv('SMTP_FROM_NAME') ?: 'Magazzino Alerts';
$smtpSecure = strtolower(trim((string) (getenv('SMTP_SECURE') ?: 'tls')));

if (!in_array($smtpSecure, ['tls', 'ssl'], true)) {
    $smtpSecure = 'tls';
}
if ($smtpPort <= 0 || $smtpPort > 65535) {
    $smtpPort = 587;
}

define('SMTP_HOST', $smtpHost);
define('SMTP_PORT', $smtpPort);
define('SMTP_USER', $smtpUser);
define('SMTP_PASS', $smtpPass);
define('SMTP_FROM', $smtpFrom);
define('SMTP_FROM_NAME', $smtpFromName);
define('SMTP_SECURE', $smtpSecure);
