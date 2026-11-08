<?php
declare(strict_types=1);

namespace OCP {
    interface IConfig {
        public function getAppValue(string $app, string $key, string $default = ''): string;
        public function setAppValue(string $app, string $key, string $value): void;
    }
}
namespace {
    require __DIR__ . '/../lib/Service/ExternalShareService.php';

    class TestExternalConfig implements \OCP\IConfig {
        public array $storage = [];
        public function getAppValue(string $app, string $key, string $default = ''): string {
            return $this->storage[$key] ?? $default;
        }
        public function setAppValue(string $app, string $key, string $value): void {
            $this->storage[$key] = $value;
        }
    }
    function insist(bool $passed, string $message): void {
        if (!$passed) throw new \RuntimeException($message);
    }

    $config = new TestExternalConfig();
    $service = new \OCA\Gefahrstoffkataster\Service\ExternalShareService($config);
    $initial = $service->listing();
    insist($initial['links'] === [], 'New install must contain no public links');
    $generated = $service->create('Hygieneinstitut', 'kataster', 7, 'langes-Sicheres-Passwort-123', 'lars', $initial['revision']);
    insist(preg_match('/^[a-f0-9]{64}$/D', $generated['token']) === 1, 'Token must be 256-bit hexadecimal');
    insist($generated['id'] === hash('sha256', $generated['token']), 'Token hash must be storage key');
    $stored = $config->storage['external_shares_v1'] ?? '';
    insist(!str_contains($stored, $generated['token']), 'Plaintext token leaked into configuration');
    insist(!str_contains($stored, 'langes-Sicheres-Passwort-123'), 'Plaintext password leaked into configuration');
    insist($service->authenticate($generated['token'], 'langes-Sicheres-Passwort-123') !== null, 'Valid token+password rejected');
    insist($service->authenticate($generated['token'], 'wrong') === null, 'Invalid password accepted');
    insist($service->authenticate(str_repeat('a',64), 'langes-Sicheres-Passwort-123') === null, 'Unknown token accepted');
    $list = $service->listing();
    insist(count($list['links']) === 1 && $list['links'][0]['scope'] === 'kataster', 'Listing incorrect');
    insist(!array_key_exists('token', $list['links'][0]) && !array_key_exists('password_hash', $list['links'][0]), 'Secret exposed through listing');
    foreach (['wrong','',str_repeat('x',12)] as $badRevision) {
        try {
            $service->revoke($generated['id'], $badRevision);
            throw new \LogicException('Stale revision accepted');
        } catch (\RuntimeException $e) { /* expected */ }
    }
    foreach ([
        ['scope'=>'invalid','days'=>7,'password'=>''],
        ['scope'=>'kataster','days'=>365,'password'=>''],
        ['scope'=>'kataster','days'=>7,'password'=>'short'],
    ] as $bad) {
        try {
            $service->create('Falsche Freigabe', $bad['scope'], $bad['days'], $bad['password'], 'lars', $list['revision']);
            throw new \LogicException('Invalid share accepted');
        } catch (\InvalidArgumentException $e) { /* expected */ }
    }
    $noPassword = $service->create('Papierlager','lager',1,'','lars',$list['revision']);
    insist($service->authenticate($noPassword['token'], '') !== null, 'Valid passwordless token rejected');
    insist($service->authenticate($noPassword['token'], 'anything') !== null, 'Token-only link requires no password');
    $state = json_decode($config->storage['external_shares_v1'], true);
    $state['links'][$generated['id']]['expires_at'] = time()-1;
    $config->storage['external_shares_v1'] = json_encode($state);
    insist($service->active($generated['token']) === null, 'Expired link remained usable');
    insist($service->authenticate($generated['token'], 'langes-Sicheres-Passwort-123') === null, 'Expired password login accepted');
    $newList = $service->listing();
    $service->revoke($noPassword['id'], $newList['revision']);
    insist($service->active($noPassword['token']) === null, 'Revoked token remained active');
    echo "External token hashing, password validation, expiry, revision and revocation passed\n";
}
