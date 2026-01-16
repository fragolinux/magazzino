<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-01-15 18:42:46 
 * @Last Modified by:   gabriele.riva
 * @Last Modified time: 2026-01-15 18:42:46
*/

/*
 * Validatore sicuro per upload di file
 * Previene upload di file malevoli e valida contenuto
 */

class SecureUploadValidator {
    private $allowedMimeTypes = [
        // Immagini
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],

        // Documenti
        'application/pdf' => ['pdf'],
        'text/plain' => ['txt', 'csv'], // CSV spesso rilevati come text/plain
        'text/csv' => ['csv'],
        'application/csv' => ['csv'], // Alcuni sistemi rilevano CSV come application/csv
        'application/vnd.ms-excel' => ['xls', 'csv'], // Alcuni CSV rilevati come Excel
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    ];

    private $maxFileSize = 10 * 1024 * 1024; // 10MB default
    private $uploadDir;

    public function __construct($uploadDir = null, $maxFileSize = null) {
        $this->uploadDir = $uploadDir ?: __DIR__ . '/../uploads/';
        $this->maxFileSize = $maxFileSize ?: $this->maxFileSize;

        // Crea directory se non esiste
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Valida un file upload
     */
    public function validateUpload($file, $allowedTypes = null) {
        $errors = [];

        // Verifica errori di upload PHP
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->getUploadErrorMessage($file['error']);
            return ['valid' => false, 'errors' => $errors];
        }

        // Verifica se è un file realmente uploadato
        if (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'File non valido o upload fallito.';
            return ['valid' => false, 'errors' => $errors];
        }

        // Verifica dimensione file
        if ($file['size'] > $this->maxFileSize) {
            $errors[] = 'File troppo grande. Dimensione massima: ' . $this->formatBytes($this->maxFileSize);
            return ['valid' => false, 'errors' => $errors];
        }

        // Verifica dimensione file (doppia verifica)
        if ($file['size'] != filesize($file['tmp_name'])) {
            $errors[] = 'Dimensione file non valida.';
            return ['valid' => false, 'errors' => $errors];
        }

        // Rileva MIME type dal contenuto del file
        $detectedMime = $this->detectMimeType($file['tmp_name']);
        if (!$detectedMime) {
            $errors[] = 'Impossibile determinare il tipo di file.';
            return ['valid' => false, 'errors' => $errors];
        }

        // Verifica estensione del file
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (empty($fileExtension)) {
            $errors[] = 'Estensione file mancante.';
            return ['valid' => false, 'errors' => $errors];
        }

        // Verifica che l'estensione corrisponda al MIME type rilevato
        $expectedExtensions = $this->allowedMimeTypes[$detectedMime] ?? [];
        if (!in_array($fileExtension, $expectedExtensions)) {
            $errors[] = 'Estensione file non valida per il tipo di contenuto rilevato.';
            return ['valid' => false, 'errors' => $errors];
        }

        // Verifica tipi consentiti
        $allowedMimeTypes = $allowedTypes ?: array_keys($this->allowedMimeTypes);
        if (!in_array($detectedMime, $allowedMimeTypes)) {
            $errors[] = 'Tipo di file non consentito.';
            return ['valid' => false, 'errors' => $errors];
        }

        // Verifica contenuto del file per immagini
        if (strpos($detectedMime, 'image/') === 0) {
            if (!$this->validateImageContent($file['tmp_name'], $detectedMime)) {
                $errors[] = 'Contenuto immagine non valido o potenzialmente malevolo.';
                return ['valid' => false, 'errors' => $errors];
            }
        }

        return [
            'valid' => true,
            'mime_type' => $detectedMime,
            'extension' => $fileExtension,
            'safe_filename' => $this->sanitizeFilename($file['name']),
            'size' => $file['size']
        ];
    }

    /**
     * Salva un file upload validato
     */
    public function saveValidatedFile($file, $validationResult, $customFilename = null) {
        if (!$validationResult['valid']) {
            return ['success' => false, 'error' => 'File non validato'];
        }

        $filename = $customFilename ?: $this->generateUniqueFilename($validationResult['safe_filename'], $validationResult['extension']);
        $filepath = $this->uploadDir . $filename;

        // Sposta il file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'error' => 'Errore durante il salvataggio del file'];
        }

        // Verifica che il file sia stato salvato correttamente
        if (!file_exists($filepath) || filesize($filepath) !== $validationResult['size']) {
            @unlink($filepath); // Rimuovi file se corrotto
            return ['success' => false, 'error' => 'File salvato in modo non corretto'];
        }

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => $validationResult['size'],
            'mime_type' => $validationResult['mime_type']
        ];
    }

    /**
     * Rileva il MIME type dal contenuto del file
     */
    private function detectMimeType($filepath) {
        // Usa finfo per rilevare il MIME type
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $filepath);
                finfo_close($finfo);
                return $mime;
            }
        }

        // Fallback: usa mime_content_type se disponibile
        if (function_exists('mime_content_type')) {
            return mime_content_type($filepath);
        }

        return false;
    }

    /**
     * Valida il contenuto di un'immagine
     */
    private function validateImageContent($filepath, $mimeType) {
        // Verifica che sia un'immagine valida usando getimagesize
        $imageInfo = @getimagesize($filepath);
        if (!$imageInfo) {
            return false;
        }

        // Verifica che il MIME type corrisponda
        $expectedMime = $imageInfo['mime'];
        if ($expectedMime !== $mimeType) {
            return false;
        }

        // Verifica dimensioni minime (previene immagini 1x1 pixel malevole)
        if ($imageInfo[0] < 1 || $imageInfo[1] < 1) {
            return false;
        }

        // Verifica che non sia un file con estensione immagine ma contenuto diverso
        $handle = fopen($filepath, 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 12);
        fclose($handle);

        // Verifica magic bytes per diversi formati
        switch ($mimeType) {
            case 'image/jpeg':
                return substr($header, 0, 2) === "\xFF\xD8";
            case 'image/png':
                return substr($header, 0, 8) === "\x89PNG\r\n\x1A\n";
            case 'image/gif':
                return substr($header, 0, 6) === 'GIF87a' || substr($header, 0, 6) === 'GIF89a';
            case 'image/webp':
                return substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP';
            default:
                return false;
        }
    }

    /**
     * Sanitizza il nome del file
     */
    private function sanitizeFilename($filename) {
        // Rimuovi caratteri pericolosi
        $filename = preg_replace('/[^a-zA-Z0-9\.\-_]/', '', $filename);

        // Rimuovi sequenze pericolose
        $filename = str_replace(['..', './', '/.', '\\'], '', $filename);

        // Limita lunghezza
        if (strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $filename = substr(pathinfo($filename, PATHINFO_FILENAME), 0, 250) . '.' . $extension;
        }

        return $filename;
    }

    /**
     * Genera un nome file univoco
     */
    private function generateUniqueFilename($originalName, $extension) {
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $basename = preg_replace('/[^a-zA-Z0-9\-_]/', '', $basename);

        do {
            $random = bin2hex(random_bytes(8));
            $filename = $basename . '_' . $random . '.' . $extension;
        } while (file_exists($this->uploadDir . $filename));

        return $filename;
    }

    /**
     * Ottiene il messaggio di errore per upload PHP
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File troppo grande (limite PHP ini).';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File troppo grande (limite form).';
            case UPLOAD_ERR_PARTIAL:
                return 'Upload incompleto.';
            case UPLOAD_ERR_NO_FILE:
                return 'Nessun file selezionato.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Directory temporanea mancante.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Errore di scrittura.';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload bloccato da estensione.';
            default:
                return 'Errore durante l\'upload.';
        }
    }

    /**
     * Formatta bytes in formato leggibile
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Imposta tipi MIME consentiti
     */
    public function setAllowedMimeTypes($mimeTypes) {
        $this->allowedMimeTypes = $mimeTypes;
    }

    /**
     * Imposta dimensione massima file
     */
    public function setMaxFileSize($size) {
        $this->maxFileSize = $size;
    }

    /**
     * Imposta directory upload
     */
    public function setUploadDir($dir) {
        $this->uploadDir = rtrim($dir, '/') . '/';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
}
?>