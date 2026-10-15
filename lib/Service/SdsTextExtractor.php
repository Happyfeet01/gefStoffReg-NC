<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Service;

class SdsTextExtractor {
    private function run(array $command, string $directory): string {
        $pipes = [];
        $process = proc_open(array_merge(['/usr/bin/timeout', '12s'], $command),
            [0 => ['pipe', 'r'], 1 => ['file', $directory . '/stdout', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        if (!is_resource($process)) throw new \RuntimeException('PDF-Werkzeuge konnten nicht gestartet werden.');
        fclose($pipes[0]);
        if (proc_close($process) !== 0) throw new \RuntimeException('PDF-Auswertung fehlgeschlagen. poppler-utils, Tesseract und Sprachdaten deu/eng prüfen; geschützte oder beschädigte PDFs werden nicht unterstützt.');
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
            foreach (['input.pdf', 'stdout', 'page.png'] as $file) if (is_file($directory . '/' . $file)) unlink($directory . '/' . $file);
            rmdir($directory);
        }
    }
}
