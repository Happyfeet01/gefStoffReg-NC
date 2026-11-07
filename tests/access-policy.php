<?php
declare(strict_types=1);
namespace OCP {
    interface IConfig {
        public function getAppValue(string $app, string $key, string $default = ''): string;
        public function setAppValue(string $app, string $key, string $value): void;
    }
    interface IGroupManager {
        public function isAdmin(string $uid): bool;
        public function isInGroup(string $uid, string $gid): bool;
        public function get(string $gid): ?object;
    }
    interface IUserManager { public function get(string $uid): ?object; }
    interface IUserSession { public function getUser(): ?object; }
}
namespace {
    require __DIR__ . '/../lib/Service/AccessPolicy.php';
    class Config implements \OCP\IConfig {
        public array $store = [];
        public function getAppValue(string $app, string $key, string $default = ''): string {return $this->store[$key] ?? $default;}
        public function setAppValue(string $app, string $key, string $value): void {$this->store[$key] = $value;}
    }
    class GroupManager implements \OCP\IGroupManager {
        public array $membership = [];
        public function isAdmin(string $uid): bool {return $uid === 'admin';}
        public function isInGroup(string $uid, string $gid): bool {return in_array($gid, $this->membership[$uid] ?? [], true);}
        public function get(string $gid): ?object {return in_array($gid, ['team', 'freibad-gefahrstoffe'], true) ? (object)['id'=>$gid] : null;}
    }
    class Users implements \OCP\IUserManager {
        public function get(string $uid): ?object {return in_array($uid, ['admin','lars','kollege'], true) ? (object)['uid'=>$uid] : null;}
    }
    class Session implements \OCP\IUserSession {
        public ?string $uid = 'admin';
        public function getUser(): ?object {return $this->uid === null ? null : new class($this->uid) {
            public function __construct(private string $uid) {}
            public function getUID(): string {return $this->uid;}
        };}
    }
    function check(bool $ok, string $message): void {if (!$ok) throw new \RuntimeException($message);}
    $config = new Config();$groups = new GroupManager();$users = new Users();$session = new Session();
    $policy = new \OCA\Gefahrstoffkataster\Service\AccessPolicy($config, $session, $groups, $users);
    check($policy->canManage(), 'Admins should manage');
    $session->uid = 'kollege';
    check(!$policy->canRead() && !$policy->canChangeStock(), 'Default deny failed');
    $session->uid = 'admin';$rev = $policy->listing()['revision'];
    $policy->change('grant','user','kollege','reader',$rev);
    $session->uid = 'kollege';
    check($policy->canRead() && !$policy->canChangeStock() && !$policy->canEditSafety(), 'Read-only role leaked');
    $groups->membership['kollege'] = ['freibad-gefahrstoffe'];
    check(!$policy->canChangeStock(), 'Explicit user reader must override legacy group');
    $session->uid = 'admin';$rev = $policy->listing()['revision'];
    try {$policy->change('grant','user','kollege','manager','000');throw new \RuntimeException('Accepted stale revision');}
    catch (\RuntimeException $e) {if ($e->getMessage() === 'Accepted stale revision') throw $e;}
    $policy->change('revoke','user','kollege',null,$rev);
    $session->uid='kollege';
    check($policy->canManage(), 'Legacy group access not preserved');
    $groups->membership['kollege'] = ['team'];
    $session->uid='admin';$rev=$policy->listing()['revision'];
    $policy->change('grant','group','team','stock_editor',$rev);
    $session->uid='kollege';
    check($policy->canChangeStock() && !$policy->canEditSafety() && !$policy->canManage(), 'Stock role scope invalid');
    $session->uid='admin';$rev=$policy->listing()['revision'];
    $policy->change('grant','user','kollege','hazard_editor',$rev);
    $session->uid='kollege';
    check($policy->canEditSafety() && !$policy->canManage(), 'Hazard editor role invalid');
    $session->uid=null;
    check(!$policy->canRead(), 'Anonymous access granted');
    echo "Share authorization, role isolation and revision checks passed\n";
}
