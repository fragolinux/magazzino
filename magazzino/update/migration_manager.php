<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-02-03 09:37:33 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-03 16:49:55
*/

/*
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
        $deferredFkStatements = [];
        
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

            // Se è una CREATE TABLE che contiene FOREIGN KEY, estrai i vincoli
            if (preg_match('/^\s*CREATE\s+TABLE/i', $s) && stripos($s, 'FOREIGN KEY') !== false) {
                if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $s, $m)) {
                    $tableName = $m[1];

                    // Trova tutte le clausole CONSTRAINT ... FOREIGN KEY ... in modo robusto
                        // Regex migliorata: consente azioni multi-token come 'SET NULL' o 'NO ACTION'
                        if (preg_match_all('/CONSTRAINT\s+`[^`]+`\s+FOREIGN\s+KEY\s*\([^\)]+\)\s*REFERENCES\s*`?\w+`?\s*\([^\)]+\)(?:\s+ON\s+DELETE\s+[^\\n,;()]+(?:\s+[^\\n,;()]+)?)?(?:\s+ON\s+UPDATE\s+[^\\n,;()]+(?:\s+[^\\n,;()]+)?)?/i', $s, $fkMatches)) {
                        foreach ($fkMatches[0] as $fkClause) {
                            $fkClause = trim($fkClause);
                            // Validazione minima della clausola trovata
                            if ($fkClause === '' || stripos($fkClause, 'FOREIGN KEY') === false || stripos($fkClause, 'REFERENCES') === false) {
                                continue;
                            }

                            // Assicuriamoci che la clausola non sia vuota o malformata
                            $deferredStmt = "ALTER TABLE `{$tableName}` ADD " . $fkClause . ";";
                            $deferredFkStatements[] = $deferredStmt;
                        }

                        // Rimuovi le clausole CONSTRAINT dal CREATE TABLE originale in modo sicuro
                            // Rimuovi le clausole CONSTRAINT dal CREATE TABLE originale in modo sicuro (stessa regex usata sopra)
                            $s = preg_replace('/,?\s*CONSTRAINT\s+`[^`]+`\s+FOREIGN\s+KEY\s*\([^\)]+\)\s*REFERENCES\s*`?\w+`?\s*\([^\)]+\)(?:\s+ON\s+DELETE\s+[^\\n,;()]+(?:\s+[^\\n,;()]+)?)?(?:\s+ON\s+UPDATE\s+[^\\n,;()]+(?:\s+[^\\n,;()]+)?)?/i', '', $s);

                        // Rimuovi eventuali virgole duplicate prima della chiusura della parentesi
                        $s = preg_replace('/,\s*,+/', ',', $s);
                        $s = preg_replace('/,\s*\)/', '\\n)', $s);
                        $s = trim($s);
                        if (!str_ends_with($s, ';')) {
                            $s .= ';';
                        }
                    }
                }
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

                // Gestione idempotente per ALTER TABLE ADD INDEX / FULLTEXT INDEX
                if (preg_match('/ALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+(?:FULLTEXT\s+)?INDEX\s+`?(\w+)`?/i', $s, $idxMatches)) {
                    $tableName = $idxMatches[1];
                    $indexName = $idxMatches[2];

                    $stmtIdx = $this->pdo->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
                    $stmtIdx->execute([$tableName, $indexName]);
                    $rowIdx = $stmtIdx->fetch(PDO::FETCH_ASSOC);
                    $stmtIdx->closeCursor();

                    if ($rowIdx && intval($rowIdx['cnt']) > 0) {
                        $skipped++;
                        $details[] = "⊘ Indice '{$indexName}' già esistente in '{$tableName}'";
                        continue;
                    }
                    // Altrimenti procediamo normalmente (eseguiamo l'ALTER)
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
                // 1091 = Can't DROP INDEX (indice non presente)
                if (in_array($errorCode, ['1050', '1060', '1061', '1062', '1091']) || 
                    strpos($errorMsg, 'already exists') !== false ||
                    strpos($errorMsg, 'Duplicate') !== false ||
                    strpos($errorMsg, "Can't DROP INDEX") !== false ||
                    strpos($errorMsg, 'check that it exists') !== false) {
                    $skipped++;
                    $shortStmt = strlen($s) > 60 ? substr($s, 0, 60) . '...' : $s;
                    $details[] = "⊘ " . $shortStmt . " (già esistente o non applicabile)";
                } else {
                    $errors++;
                    $details[] = "✗ ERRORE: " . $e->getMessage();
                }
            }
        }
        
        // Esegui eventuali ALTER TABLE per aggiungere foreign key raccolte
        if (!empty($deferredFkStatements)) {
            // Evita duplicati
            $deferredFkStatements = array_values(array_unique($deferredFkStatements));

            foreach ($deferredFkStatements as $fkStmt) {
                // Estrai nome tabella target e tabella referenziata per controllo esistenza
                $targetTable = null;
                $refTable = null;
                if (preg_match('/ALTER\s+TABLE\s+`?(\w+)`?/i', $fkStmt, $m)) {
                    $targetTable = $m[1];
                }
                if (preg_match('/REFERENCES\s+`?(\w+)`?/i', $fkStmt, $m2)) {
                    $refTable = $m2[1];
                }

                // Controlla esistenza tabelle prima di eseguire
                $canExecute = true;
                if ($targetTable) {
                    $r = $this->pdo->query("SHOW TABLES LIKE '" . $targetTable . "'");
                    $existsT = $r && $r->rowCount() > 0;
                    if ($r) $r->closeCursor();
                    if (!$existsT) $canExecute = false;
                }
                if ($refTable) {
                    $r2 = $this->pdo->query("SHOW TABLES LIKE '" . $refTable . "'");
                    $existsR = $r2 && $r2->rowCount() > 0;
                    if ($r2) $r2->closeCursor();
                    if (!$existsR) $canExecute = false;
                }

                if (!$canExecute) {
                    $skipped++;
                    $details[] = "⊘ Deferred skipped: missing table for statement: " . $fkStmt;
                    continue;
                }

                try {
                    $affected = $this->pdo->exec($fkStmt);
                    $executed++;
                    $short = strlen($fkStmt) > 120 ? substr($fkStmt, 0, 120) . '...' : $fkStmt;
                    $details[] = "✓ Deferred: " . $short;
                } catch (PDOException $e) {
                    $errCode = $e->getCode();
                    $errMsg = $e->getMessage();
                    if (in_array($errCode, ['1050','1060','1061','1062','1091']) || strpos($errMsg,'already exists')!==false) {
                        $skipped++;
                        $details[] = "⊘ Deferred: " . $fkStmt . " (già esistente o non applicabile)";
                    } else {
                        $errors++;
                        $details[] = "✗ Deferred ERRORE: " . $e->getMessage();
                    }
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
        
        // Se è la prima volta, partiamo da 0.9 per includere anche 1.0
        // Ogni migrazione deve essere idempotente (con IF NOT EXISTS, ecc.)
        // quindi quelle già applicate verranno saltate automaticamente
        $currentVersion = $isFirstRun ? '0.9' : $this->getCurrentVersion();
        
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
                
                // Post-hook: se la migrazione è 1.8, aggiorna le credenziali nel file config/database.php
                if (version_compare($version, '1.8', '=')) {
                    $this->updateDatabaseConfigAfter18();
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
     * Aggiorna il file config/database.php dopo la migrazione 1.8
     * Sostituisce le credenziali da root a magazzino_user
     */
    private function updateDatabaseConfigAfter18() {
        $configFile = dirname($this->migrationsDir) . '/../config/database.php';
        
        if (!file_exists($configFile)) {
            return; // File non esiste, salta silenziosamente
        }
        
        try {
            $content = file_get_contents($configFile);
            
            // Controlla se le credenziali sono ancora 'root' prima di sostituire
            if (preg_match("/'user'\\s*=>\\s*'root'/", $content)) {
                // Sostituisci le credenziali root con magazzino_user
                $newContent = preg_replace(
                    "/('user'\\s*=>\\s*)'root'/",
                    "$1'magazzino_user'",
                    $content
                );
                $newContent = preg_replace(
                    "/('pass'\\s*=>\\s*)'[^']*'/",
                    "$1'SecurePass2024!'",
                    $newContent
                );
                
                file_put_contents($configFile, $newContent);
            }
        } catch (Exception $e) {
            // Ignora silenziosamente gli errori di scrittura
            // (il file potrebbe essere read-only o i permessi potrebbero mancare)
        }
    }
}