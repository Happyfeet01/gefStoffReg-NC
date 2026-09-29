<?php
declare(strict_types=1);
namespace OCA\Gefahrstoffkataster\Settings;

use OCA\Gefahrstoffkataster\Service\ApiKeyService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\Util;

class Personal implements ISettings {
    public function __construct(private ApiKeyService $keys) {}
    public function getForm(): TemplateResponse {
        Util::addScript('gefahrstoffkataster', 'settings');
        return new TemplateResponse('gefahrstoffkataster', 'settings', ['provider' => $this->keys->getProvider(), 'openai' => $this->keys->hasPersonalKey('openai'), 'mistral' => $this->keys->hasPersonalKey('mistral')]);
    }
    public function getSection(): string { return 'additional'; }
    public function getPriority(): int { return 50; }
}

