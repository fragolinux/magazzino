<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-05 09:20:18 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-05 09:25:50
*/

/**
 * Pagina impostazioni generali (solo admin)
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db_connect.php';

// solo admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Accesso negato: permessi insufficienti.";
    exit;
}

$message = null;
$error = null;

// Rilevamento automatico IP della scheda di rete
function detectLocalIP() {
    // Prima prova: socket UDP verso DNS pubblico per determinare IP locale
    $ip = null;
    $sock = @stream_socket_client("udp://8.8.8.8:53", $errno, $errstr, 1);
    if ($sock !== false) {
        $name = stream_socket_get_name($sock, false); // local address:port
        if ($name !== false) {
            $parts = explode(':', $name);
            if (filter_var($parts[0], FILTER_VALIDATE_IP)) {
                $ip = $parts[0];
            }
        }
        fclose($sock);
    }

    // Seconda prova: socket extension se disponibile
    if (empty($ip) && function_exists('socket_create')) {
        $s = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($s !== false) {
            @socket_connect($s, '8.8.8.8', 53);
            @socket_getsockname($s, $localIp, $port);
            if (!empty($localIp) && filter_var($localIp, FILTER_VALIDATE_IP)) {
                $ip = $localIp;
            }
            @socket_close($s);
        }
    }

    // Fallback: gethostbyname
    if (empty($ip) || $ip === '127.0.0.1') {
        $host = gethostname();
        $resolved = gethostbyname($host);
        if ($resolved && $resolved !== '127.0.0.1') {
            $ip = $resolved;
        }
    }

    return $ip ?: '127.0.0.1';
}

$detectedIp = detectLocalIP();

// Leggi valore corrente dal DB
$currentIp = '';
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM setting WHERE setting_name = ? LIMIT 1");
    $stmt->execute(['IP_Computer']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $currentIp = $row['setting_value'];
} catch (Exception $e) {
    // se la tabella non esiste o errore, lasciamo vuoto
    $currentIp = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = isset($_POST['ip_address']) ? trim($_POST['ip_address']) : '';
    if ($ip === '') {
        $error = 'Indirizzo IP non può essere vuoto.';
    } elseif (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $error = 'Indirizzo IP non valido.';
    } else {
        // inserisci o aggiorna
        try {
            $stmt = $pdo->prepare("SELECT id_setting FROM setting WHERE setting_name = ? LIMIT 1");
            $stmt->execute(['IP_Computer']);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($exists) {
                $upd = $pdo->prepare("UPDATE setting SET setting_value = ? WHERE setting_name = ?");
                $upd->execute([$ip, 'IP_Computer']);
            } else {
                $ins = $pdo->prepare("INSERT INTO setting (setting_name, setting_value) VALUES (?, ?)");
                $ins->execute(['IP_Computer', $ip]);
            }
            $message = 'Impostazione salvata.';
            $currentIp = $ip;
        } catch (Exception $e) {
            $error = 'Errore salvataggio: ' . $e->getMessage();
        }
    }
}

?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="container py-4">
    <h2>Settaggi</h2>
    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="mt-3" style="max-width:600px;">
        <div class="mb-3">
            <label for="ip_address" class="form-label">IP del computer dove gira Xampp</label>
            <input type="text" id="ip_address" name="ip_address" class="form-control" value="<?= htmlspecialchars($currentIp ?: $detectedIp) ?>">
            <div class="form-text">Valore suggerito: <?= htmlspecialchars($detectedIp) ?></div>
        </div>
        <button class="btn btn-primary">Salva</button>
        <a href="/magazzino/index.php" class="btn btn-secondary ms-2">Annulla</a>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>