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
echo "Text PDF, scanned PDF and invalid input extraction passed\n";
