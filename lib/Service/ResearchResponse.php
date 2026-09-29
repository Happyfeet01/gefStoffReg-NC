<?php
declare(strict_types=1);
namespace OCA\Gefahrstoffkataster\Service;

/** Provider response parsing without credentials or transport dependencies. */
final class ResearchResponse {
    public static function candidates(array $result, string $provider): array {
        $output = '';
        if ($provider === 'mistral') {
            $choice = $result['choices'][0] ?? [];
            if (($choice['finish_reason'] ?? '') === 'length') throw new \UnexpectedValueException('Mistral-Antwort wegen Tokenlimit abgeschnitten. Bitte Suche präzisieren.');
            if (($choice['finish_reason'] ?? '') !== 'stop') throw new \UnexpectedValueException('Mistral hat keine abgeschlossene Textantwort geliefert.');
            $content = $choice['message']['content'] ?? '';
            if (is_string($content)) $output = $content;
            elseif (is_array($content)) foreach ($content as $part) {
                if (($part['type'] ?? '') === 'text' && is_string($part['text'] ?? null)) $output .= $part['text'];
            }
        } else {
            if (($result['status'] ?? '') === 'incomplete') throw new \UnexpectedValueException('OpenAI-Antwort unvollständig (Tokenlimit oder Inhaltsfilter). Bitte Suche präzisieren.');
            if (($result['status'] ?? '') !== 'completed') throw new \UnexpectedValueException('OpenAI hat keine abgeschlossene Antwort geliefert.');
            foreach (($result['output'] ?? []) as $item) {
                if (($item['type'] ?? '') !== 'message') continue;
                foreach (($item['content'] ?? []) as $part) {
                    if (($part['type'] ?? '') === 'refusal') throw new \UnexpectedValueException('OpenAI hat diese Anfrage abgelehnt. Bitte Originalquelle manuell prüfen.');
                    if (($part['type'] ?? '') === 'output_text' && is_string($part['text'] ?? null)) $output .= $part['text'];
                }
            }
        }
        if (trim($output) === '') throw new \UnexpectedValueException('Der KI-Anbieter hat eine leere Antwort geliefert.');
        try { $parsed = json_decode($output, true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new \UnexpectedValueException('Die KI-Antwort enthält kein gültiges JSON.'); }
        if (!is_array($parsed) || !is_array($parsed['candidates'] ?? null) || !array_is_list($parsed['candidates'])) {
            throw new \UnexpectedValueException('Die KI-Antwort enthält keine gültige Variantenliste.');
        }
        foreach ($parsed['candidates'] as $entry) {
            if (!is_array($entry)) throw new \UnexpectedValueException('Ungültige Produktdaten in der KI-Antwort.');
            foreach (['name','manufacturer','article','unit','source_url','sds_url','match_note'] as $field) {
                if (isset($entry[$field]) && !is_string($entry[$field])) throw new \UnexpectedValueException('Ungültige Feldtypen in der KI-Antwort.');
            }
        }
        return $parsed['candidates'];
    }
}
