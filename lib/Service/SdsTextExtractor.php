<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Service;

class SdsTextExtractor {
    private function executable(string $name): string {
        $packages = ['pdftotext' => 'poppler-utils', 'pdftoppm' => 'poppler-utils',
            'tesseract' => 'tesseract-ocr', 'timeout' => 'coreutils'];
        foreach (array_unique(array_merge(['/usr/bin', '/usr/local/bin', '/bin'], explode(PATH_SEPARATOR, (string)getenv('PATH')))) as $directory) {
            if ($directory === '' || !str_starts_with($directory, '/')) continue;
            $path = rtrim($directory, '/') . '/' . $name;
            if (@is_file($path) && @is_executable($path)) return $path;
        }
        throw new \RuntimeException('Server-Werkzeug ' . $name . ' fehlt oder ist für PHP nicht ausführbar. Auf dem Nextcloud-Server das Paket ' . $packages[$name] . ' installieren bzw. die PHP-Ausführungsrechte prüfen.');
    }

    private function failure(string $tool, int $exit, string $detail): string {
        if ($exit === 124) return $tool . ': Zeitlimit von 12 Sekunden erreicht. Bitte erneut versuchen oder eine kleinere PDF-Version verwenden.';
        if ($exit === 137) return $tool . ' wurde vom Server beendet (Exit 137). Speicher- und Prozesslimits des Servers prüfen.';
        if (in_array($exit, [126, 127], true)) return $tool . ' wurde gefunden, konnte aber nicht ausgeführt werden. Server-Berechtigungen und benötigte Systembibliotheken prüfen (Exit ' . $exit . ').';
        if ($tool === 'tesseract' && preg_match('/(?:Failed loading language|Error opening data file|Could not initialize tesseract)/i', $detail)) {
            return 'Tesseract kann die Sprachdaten deu/eng nicht laden. Auf dem Nextcloud-Server tesseract-ocr-deu und tesseract-ocr-eng installieren; gegebenenfalls TESSDATA_PREFIX und Leserechte prüfen.';
        }
        if (preg_match('/(?:incorrect password|password required|encrypted)/i', $detail)) return $tool . ': Das PDF ist geschützt. Bitte eine lesbare PDF-Version ohne Kennwort hochladen.';
        return 'Verarbeitung mit ' . $tool . ' fehlgeschlagen (Exit ' . $exit . '). PDF im Original öffnen und prüfen; bei Bedarf neu als PDF exportieren. Auch die temporäre Ablage muss für PHP beschreibbar sein.';
    }

    private function run(array $command, string $directory): string {
        $tool = $command[0];
        $command[0] = $this->executable($tool);
        $timeout = $this->executable('timeout');
        $pipes = [];
        $process = @proc_open(array_merge([$timeout, '12s'], $command),
            [0 => ['pipe', 'r'], 1 => ['file', $directory . '/stdout', 'w'], 2 => ['file', $directory . '/stderr', 'w']], $pipes);
        if (!is_resource($process)) throw new \RuntimeException($tool . ' konnte nicht gestartet werden. PHP-Prozessrechte und temporäre Ablage prüfen.');
        fclose($pipes[0]);
        $exit = proc_close($process);
        if ($exit !== 0) {
            // Use stderr only to classify errors; never expose PDF text, paths or raw tool output.
            $detail = (string)file_get_contents($directory . '/stderr', false, null, 0, 8192);
            throw new \RuntimeException($this->failure($tool, $exit, $detail));
        }
        if (filesize($directory . '/stdout') > 1024 * 1024) throw new \RuntimeException('PDF-Text ist zu groß.');
        return (string)file_get_contents($directory . '/stdout');
    }

    public function extract(string $bytes): array {
        if (!function_exists('proc_open')) throw new \RuntimeException('PDF-Auswertung benötigt proc_open auf dem Server.');
        if (strlen($bytes) > 15 * 1024 * 1024 || !str_starts_with($bytes, '%PDF-')) throw new \InvalidArgumentException('Bitte ein SDB-PDF bis 15 MB verwenden.');
        $directory = sys_get_temp_dir() . '/gsk-sds-' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700)) throw new \RuntimeException('Temporäre Ablage nicht verfügbar.');
        try {
            if (file_put_contents($directory . '/input.pdf', $bytes) !== strlen($bytes)) throw new \RuntimeException('PDF konnte nicht vorbereitet werden.');
            $text = $this->run(['pdftotext', '-f', '1', '-l', '4', '-layout', '-enc', 'UTF-8', $directory . '/input.pdf', '-'], $directory);
            $pages = explode("\f", $text);
            if (end($pages) === '') array_pop($pages);
            $ocrPages = [];
            foreach ($pages as $index => &$page) {
                if (mb_strlen(preg_replace('/\s/u', '', $page)) >= 100) continue;
                $number = (string)($index + 1);
                $this->run(['pdftoppm', '-f', $number, '-l', $number, '-singlefile', '-scale-to', '2200', '-png', $directory . '/input.pdf', $directory . '/page'], $directory);
                $page = $this->run(['tesseract', $directory . '/page.png', 'stdout', '-l', 'deu+eng', '--psm', '3'], $directory);
                $ocrPages[] = $index + 1;
            }
            unset($page);
            return ['pages' => $pages, 'ocr_pages' => $ocrPages];
        } finally {
            foreach (['input.pdf', 'stdout', 'stderr', 'page.png'] as $file) if (is_file($directory . '/' . $file)) unlink($directory . '/' . $file);
            rmdir($directory);
        }
    }
}
