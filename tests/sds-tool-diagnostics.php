<?php
declare(strict_types=1);
require __DIR__ . '/../lib/Service/SdsTextExtractor.php';
use OCA\Gefahrstoffkataster\Service\SdsTextExtractor;
$extractor = new SdsTextExtractor();
$method = new ReflectionMethod($extractor, 'failure');
foreach ([
    ['pdftotext', 124, '', 'Zeitlimit'],
    ['pdftoppm', 137, '', 'Prozesslimits'],
    ['tesseract', 126, '', 'nicht ausgeführt'],
    ['pdftotext', 127, '', 'Systembibliotheken'],
    ['tesseract', 1, 'Error opening data file /private/deu.traineddata', 'Sprachdaten'],
    ['tesseract', 1, 'Failed loading language eng', 'tesseract-ocr-eng'],
    ['pdftotext', 1, 'Incorrect password', 'geschützt'],
    ['pdftotext', 1, 'PDF text: PRIVATE DOCUMENT CONTENT /private/input.pdf', 'Exit 1'],
] as [$tool, $exit, $detail, $expected]) {
    $message = $method->invoke($extractor, $tool, $exit, $detail);
    if (!str_contains($message, $expected) || str_contains($message, 'PRIVATE DOCUMENT') || str_contains($message, '/private')) throw new RuntimeException('Incorrect or leaking diagnostic');
}
$resolve = new ReflectionMethod($extractor, 'executable');
if (!is_executable($resolve->invoke($extractor, 'timeout'))) throw new RuntimeException('timeout resolution failed');
echo "PDF tool timeout, execution, language, protected-document and privacy diagnostics passed\n";
