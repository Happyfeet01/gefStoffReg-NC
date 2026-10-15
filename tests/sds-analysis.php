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
$decorated = SdsAnalysis::parse(["* ABSCHNITT 2: Mögliche Gefahren\n· 2.1 Einstufung des Stoffs oder Gemischs\nMet. Corr.1 H290\nSkin Corr. 1B H314\n· 2.2 Kennzeichnungselemente\n· Signalwort Gefahr\nGHS05\n· Gefahrenhinweise H290 Kann gegenüber Metallen korrosiv sein.\nH314 Verursacht schwere Verätzungen.\n* ABSCHNITT 3: Zusammensetzung\nAcute Tox. 4 H302\nH302 Nur Bestandteil"]);
checkSds($decorated['status'] === 'classified', 'Decorated heading missed');
checkSds(values($decorated)['signal'] === 'Gefahr', 'Bulleted signal word missed');
checkSds(!str_contains(values($decorated)['h_statements'], 'H302'), 'Decorated section 3 leaked');
checkSds(!str_contains(values($decorated)['classification'], 'Acute Tox'), 'Ingredient class leaked');
$negativeClp = SdsAnalysis::parse(["ABSCHNITT 2: Mögliche Gefahren\n· 2.1 Einstufung\n1272/2008 Der Stoff ist gemäß CLP-Verordnung nicht eingestuft.\n· 2.2 Kennzeichnung\n· Signalwort entfällt\nABSCHNITT 3: Zusammensetzung"]);
checkSds($negativeClp['status'] === 'not_classified', 'CLP negative with bullets missed');
checkSds(values($negativeClp) === [], 'Negative statement created hazard fields');
checkSds(str_contains(SdsAnalysis::parse(['Betriebsanweisung gem. §14 GefStoffV'])['notice'], 'Betriebsanweisung erkannt'), 'Document type notice missing');
checkSds(str_contains(SdsAnalysis::parse(['Produktinformation ohne SDB-Abschnitt'])['notice'], 'Produktmerkblatt'), 'Non-SDS notice missing');
checkSds(!str_contains(values(SdsAnalysis::parse(["* ABSCHNITT 2: Mögliche Gefahren\n· 2.2 Kennzeichnung\nH314 Produkt\n* ABSCHNITT 4: Erste Hilfe\nH302 Nicht übernehmen"]))['h_statements'], 'H302'), 'Unexpected section end ignored');
echo "24 SDB parser regression cases passed\n";
