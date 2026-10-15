<?php
declare(strict_types=1);
require __DIR__ . '/../lib/Service/SdsTextExtractor.php';
require __DIR__ . '/../lib/Service/SdsAnalysis.php';
use OCA\Gefahrstoffkataster\Service\SdsTextExtractor;
use OCA\Gefahrstoffkataster\Service\SdsAnalysis;
// Supply two synthetic PDFs (text and image-only) to exercise real local tools.
if ($argc !== 3) { fwrite(STDERR, "Usage: php tests/sds-extraction.php text.pdf scan.pdf\n"); exit(1); }
foreach ([$argv[1], $argv[2]] as $index => $file) {
    $text = (new SdsTextExtractor())->extract(file_get_contents($file));
    if (($text['ocr_pages'] !== []) !== ($index === 1)) throw new RuntimeException('Wrong OCR path');
    $result = SdsAnalysis::parse($text['pages']);
    $values = array_column($result['suggestions'], 'value', 'field');
    if ($result['status'] !== 'classified' || ($values['signal'] ?? '') !== 'Gefahr' || !str_contains($values['h_statements'] ?? '', 'H314') || str_contains($values['h_statements'] ?? '', 'H301')) throw new RuntimeException('Extraction failed');
}
try { (new SdsTextExtractor())->extract('not a PDF'); throw new LogicException('Invalid input accepted'); }
catch (InvalidArgumentException $e) {}
$processPath = getenv('PATH');
putenv('PATH=/gsk-no-tools-in-path');
try {
    $text = (new SdsTextExtractor())->extract(file_get_contents($argv[1]));
    if (!str_contains(implode('', $text['pages']), 'H314')) throw new LogicException('Standard tools not found with restricted PHP PATH');
} finally { $processPath === false ? putenv('PATH') : putenv('PATH=' . $processPath); }
$prefix = getenv('TESSDATA_PREFIX');
putenv('TESSDATA_PREFIX=' . sys_get_temp_dir() . '/gsk-missing-language-' . bin2hex(random_bytes(8)));
try {
    try { (new SdsTextExtractor())->extract(file_get_contents($argv[2])); throw new LogicException('Missing languages accepted'); }
    catch (RuntimeException $e) { if (!str_contains($e->getMessage(), 'Sprachdaten')) throw $e; }
} finally { $prefix === false ? putenv('TESSDATA_PREFIX') : putenv('TESSDATA_PREFIX=' . $prefix); }
try { (new SdsTextExtractor())->extract("%PDF-1.4\nincomplete\n"); throw new LogicException('Broken PDF accepted'); }
catch (RuntimeException $e) { if (!str_contains($e->getMessage(), 'pdftotext')) throw $e; }
echo "Text PDF, scanned PDF, restricted PATH, missing languages and invalid/broken input extraction passed\n";
