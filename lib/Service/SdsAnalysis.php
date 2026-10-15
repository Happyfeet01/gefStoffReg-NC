<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Service;

/** Conservative extraction: only explicit text in section 2, never inferred pictograms. */
class SdsAnalysis {
    public static function parse(array $pages): array {
        $evidence = [];
        $active = false;
        $subsection = '';
        foreach ($pages as $page => $text) {
            foreach (preg_split('/\R/u', $text) ?: [] as $line) {
                $line = trim(preg_replace('/\s+/u', ' ', $line));
                if (preg_match('/^(?:(?:ABSCHNITT|SECTION)\s+)?2[.:]?\s+(?:Mögliche Gefahren|Gefahrenidentifikation|Hazards identification)/iu', $line)) { $active = true; $subsection = ''; }
                if ($active && preg_match('/^(?:(?:ABSCHNITT|SECTION)\s+)?3[.:]?\s+(?:Zusammensetzung|Composition)/iu', $line)) $active = false;
                if ($active && preg_match('/^2[.,]([123])\b/u', $line, $m)) $subsection = $m[1];
                if ($active && $line !== '') $evidence[] = ['page' => $page + 1, 'text' => $line, 'subsection' => $subsection];
            }
        }
        $fields = [];
        $add = static function (string $field, string $value, array $source) use (&$fields): void {
            $fields[$field][$value] = $source;
        };
        $notClassified = false;
        foreach ($evidence as $source) {
            if ($source['subsection'] === '3') continue;
            $line = $source['text'];
            if ($source['subsection'] === '1' && preg_match('/(?:nicht als gefährlich eingestuft|(?:Gemisch|Produkt|Stoff)\s+ist\s+nicht\s+(?:als\s+gefährlich\s+)?eingestuft|not classified as hazardous)/iu', $line)
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
        return ['status' => $status, 'suggestions' => $suggestions, 'evidence' => $evidence,
            'notice' => 'Produkt, Hersteller und SDB-Version im Original prüfen. Fehlende Angaben und nicht als gefährlich eingestuft bedeuten nicht automatisch kein Gefahrstoff. GHS-Symbole als Bild werden nicht erkannt.'];
    }
}
