<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Service;

use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;

/**
 * Server-side app grants. Never authorize writes from browser-provided roles.
 *
 * The old freibad-gefahrstoffe group keeps its existing full access until the
 * installation explicitly migrates to shares. A direct user grant overrides
 * this legacy membership (e.g. to give one colleague read-only access).
 */
final class AccessPolicy {
    private const APP = 'gefahrstoffkataster';
    private const KEY = 'shares_v1';
    private const LEGACY_GROUP = 'freibad-gefahrstoffe';
    public const ROLES = ['reader', 'stock_editor', 'hazard_editor', 'manager'];

    public function __construct(
        private IConfig $config,
        private IUserSession $session,
        private IGroupManager $groups,
        private IUserManager $users,
    ) {}

    private function raw(): string {
        return $this->config->getAppValue(self::APP, self::KEY, '{"users":{},"groups":{}}');
    }

    private function shares(): array {
        $data = json_decode($this->raw(), true);
        if (!is_array($data)) return ['users' => [], 'groups' => []];
        $result = ['users' => [], 'groups' => []];
        foreach (['users', 'groups'] as $type) {
            foreach ((is_array($data[$type] ?? null) ? $data[$type] : []) as $id => $role) {
                if (is_string($id) && is_string($role) && in_array($role, self::ROLES, true)) {
                    $result[$type][$id] = $role;
                }
            }
        }
        return $result;
    }

    private static function rank(?string $role): int {
        $index = array_search($role, self::ROLES, true);
        return $index === false ? -1 : $index;
    }

    public function role(): ?string {
        $user = $this->session->getUser();
        if ($user === null) return null;
        $uid = $user->getUID();
        if ($this->groups->isAdmin($uid)) return 'manager';
        $shares = $this->shares();
        // Explicit user grants beat group membership, including the legacy group.
        if (isset($shares['users'][$uid])) return $shares['users'][$uid];
        $highest = $this->groups->isInGroup($uid, self::LEGACY_GROUP) ? 'manager' : null;
        foreach ($shares['groups'] as $gid => $role) {
            if ($this->groups->isInGroup($uid, $gid) && self::rank($role) > self::rank($highest)) {
                $highest = $role;
            }
        }
        return $highest;
    }

    public function canRead(): bool { return $this->role() !== null; }
    public function canChangeStock(): bool { return self::rank($this->role()) >= self::rank('stock_editor'); }
    public function canEditBasic(): bool { return self::rank($this->role()) >= self::rank('stock_editor'); }
    public function canEditSafety(): bool { return self::rank($this->role()) >= self::rank('hazard_editor'); }
    public function canManage(): bool { return $this->role() === 'manager'; }

    public function capabilities(): array {
        $role = $this->role();
        return [
            'role' => $role,
            'read' => $role !== null,
            'stock' => self::rank($role) >= self::rank('stock_editor'),
            'editBasic' => self::rank($role) >= self::rank('stock_editor'),
            'editSafety' => self::rank($role) >= self::rank('hazard_editor'),
            'manage' => $role === 'manager',
        ];
    }

    public function listing(): array {
        if (!$this->canManage()) throw new \DomainException('Nicht berechtigt.');
        return ['shares' => $this->shares(), 'revision' => hash('sha256', $this->raw())];
    }

    public function change(string $action, string $type, string $id, ?string $role, string $revision): array {
        if (!$this->canManage()) throw new \DomainException('Nicht berechtigt.');
        if (!in_array($action, ['grant', 'revoke'], true)) throw new \InvalidArgumentException('Aktion unbekannt.');
        if (!in_array($type, ['user', 'group'], true)) throw new \InvalidArgumentException('Freigabetyp unbekannt.');
        $id = trim($id);
        if ($id === '' || mb_strlen($id) > 128) throw new \InvalidArgumentException('Ungültige Benutzer- oder Gruppen-ID.');
        if ($type === 'group' && $id === self::LEGACY_GROUP) {
            throw new \InvalidArgumentException('Die bisherige Standardgruppe wird noch über die bestehende Nextcloud-Gruppenzugehörigkeit verwaltet.');
        }
        if ($action === 'grant') {
            if (!in_array($role, self::ROLES, true)) throw new \InvalidArgumentException('Rolle unbekannt.');
            if ($type === 'user' && $this->users->get($id) === null) throw new \InvalidArgumentException('Nextcloud-Benutzer existiert nicht.');
            if ($type === 'group' && $this->groups->get($id) === null) throw new \InvalidArgumentException('Nextcloud-Gruppe existiert nicht.');
        }
        $old = $this->raw();
        if (!preg_match('/^[a-f0-9]{64}$/D', $revision) || !hash_equals(hash('sha256', $old), $revision)) {
            throw new \RuntimeException('Die Freigaben wurden inzwischen geändert. Bitte neu laden.');
        }
        $shares = $this->shares();
        $key = $type === 'user' ? 'users' : 'groups';
        if ($action === 'revoke') unset($shares[$key][$id]);
        else $shares[$key][$id] = $role;
        if (count($shares['users']) + count($shares['groups']) > 200) throw new \InvalidArgumentException('Zu viele Freigaben.');
        $value = json_encode($shares, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->config->setAppValue(self::APP, self::KEY, $value);
        return ['shares' => $shares, 'revision' => hash('sha256', $value)];
    }
}
