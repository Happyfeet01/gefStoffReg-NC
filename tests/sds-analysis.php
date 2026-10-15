<?php
declare(strict_types=1);
require __DIR__ . '/../lib/Service/SdsAnalysis.php';
use OCA\Gefahrstoffkataster\Service\SdsAnalysis;
function checkSds(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function values(array $result): array { return array_column($result['suggestions'], 'value', 'field'); }
$result = SdsAnalysis::parse([
    "ABSCHNITT 1: Bezeichnung\nH999 nicht übernehmen\nABSCHNITT 2: Mögliche Gefahren\n2.1 Einstufung\nSkin Corr. 1A H314\n2.2 Kennzeichnungselemente\nSignalwort: Gefahr\nGHS05\nH314 Verursacht schwere Verätzungen der Haut und schwere Augenschäden.",
    "EUH031 Entwickelt bei Berührung mit Säure giftige Gase.\nABSCHNITT 3: Zusammensetzung\nH301 Giftig beim Verschlucken\nABSCHNITT 16: Sonstige Angaben\nH302 Gesundheitsschädlich",
]);
checkSds($result['status'] === 'classified', 'Classification missed');
checkSds(values($result)['signal'] === 'Gefahr', 'Signal word missed');
checkSds(values($result)['ghs'] === 'GHS05', 'Explicit pictogram missed');
checkSds(str_contains(values($result)['h_statements'], 'EUH031'), 'EUH missed');
checkSds(!str_contains(values($result)['h_statements'], 'H301'), 'Ingredient hazard leaked');
checkSds(!str_contains(values($result)['h_statements'], 'H999'), 'Section 1 leaked');
checkSds($result['evidence'][count($result['evidence'])-1]['page'] === 2, 'Page lost');
checkSds(SdsAnalysis::parse(['H314 Gefahr GHS05'])['status'] === 'unclear', 'Missing section treated as clear');
$negative = "ABSCHNITT 2: Mögliche Gefahren\n2.1 Einstufung\nDas Gemisch ist nicht als gefährlich eingestuft.\n2.2 Kennzeichnung";
checkSds(SdsAnalysis::parse([$negative])['status'] === 'not_classified', 'Explicit negative missed');
checkSds(SdsAnalysis::parse([$negative . "\nGHS05"])['status'] === 'unclear', 'Contradiction ignored');
checkSds(SdsAnalysis::parse([$negative . "\nEUH210 Sicherheitsdatenblatt auf Anfrage erhältlich."])['status'] === 'unclear', 'Supplementary statement ignored');
checkSds(SdsAnalysis::parse(["ABSCHNITT 2: Mögliche Gefahren\n2.3 Sonstige Gefahren\nDas Produkt ist nicht als gefährlich eingestuft."])['status'] === 'unclear', 'Wrong subsection cleared');
checkSds(!isset(values(SdsAnalysis::parse([$negative . "\nSignalwort: Gefahr\nSignalwort: Achtung"]))['signal']), 'Conflicting signal proposed');
checkSds(values(SdsAnalysis::parse(["2 Mögliche Gefahren\n2.2 Kennzeichnung\nH300+H310+H330 Lebensgefahr."]))['h_statements'] === 'H300+H310+H330 Lebensgefahr.', 'Combined code lost');
checkSds(!isset(values(SdsAnalysis::parse(["2 Mögliche Gefahren\n2.2 Kennzeichnung\nH314 " . str_repeat('x', 1600)]))['h_statements']), 'Oversized value truncated');
echo "15 SDB parser regression cases passed\n";
