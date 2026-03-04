<?php
/**
 * Script per l'invio delle email di avviso scorte basse.
 * Da chiamare tramite Task Scheduler su Windows o Cron su Linux.
 * Esempio Windows: php.exe C:\xampp\htdocs\magazzino\cron\send_low_stock_emails.php
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../config/mail_config.php';

// 1. Recupera i componenti sotto scorta
$query = "SELECT c.id, c.codice_prodotto, c.quantity, c.quantity_min, c.unita_misura,
                 cat.name AS category_name
          FROM components c
          LEFT JOIN categories cat ON c.category_id = cat.id
          WHERE c.quantity_min IS NOT NULL 
          AND c.quantity_min != 0 
          AND c.quantity < c.quantity_min
          ORDER BY cat.name, c.codice_prodotto";

$stmt = $pdo->query($query);
$low_stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($low_stock_items)) {
    echo "Nessun componente sotto scorta. Invio email non necessario.\n";
    exit;
}

// 2. Recupera gli utenti admin con email valida
$stmtAdmins = $pdo->query("SELECT email, username FROM users WHERE role = 'admin' AND email IS NOT NULL AND email != ''");
$admins = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);

if (empty($admins)) {
    echo "Nessun amministratore con email configurata.\n";
    exit;
}

// 3. Prepara il corpo dell'email (HTML)
$htmlBody = "<h2>Avviso Scorte Basse - Magazzino</h2>";
$htmlBody .= "<p>I seguenti componenti sono scesi sotto la soglia minima impostata:</p>";
$htmlBody .= "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
$htmlBody .= "<thead style='background-color: #f2f2f2;'><tr><th>Categoria</th><th>Codice Prodotto</th><th>Q.tà Attuale</th><th>Soglia Minima</th></tr></thead>";
$htmlBody .= "<tbody>";

foreach ($low_stock_items as $item) {
    $unit = $item['unita_misura'] ?? 'pz';
    $htmlBody .= "<tr>";
    $htmlBody .= "<td>" . htmlspecialchars($item['category_name'] ?? '-') . "</td>";
    $htmlBody .= "<td>" . htmlspecialchars($item['codice_prodotto']) . "</td>";
    $htmlBody .= "<td style='color: red;'>" . intval($item['quantity']) . " " . htmlspecialchars($unit) . "</td>";
    $htmlBody .= "<td>" . intval($item['quantity_min']) . " " . htmlspecialchars($unit) . "</td>";
    $htmlBody .= "</tr>";
}

$htmlBody .= "</tbody></table>";
$htmlBody .= "<p><br>Accedi al programma di magazzino per gestire gli ordini.</p>";

// 4. Invia l'email a ogni admin
$mail = new PHPMailer(true);

try {
    // Configurazione Server
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    // Mittente
    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);

    // Destinatari
    foreach ($admins as $admin) {
        $mail->addAddress($admin['email'], $admin['username']);
    }

    // Contenuto
    $mail->isHTML(true);
    $mail->Subject = 'ALERT: Componenti Sotto Scorta - ' . date('d/m/Y');
    $mail->Body    = $htmlBody;
    $mail->AltBody = strip_tags(str_replace(['<br>', '</tr>'], ["\n", "\n"], $htmlBody));

    $mail->send();
    echo "Email di avviso inviata con successo a " . count($admins) . " amministratori.\n";
} catch (Exception $e) {
    echo "Errore durante l'invio dell'email: {$mail->ErrorInfo}\n";
}
