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
 *
 * Catalog-specific permissions allow for multi-tenant support.
 */
final class AccessPolicy {
    private const APP = 'gefahrstoffkataster';
    private const KEY = 'shares_v1';
    private const CATALOG_KEY = 'catalog_shares_v1';
    private const LEGACY_GROUP = 'freibad-gefahrstoffe';
    public const ROLES = ['reader', 'stock_editor', 'hazard_editor', 'manager'];
    public const CATALOG_ROLES = ['catalog_reader', 'catalog_stock_editor', 'catalog_hazard_editor', 'catalog_manager'];

    public function __construct(
        private IConfig $config,
        private IUserSession $session,
        private IGroupManager $groups,
        private IUserManager $users,
        private ?CatalogService $catalogService = null,
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

    // ==================== Catalog-specific permissions ====================

    /**
     * Get catalog-specific shares.
     *
     * @return array
     */
    private function catalogShares(): array {
        $data = json_decode($this->config->getAppValue(self::APP, self::CATALOG_KEY, '{"users":{},"groups":{},"catalogs":{}}'), true);
        if (!is_array($data)) return ['users' => [], 'groups' => [], 'catalogs' => []];
        $result = ['users' => [], 'groups' => [], 'catalogs' => []];
        foreach (['users', 'groups'] as $type) {
            foreach ((is_array($data[$type] ?? null) ? $data[$type] : []) as $id => $catalogRoles) {
                if (is_string($id) && is_array($catalogRoles)) {
                    $result[$type][$id] = [];
                    foreach ($catalogRoles as $catalogId => $role) {
                        if (is_string($role) && in_array($role, self::CATALOG_ROLES, true)) {
                            $result[$type][$id][(int)$catalogId] = $role;
                        }
                    }
                }
            }
        }
        foreach ((is_array($data['catalogs'] ?? null) ? $data['catalogs'] : []) as $catalogId => $catalogData) {
            if (is_int($catalogId) && is_array($catalogData)) {
                $result['catalogs'][(int)$catalogId] = $catalogData;
            }
        }
        return $result;
    }

    /**
     * Get the user's role for a specific catalog.
     *
     * @param int $catalogId
     * @return string|null
     */
    public function catalogRole(int $catalogId): ?string {
        $user = $this->session->getUser();
        if ($user === null) return null;
        $uid = $user->getUID();
        
        // Admins have full access to all catalogs
        if ($this->groups->isAdmin($uid)) return 'catalog_manager';
        
        $shares = $this->catalogShares();
        
        // Check explicit user grants for this catalog
        if (isset($shares['users'][$uid][$catalogId])) {
            return $shares['users'][$uid][$catalogId];
        }
        
        // Check group grants for this catalog
        $highest = null;
        foreach ($shares['groups'] as $gid => $groupCatalogs) {
            if (isset($groupCatalogs[$catalogId]) && $this->groups->isInGroup($uid, $gid)) {
                $role = $groupCatalogs[$catalogId];
                if (self::catalogRank($role) > self::catalogRank($highest)) {
                    $highest = $role;
                }
            }
        }
        
        // Fall back to legacy group (full access to all catalogs)
        if ($this->groups->isInGroup($uid, self::LEGACY_GROUP)) {
            return 'catalog_manager';
        }
        
        return $highest;
    }

    /**
     * Get the rank of a catalog role.
     *
     * @param string|null $role
     * @return int
     */
    private static function catalogRank(?string $role): int {
        $index = array_search($role, self::CATALOG_ROLES, true);
        return $index === false ? -1 : $index;
    }

    /**
     * Check if user can read a specific catalog.
     *
     * @param int $catalogId
     * @return bool
     */
    public function canReadCatalog(int $catalogId): bool {
        return $this->catalogRole($catalogId) !== null;
    }

    /**
     * Check if user can change stock in a specific catalog.
     *
     * @param int $catalogId
     * @return bool
     */
    public function canChangeStockInCatalog(int $catalogId): bool {
        return self::catalogRank($this->catalogRole($catalogId)) >= self::catalogRank('catalog_stock_editor');
    }

    /**
     * Check if user can edit basic data in a specific catalog.
     *
     * @param int $catalogId
     * @return bool
     */
    public function canEditBasicInCatalog(int $catalogId): bool {
        return self::catalogRank($this->catalogRole($catalogId)) >= self::catalogRank('catalog_stock_editor');
    }

    /**
     * Check if user can edit safety data in a specific catalog.
     *
     * @param int $catalogId
     * @return bool
     */
    public function canEditSafetyInCatalog(int $catalogId): bool {
        return self::catalogRank($this->catalogRole($catalogId)) >= self::catalogRank('catalog_hazard_editor');
    }

    /**
     * Check if user can manage a specific catalog.
     *
     * @param int $catalogId
     * @return bool
     */
    public function canManageCatalog(int $catalogId): bool {
        return $this->catalogRole($catalogId) === 'catalog_manager';
    }

    /**
     * Get catalog-specific capabilities.
     *
     * @param int $catalogId
     * @return array
     */
    public function catalogCapabilities(int $catalogId): array {
        $role = $this->catalogRole($catalogId);
        return [
            'catalog_id' => $catalogId,
            'role' => $role,
            'read' => $role !== null,
            'stock' => self::catalogRank($role) >= self::catalogRank('catalog_stock_editor'),
            'editBasic' => self::catalogRank($role) >= self::catalogRank('catalog_stock_editor'),
            'editSafety' => self::catalogRank($role) >= self::catalogRank('catalog_hazard_editor'),
            'manage' => $role === 'catalog_manager',
        ];
    }

    /** Revision uses the same empty-config default as changeCatalogAccess(). */
    public function catalogRevision(): string {
        return hash('sha256', $this->config->getAppValue(self::APP, self::CATALOG_KEY, '{"users":{},"groups":{},"catalogs":{}}'));
    }

    /**
     * Grant or revoke catalog-specific permissions.
     *
     * @param string $action
     * @param string $type
     * @param string $id
     * @param int $catalogId
     * @param string|null $role
     * @param string $revision
     * @return array
     */
    public function changeCatalogAccess(string $action, string $type, string $id, int $catalogId, ?string $role, string $revision): array {
        if (!$this->canManage()) throw new \DomainException('Nicht berechtigt.');
        if (!in_array($action, ['grant', 'revoke'], true)) throw new \InvalidArgumentException('Aktion unbekannt.');
        if (!in_array($type, ['user', 'group'], true)) throw new \InvalidArgumentException('Freigabetyp unbekannt.');
        $id = trim($id);
        if ($id === '' || mb_strlen($id) > 128) throw new \InvalidArgumentException('Ungültige Benutzer- oder Gruppen-ID.');
        
        if ($action === 'grant') {
            if (!in_array($role, self::CATALOG_ROLES, true)) throw new \InvalidArgumentException('Rolle unbekannt.');
            if ($type === 'user' && $this->users->get($id) === null) throw new \InvalidArgumentException('Nextcloud-Benutzer existiert nicht.');
            if ($type === 'group' && $this->groups->get($id) === null) throw new \InvalidArgumentException('Nextcloud-Gruppe existiert nicht.');
        }
        
        $old = $this->config->getAppValue(self::APP, self::CATALOG_KEY, '{"users":{},"groups":{},"catalogs":{}}');
        if (!preg_match('/^[a-f0-9]{64}$/D', $revision) || !hash_equals(hash('sha256', $old), $revision)) {
            throw new \RuntimeException('Die Katalog-Freigaben wurden inzwischen geändert. Bitte neu laden.');
        }
        
        $shares = $this->catalogShares();
        $key = $type === 'user' ? 'users' : 'groups';
        
        if (!isset($shares[$key][$id])) {
            $shares[$key][$id] = [];
        }
        
        if ($action === 'revoke') {
            unset($shares[$key][$id][$catalogId]);
            // Clean up empty entries
            if (empty($shares[$key][$id])) {
                unset($shares[$key][$id]);
            }
        } else {
            $shares[$key][$id][$catalogId] = $role;
        }
        
        if (count($shares['users']) + count($shares['groups']) > 200) throw new \InvalidArgumentException('Zu viele Freigaben.');
        
        $value = json_encode($shares, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->config->setAppValue(self::APP, self::CATALOG_KEY, $value);
        return ['shares' => $shares, 'revision' => hash('sha256', $value)];
    }

    /**
     * Get all catalogs the user has access to.
     *
     * @return array
     */
    public function getAccessibleCatalogs(): array {
        $user = $this->session->getUser();
        if ($user === null) return [];
        
        $uid = $user->getUID();
        $catalogs = [];
        
        // Admins can access all catalogs
        if ($this->groups->isAdmin($uid)) {
            // For now, return all catalogs (will be implemented with CatalogService)
            return ['all' => true];
        }
        
        $shares = $this->catalogShares();
        
        // Check user-specific catalog access
        if (isset($shares['users'][$uid])) {
            foreach ($shares['users'][$uid] as $catalogId => $role) {
                $catalogs[(int)$catalogId] = ['role' => $role, 'source' => 'user'];
            }
        }
        
        // Check group-specific catalog access
        foreach ($shares['groups'] as $gid => $groupCatalogs) {
            if ($this->groups->isInGroup($uid, $gid)) {
                foreach ($groupCatalogs as $catalogId => $role) {
                    $catalogId = (int)$catalogId;
                    if (!isset($catalogs[$catalogId]) || self::catalogRank($role) > self::catalogRank($catalogs[$catalogId]['role'])) {
                        $catalogs[$catalogId] = ['role' => $role, 'source' => 'group'];
                    }
                }
            }
        }
        
        // Legacy group has access to all catalogs
        if ($this->groups->isInGroup($uid, self::LEGACY_GROUP)) {
            return ['all' => true];
        }
        
        return $catalogs;
    }
}
