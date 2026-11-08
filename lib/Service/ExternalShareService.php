<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Service;

use OCP\IConfig;

/**
 * Read-only, expiring capability links. Only SHA-256 hashes of the random
 * 256-bit URL token are stored. Password hashes use PHP's password_hash().
 * Access is checked again for every read and file download.
 */
final class ExternalShareService {
    private const APP = 'gefahrstoffkataster';
    private const KEY = 'external_shares_v1';
    private const SCOPES = ['kataster', 'lager', 'both'];
    private const DAYS = [1, 7, 30];
    private const MAX_LINKS = 100;

    public function __construct(private IConfig $config) {}

    private function raw(): string {
        return $this->config->getAppValue(self::APP, self::KEY, '{"links":{}}');
    }

    private function entries(): array {
        $data = json_decode($this->raw(), true);
        return is_array($data) && is_array($data['links'] ?? null) ? $data['links'] : [];
    }

    public function listing(): array {
        $items = [];
        foreach ($this->entries() as $hash => $entry) {
            if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash) || !is_array($entry)) continue;
            $items[] = [
                'id' => $hash,
                'label' => $entry['label'] ?? '',
                'scope' => $entry['scope'] ?? 'kataster',
                'expires_at' => (int)($entry['expires_at'] ?? 0),
                'created_at' => (int)($entry['created_at'] ?? 0),
                'password_protected' => !empty($entry['password_hash']),
                'full_details' => !empty($entry['full_details']),
                'expired' => (int)($entry['expires_at'] ?? 0) <= time(),
            ];
        }
        usort($items, static fn(array $a, array $b): int => $b['created_at'] <=> $a['created_at']);
        return ['links' => $items, 'revision' => hash('sha256', $this->raw())];
    }

    public function create(string $label, string $scope, int $days, string $password, string $actor, string $revision): array {
        $label = trim($label);
        if ($label === '' || mb_strlen($label) > 100) throw new \InvalidArgumentException('Bezeichnung muss 1 bis 100 Zeichen enthalten.');
        if (!in_array($scope, self::SCOPES, true)) throw new \InvalidArgumentException('Freigabebereich ungültig.');
        if (!in_array($days, self::DAYS, true)) throw new \InvalidArgumentException('Laufzeit ungültig.');
        if ($password !== '' && (mb_strlen($password) < 12 || mb_strlen($password) > 256)) {
            throw new \InvalidArgumentException('Passwort muss mindestens 12 Zeichen haben.');
        }
        $current = $this->raw();
        $this->assertRevision($revision, $current);
        $entries = $this->entries();
        // Expired records are no longer usable, and may be pruned when managing links.
        $entries = array_filter($entries, static fn($item): bool => is_array($item) && (int)($item['expires_at'] ?? 0) > time());
        if (count($entries) >= self::MAX_LINKS) throw new \InvalidArgumentException('Maximal 100 aktive Freigaben.');
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $now = time();
        $entries[$hash] = [
            'label' => $label,
            'scope' => $scope,
            'created_at' => $now,
            'expires_at' => $now + $days * 86400,
            'password_hash' => $password === '' ? '' : password_hash($password, PASSWORD_DEFAULT),
            'created_by' => $actor,
            'full_details' => true,
        ];
        $this->save($entries);
        return ['token' => $token, 'id' => $hash, 'expires_at' => $entries[$hash]['expires_at']];
    }

    public function revoke(string $id, string $revision): void {
        if (!preg_match('/^[a-f0-9]{64}$/D', $id)) throw new \InvalidArgumentException('Freigabe-ID ungültig.');
        $current = $this->raw();
        $this->assertRevision($revision, $current);
        $entries = $this->entries();
        if (!isset($entries[$id])) throw new \InvalidArgumentException('Freigabe nicht gefunden.');
        unset($entries[$id]);
        $this->save($entries);
    }

    /** Returns only a currently active link. No token or password data is exposed. */
    public function active(string $token): ?array {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return null;
        $entry = $this->entries()[hash('sha256', $token)] ?? null;
        if (!is_array($entry) || !in_array($entry['scope'] ?? null, self::SCOPES, true)
            || (int)($entry['expires_at'] ?? 0) <= time()) return null;
        return $entry;
    }

    public function authenticate(string $token, string $password): ?array {
        $entry = $this->active($token);
        if ($entry === null) return null;
        $hash = $entry['password_hash'] ?? '';
        if (!is_string($hash) || ($hash !== '' && !password_verify($password, $hash))) return null;
        return $entry;
    }

    private function assertRevision(string $revision, string $raw): void {
        if (!preg_match('/^[a-f0-9]{64}$/D', $revision) || !hash_equals(hash('sha256', $raw), $revision)) {
            throw new \RuntimeException('Freigaben wurden geändert. Bitte neu laden.');
        }
    }

    private function save(array $entries): void {
        $this->config->setAppValue(self::APP, self::KEY,
            json_encode(['links' => $entries], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
