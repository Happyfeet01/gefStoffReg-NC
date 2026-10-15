<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Service;

/** Conservative extraction: only explicit text in section 2, never inferred pictograms. */
class SdsAnalysis {
    private static function normalized(string $line): string {
        $line = trim(preg_replace('/\s+/u', ' ', $line));
        return preg_replace('/^(?:[·•*▪●]\s*)+/u', '', $line);
    }

    public static function parse(array $pages): array {
        $evidence = [];
        $active = false;
        $subsection = '';
        foreach ($pages as $page => $text) {
            foreach (preg_split('/\R/u', $text) ?: [] as $line) {
                $source = trim(preg_replace('/\s+/u', ' ', $line));
                $line = self::normalized($line);
                if (preg_match('/^(?:ABSCHNITT|SECTION)\s+(\d{1,2})[.:]?\s/iu', $line, $section) && (int)$section[1] !== 2) $active = false;
                if (preg_match('/^(?:(?:ABSCHNITT|SECTION)\s+)?2[.:]?\s+(?:Mögliche Gefahren|Gefahrenidentifikation|Hazards identification)/iu', $line)) {
                    if (!$active) $subsection = '';
                    $active = true;
                }
                if ($active && preg_match('/^3[.:]?\s+(?:Zusammensetzung|Composition)/iu', $line)) $active = false;
                if ($active && preg_match('/^2[.,]([123])\b/u', $line, $m)) $subsection = $m[1];
                if ($active && $line !== '') $evidence[] = ['page' => $page + 1, 'text' => $source, 'subsection' => $subsection];
            }
        }
        $fields = [];
        $add = static function (string $field, string $value, array $source) use (&$fields): void {
            $fields[$field][$value] = $source;
        };
        $notClassified = false;
        foreach ($evidence as $source) {
            if ($source['subsection'] === '3') continue;
            $line = self::normalized($source['text']);
            if ($source['subsection'] === '1' && preg_match('/(?:nicht als gefährlich eingestuft|(?:Gemisch|Produkt|Stoff)\s+ist\s+(?:gemäß\s+(?:der\s+)?CLP-Verordnung\s+)?nicht\s+(?:als\s+gefährlich\s+)?eingestuft|not classified as hazardous)/iu', $line)
                && !preg_match('/(?:nicht als gefährlich eingestuft werden|not classified as hazardous if)/iu', $line)) $notClassified = true;
            if (preg_match('/^(?:Signalwort(?:er)?\s*[:.]?\s*)?(Gefahr|Achtung|Danger|Warning)$/iu', $line, $m)) {
                $add('signal', in_array(strtolower($m[1]), ['gefahr','danger'], true) ? 'Gefahr' : 'Achtung', $source);
            }
            preg_match_all('/\bGHS\s*0[1-9]\b/i', $line, $symbols);
            foreach ($symbols[0] as $symbol) $add('ghs', preg_replace('/\s/', '', strtoupper($symbol)), $source);
            // Keep combined codes and their literal wording; do not invent text from code tables.
            if ($source['subsection'] !== '3' && preg_match('/\b((?:EU)?H\d{3}[a-zA-Z]{0,2}(?:\s*\+\s*H?\d{3}[a-zA-Z]{0,2})*)\b\s*[:\-]?\s*(.*)/u', $line, $m)) {
                $add('h_statements', trim($m[1] . ' ' . $m[2]), $source);
            }
            if (preg_match('/\b(?:Flam\.?\s*(?:Liq|Sol|Gas)|Ox\.?\s*(?:Liq|Sol|Gas)|Acute\s*Tox|Skin\s*(?:Corr|Irrit|Sens)|Eye\s*(?:Dam|Irrit)|Resp\.?\s*Sens|STOT\s*(?:SE|RE)|Asp\.?\s*Tox|Carc|Muta|Repr|Aquatic\s*(?:Acute|Chronic)|Met\.?\s*Corr)\.?\s*[1234][ABC]?\b/iu', $line)) {
                $add('classification', $line, $source);
            }
        }
        $positive = isset($fields['classification']) || isset($fields['signal']) || isset($fields['ghs']);
        // H codes alone and contradictory statements require manual assessment.
        $status = $notClassified && ($positive || isset($fields['h_statements'])) ? 'unclear' : ($positive ? 'classified' : ($notClassified ? 'not_classified' : 'unclear'));
        $limits = ['classification' => 600, 'signal' => 40, 'ghs' => 120, 'h_statements' => 1500];
        $suggestions = [];
        foreach ($fields as $field => $values) {
            $value = implode($field === 'ghs' ? ', ' : "\n", array_keys($values));
            // Oversized and conflicting values stay visible as evidence, never truncated into a proposal.
            if (mb_strlen($value) > $limits[$field] || ($field === 'signal' && count($values) !== 1)) continue;
            $suggestions[] = ['field' => $field, 'value' => $value, 'evidence' => array_values($values)];
        }
        $notice = 'Produkt, Hersteller und SDB-Version im Original prüfen. Fehlende Angaben und nicht als gefährlich eingestuft bedeuten nicht automatisch kein Gefahrstoff. GHS-Symbole als Bild werden nicht erkannt.';
        if (!$evidence) {
            $operatingInstruction = preg_match('/Betriebsanweisung\s+gem/iu', implode("\n", $pages));
            $notice = ($operatingInstruction ? 'Betriebsanweisung erkannt. Für diese Funktion bitte das zugehörige Sicherheitsdatenblatt verwenden. ' : 'In den gelesenen Seiten wurde kein SDB-Abschnitt 2 erkannt. Bitte prüfen, ob ein Produktmerkblatt statt eines Sicherheitsdatenblatts ausgewählt wurde. ') . $notice;
        }
        return ['status' => $status, 'suggestions' => $suggestions, 'evidence' => $evidence, 'notice' => $notice];
    }
}
