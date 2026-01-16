<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-15
 * 
 * Migration Manager - Sistema intelligente di gestione aggiornamenti DB
 * Applica solo le migrazioni necessarie in base alla versione corrente
 */

class MigrationManager {
    private $pdo;
    private $migrationsDir;
    
    public function __construct($pdo, $migrationsDir = null) {
        $this->pdo = $pdo;
        $this->migrationsDir = $migrationsDir ?? __DIR__ . '/migrations';
        
        // FORZA il buffering delle query per evitare errore 2014
        $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    }
    
    /**
     * Controlla se la tabella db_version esiste
     */
    private function dbVersionTableExists() {
        try {
            $result = $this->pdo->query("SHOW TABLES LIKE 'db_version'");
            $exists = $result && $result->rowCount() > 0;
            $result->closeCursor(); // Chiude il cursore per liberare la connessione
            return $exists;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Crea la tabella db_version se non esiste
     */
    private function createDbVersionTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `db_version` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `version` VARCHAR(10) NOT NULL COMMENT 'Numero versione (es: 1.4)',
            `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data applicazione',
            `description` TEXT NULL COMMENT 'Descrizione aggiornamento',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
    }
    
    /**
     * Ottiene la versione corrente del database
     */
    public function getCurrentVersion() {
        if (!$this->dbVersionTableExists()) {
            return '1.0'; // Versione iniziale se la tabella non esiste
        }
        
        try {
            $stmt = $this->pdo->query("SELECT version FROM db_version ORDER BY id DESC LIMIT 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor(); // Chiude il cursore
            return $result ? $result['version'] : '1.0';
        } catch (PDOException $e) {
            return '1.0';
        }
    }
    
    /**
     * Registra una versione nella tabella db_version
     */
    public function recordVersion($version, $description = '') {
        if (!$this->dbVersionTableExists()) {
            $this->createDbVersionTable();
        }
        
        // Controlla se la versione esiste già
        $stmt = $this->pdo->prepare("SELECT id FROM db_version WHERE version = ?");
        $stmt->execute([$version]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Aggiorna la descrizione se diversa
            $stmt = $this->pdo->prepare("UPDATE db_version SET description = ?, applied_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$description, $existing['id']]);
        } else {
            // Inserisci nuova
            $stmt = $this->pdo->prepare("INSERT INTO db_version (version, description) VALUES (?, ?)");
            $stmt->execute([$version, $description]);
        }
    }
    
    /**
     * Ottiene l'elenco delle migrazioni disponibili ordinate per versione
     */
    private function getAvailableMigrations() {
        $migrations = [];
        $files = glob($this->migrationsDir . '/*.sql');
        
        foreach ($files as $file) {
            $filename = basename($file, '.sql');
            // Estrai il numero di versione (es: 1.1, 1.2, ecc.)
            if (preg_match('/^(\d+\.\d+)/', $filename, $matches)) {
                $version = $matches[1];
                $migrations[$version] = $file;
            }
        }
        
        // Ordina per versione
        uksort($migrations, 'version_compare');
        
        return $migrations;
    }
    
    /**
     * Esegue un file SQL con gestione intelligente degli errori
     */
    private function executeSqlFile($file) {
        $content = file_get_contents($file);
        if (trim($content) === '') {
            return ['success' => 0, 'skipped' => 0, 'errors' => 0, 'details' => ['⊘ File vuoto o solo commenti']];
        }
        
        // Dividi in statement
        $statements = preg_split('/;\s*\n|;\s*$/', $content);
        $executed = 0;
        $skipped = 0;
        $errors = 0;
        $details = [];
        
        foreach ($statements as $stmt) {
            $s = trim($stmt);
            
            // Rimuovi commenti SQL
            $s = preg_replace('/--.*$/m', '', $s);
            $s = preg_replace('/\/\*.*?\*\//s', '', $s);
            $s = preg_replace('/^#.*$/m', '', $s);
            $s = trim($s);
            
            // Salta righe vuote
            if ($s === '' || $s === ';') continue;
            
            // Aggiungi punto e virgola se manca
            if (!str_ends_with($s, ';')) {
                $s .= ';';
            }
            
            try {
                // Gestione speciale per CREATE TABLE IF NOT EXISTS
                if (preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?(\w+)`?/i', $s, $matches)) {
                    $tableName = $matches[1];
                    $checkTable = $this->pdo->query("SHOW TABLES LIKE '$tableName'");
                    $exists = $checkTable && $checkTable->rowCount() > 0;
                    $checkTable->closeCursor(); // Chiudi il cursore SEMPRE
                    if ($exists) {
                        $skipped++;
                        $details[] = "⊘ Tabella '$tableName' già esistente";
                        continue;
                    }
                }
                
                // Gestione speciale per ALTER TABLE ADD COLUMN IF NOT EXISTS (MySQL 5.7+)
                // In MySQL 5.7 non esiste IF NOT EXISTS per ALTER TABLE, quindi controlliamo manualmente
                if (preg_match('/ALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+`?(\w+)`?/i', $s, $matches)) {
                    $tableName = $matches[1];
                    $columnName = $matches[2];
                    $checkColumn = $this->pdo->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
                    $exists = $checkColumn && $checkColumn->rowCount() > 0;
                    $checkColumn->closeCursor(); // Chiudi il cursore SEMPRE
                    if ($exists) {
                        $skipped++;
                        $details[] = "⊘ Colonna '$columnName' già esistente in '$tableName'";
                        continue;
                    }
                    // Rimuovi IF NOT EXISTS perché MySQL non lo supporta in ALTER TABLE
                    $s = preg_replace('/IF\s+NOT\s+EXISTS\s+/i', '', $s);
                }
                
                // Esegui lo statement
                $affectedRows = $this->pdo->exec($s);
                
                // Determina se è una query DDL
                $isDDL = preg_match('/^\s*(CREATE|ALTER|DROP|TRUNCATE|RENAME)\s+/i', $s) > 0;
                
                if ($isDDL || $affectedRows > 0) {
                    $executed++;
                    $shortStmt = strlen($s) > 60 ? substr($s, 0, 60) . '...' : $s;
                    $details[] = "✓ " . $shortStmt;
                } else {
                    $skipped++;
                    $shortStmt = strlen($s) > 60 ? substr($s, 0, 60) . '...' : $s;
                    $details[] = "⊘ " . $shortStmt;
                }
                
            } catch (PDOException $e) {
                // Controlla errori comuni che possono essere ignorati
                $errorCode = $e->getCode();
                $errorMsg = $e->getMessage();
                
                // 1050 = Table already exists
                // 1060 = Duplicate column name
                // 1061 = Duplicate key name
                // 1062 = Duplicate entry
                if (in_array($errorCode, ['1050', '1060', '1061', '1062']) || 
                    strpos($errorMsg, 'already exists') !== false ||
                    strpos($errorMsg, 'Duplicate') !== false) {
                    $skipped++;
                    $shortStmt = strlen($s) > 60 ? substr($s, 0, 60) . '...' : $s;
                    $details[] = "⊘ " . $shortStmt . " (già esistente)";
                } else {
                    $errors++;
                    $details[] = "✗ ERRORE: " . $e->getMessage();
                }
            }
        }
        
        return [
            'success' => $executed,
            'skipped' => $skipped,
            'errors' => $errors,
            'details' => $details
        ];
    }
    
    /**
     * Applica tutte le migrazioni pendenti
     * Con auto-inizializzazione intelligente
     */
    public function runPendingMigrations() {
        // Se la tabella db_version non esiste, è la prima volta che usiamo il sistema
        $isFirstRun = !$this->dbVersionTableExists();
        
        // Se è la prima volta, partiamo da 1.0 e applichiamo TUTTE le migrazioni
        // Ogni migrazione deve essere idempotente (con IF NOT EXISTS, ecc.)
        // quindi quelle già applicate verranno saltate automaticamente
        $currentVersion = $isFirstRun ? '1.0' : $this->getCurrentVersion();
        
        $pending = $this->getPendingMigrationsFrom($currentVersion);
        $results = [];
        
        if (empty($pending)) {
            return [
                'currentVersion' => $this->dbVersionTableExists() ? $this->getCurrentVersion() : $currentVersion,
                'applied' => [],
                'message' => 'Nessuna migrazione da applicare. Database già aggiornato.',
                'firstRun' => $isFirstRun,
                'detectedVersion' => null
            ];
        }
        
        foreach ($pending as $version => $file) {
            $result = $this->executeSqlFile($file);
            
            // Se non ci sono errori gravi, registra la versione
            if ($result['errors'] === 0 || ($result['success'] > 0 || $result['skipped'] > 0)) {
                $description = "Migrazione da file " . basename($file);
                
                // La tabella potrebbe essere stata creata dalla migrazione 1.6
                // Assicurati che esista prima di registrare
                if ($this->dbVersionTableExists()) {
                    $this->recordVersion($version, $description);
                }
                
                $results[] = [
                    'version' => $version,
                    'status' => 'success',
                    'stats' => $result
                ];
            } else {
                $results[] = [
                    'version' => $version,
                    'status' => 'failed',
                    'stats' => $result
                ];
                // Ferma le migrazioni in caso di errore
                break;
            }
        }
        
        return [
            'currentVersion' => $this->getCurrentVersion(),
            'applied' => $results,
            'message' => count($results) . ' migrazione/i applicata/e',
            'firstRun' => $isFirstRun,
            'detectedVersion' => $currentVersion
        ];
    }
    
    /**
     * Ottiene le migrazioni pendenti a partire da una versione specifica
     */
    private function getPendingMigrationsFrom($fromVersion) {
        $allMigrations = $this->getAvailableMigrations();
        $pending = [];
        
        foreach ($allMigrations as $version => $file) {
            if (version_compare($version, $fromVersion, '>')) {
                $pending[$version] = $file;
            }
        }
        
        return $pending;
    }
    
    /**
     * Inizializza il sistema di versioning su un database esistente
     * (usato solo da init_versioning.php per inizializzazione manuale)
     */
    public function initializeVersioning($currentVersion = '1.6') {
        if (!$this->dbVersionTableExists()) {
            $this->createDbVersionTable();
        }
        
        // Registra la versione corrente come punto di partenza
        $this->recordVersion($currentVersion, 'Inizializzazione sistema di versioning');
        
        return [
            'success' => true,
            'message' => "Sistema di versioning inizializzato alla versione $currentVersion"
        ];
    }
}
