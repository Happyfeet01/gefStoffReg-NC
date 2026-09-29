<?php
declare(strict_types=1);
namespace OCA\Gefahrstoffkataster\Service;

use OCP\IConfig;
use OCP\IUserSession;
use OCP\Security\ICrypto;

class ApiKeyService {
    private const APP = 'gefahrstoffkataster';
    public function __construct(private IConfig $config, private IUserSession $session, private ICrypto $crypto) {}
    private function uid(): string {
        $user = $this->session->getUser();
        if ($user === null) throw new \RuntimeException('Anmeldung erforderlich.');
        return $user->getUID();
    }
    public function hasPersonalKey(): bool {
        return $this->config->getUserValue($this->uid(), self::APP, 'openai_key', '') !== '';
    }
    public function save(string $key): void {
        $this->config->setUserValue($this->uid(), self::APP, 'openai_key', $this->crypto->encrypt($key));
    }
    public function delete(): void {
        $this->config->deleteUserValue($this->uid(), self::APP, 'openai_key');
    }
    public function getKey(): string {
        $encrypted = $this->config->getUserValue($this->uid(), self::APP, 'openai_key', '');
        if ($encrypted !== '') return $this->crypto->decrypt($encrypted);
        $key = trim((string)(getenv('GSK_OPENAI_API_KEY') ?: ''));
        $file = '/etc/nextcloud/gefahrstoffkataster-openai.key';
        if ($key === '' && is_readable($file)) $key = trim((string)file_get_contents($file));
        return $key;
    }
}
