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
    public function getProvider(): string {
        return $this->config->getUserValue($this->uid(), self::APP, 'provider', 'openai') === 'mistral' ? 'mistral' : 'openai';
    }
    public function setProvider(string $provider): void {
        if (!in_array($provider, ['openai', 'mistral'], true)) throw new \InvalidArgumentException();
        $this->config->setUserValue($this->uid(), self::APP, 'provider', $provider);
    }
    public function hasPersonalKey(string $provider = 'openai'): bool {
        return $this->config->getUserValue($this->uid(), self::APP, $provider . '_key', '') !== '';
    }
    public function save(string $key, string $provider = 'openai'): void {
        $this->config->setUserValue($this->uid(), self::APP, $provider . '_key', $this->crypto->encrypt($key));
    }
    public function delete(string $provider = 'openai'): void {
        $this->config->deleteUserValue($this->uid(), self::APP, $provider . '_key');
    }
    public function getKey(): string {
        $provider = $this->getProvider();
        $encrypted = $this->config->getUserValue($this->uid(), self::APP, $provider . '_key', '');
        if ($encrypted !== '') return $this->crypto->decrypt($encrypted);
        if ($provider !== 'openai') return '';
        $key = trim((string)(getenv('GSK_OPENAI_API_KEY') ?: ''));
        $file = '/etc/nextcloud/gefahrstoffkataster-openai.key';
        if ($key === '' && is_readable($file)) $key = trim((string)file_get_contents($file));
        return $key;
    }
}

