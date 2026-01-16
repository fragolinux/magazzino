<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-15 17:00:03 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-15 17:59:29
*/

/*
 * Advanced Rate Limiting System
 * Sistema avanzato di limitazione tentativi per prevenire brute force attacks
 */

class RateLimiter {
    private $pdo;
    private $max_attempts;
    private $lockout_times;
    private $ip_address;

    public function __construct($pdo, $max_attempts = 5, $lockout_times = [900, 3600, 86400]) {
        $this->pdo = $pdo;
        $this->max_attempts = $max_attempts;
        $this->lockout_times = $lockout_times;
        $this->ip_address = $this->getClientIP();
    }

    /**
     * Ottiene l'IP del client considerando proxy
     */
    private function getClientIP() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Se ci sono multiple IP (X-Forwarded-For), prendi il primo
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Valida l'IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Registra un tentativo fallito
     */
    public function recordFailedAttempt($username = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO login_attempts (ip_address, username, attempt_time, user_agent, successful)
                VALUES (?, ?, NOW(), ?, 0)
            ");
            $stmt->execute([
                $this->ip_address,
                $username,
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            // Log dell'errore ma continua
            error_log("RateLimiter: Failed to record attempt - " . $e->getMessage());
        }
    }

    /**
     * Controlla se l'IP/username è bloccato
     */
    public function isBlocked($username = null) {
        $attempts = $this->getRecentAttempts($username);

        if ($attempts >= $this->max_attempts * 3) {
            // Blocco permanente dopo 15+ tentativi falliti
            return ['blocked' => true, 'remaining_time' => -1, 'reason' => 'Troppi tentativi. Contatta l\'amministratore.'];
        }

        if ($attempts < $this->max_attempts) {
            // Meno di 5 tentativi falliti - non bloccare
            return ['blocked' => false];
        }

        // Calcola il livello di blocco basato sul numero di tentativi
        // 5-9 tentativi = livello 0 (15 minuti)
        // 10-14 tentativi = livello 1 (1 ora)
        $lockout_index = min(floor(($attempts - $this->max_attempts) / $this->max_attempts), count($this->lockout_times) - 1);
        $lockout_time = $this->lockout_times[$lockout_index];

        // Controlla se siamo ancora nel periodo di blocco dall'ultimo tentativo fallito
        $last_attempt = $this->getLastAttemptTime($username);
        if ($last_attempt && (time() - strtotime($last_attempt)) < $lockout_time) {
            $remaining = $lockout_time - (time() - strtotime($last_attempt));
            return [
                'blocked' => true,
                'remaining_time' => $remaining,
                'reason' => "Troppi tentativi falliti. Riprova tra " . ceil($remaining / 60) . " minuti."
            ];
        }

        return ['blocked' => false];
    }

    /**
     * Resetta i tentativi per un username specifico (dopo login riuscito)
     * Elimina tutti i tentativi precedenti per quell'utente
     */
    public function resetAttempts($username = null) {
        try {
            // Elimina tutti i tentativi precedenti per questo utente specifico
            $stmt = $this->pdo->prepare("
                DELETE FROM login_attempts
                WHERE username = ?
            ");
            $stmt->execute([$username]);

        } catch (Exception $e) {
            error_log("RateLimiter: Failed to reset attempts - " . $e->getMessage());
        }
    }

    /**
     * Pulisce i tentativi falliti più vecchi di 24 ore
     * Da chiamare periodicamente (es. al primo login)
     */
    public function cleanupOldAttempts() {
        try {
            // Elimina tentativi falliti più vecchi di 24 ore
            $stmt = $this->pdo->prepare("
                DELETE FROM login_attempts
                WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute();

        } catch (Exception $e) {
            error_log("RateLimiter: Failed to cleanup old attempts - " . $e->getMessage());
        }
    }

    /**
     * Ottiene il numero di tentativi recenti
     */
    private function getRecentAttempts($username = null) {
        try {
            $time_window = 3600; // 1 ora
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as attempts FROM login_attempts
                WHERE ip_address = ?
                AND successful = 0
                AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
                " . ($username ? "AND username = ?" : "")
            );

            if ($username) {
                $stmt->execute([$this->ip_address, $time_window, $username]);
            } else {
                $stmt->execute([$this->ip_address, $time_window]);
            }

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) $result['attempts'];

        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Ottiene l'ora dell'ultimo tentativo fallito
     */
    private function getLastAttemptTime($username = null) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT attempt_time FROM login_attempts
                WHERE ip_address = ?
                AND successful = 0
                " . ($username ? "AND username = ?" : "") . "
                ORDER BY attempt_time DESC
                LIMIT 1
            ");

            if ($username) {
                $stmt->execute([$this->ip_address, $username]);
            } else {
                $stmt->execute([$this->ip_address]);
            }

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['attempt_time'] : null;

        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Ottiene statistiche per l'admin
     */
    public function getStats() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_attempts,
                    SUM(CASE WHEN successful = 1 THEN 1 ELSE 0 END) as successful_logins,
                    SUM(CASE WHEN successful = 0 THEN 1 ELSE 0 END) as failed_attempts,
                    COUNT(DISTINCT ip_address) as unique_ips
                FROM login_attempts
                WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
?>