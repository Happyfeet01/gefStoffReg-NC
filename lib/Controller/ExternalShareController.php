<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Controller;

use OCA\Gefahrstoffkataster\Service\AccessPolicy;
use OCA\Gefahrstoffkataster\Service\ExternalShareService;
use OCA\Gefahrstoffkataster\Service\ExternalShareProjection;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\Files\IAppData;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;
use Psr\Log\LoggerInterface;

final class ExternalShareController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private ExternalShareService $shares,
        private AccessPolicy $access,
        private IDBConnection $db,
        private IAppData $appData,
        private IUserSession $session,
        private IURLGenerator $urls,
        private LoggerInterface $logger,
    ) {
        parent::__construct($appName, $request);
    }

    private function fail(string $message, int $status = Http::STATUS_BAD_REQUEST): DataResponse {
        return new DataResponse(['error' => $message], $status);
    }

    private function payload(): array {
        $raw = $this->request->getParam('payload');
        if (!is_string($raw) || strlen($raw) > 4096) throw new \InvalidArgumentException('Eingabe ungültig.');
        $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_is_list($data)) throw new \InvalidArgumentException('JSON-Objekt erwartet.');
        return $data;
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function listLinks(): DataResponse {
        if (!$this->access->canManage()) return $this->fail('Zugriff verweigert.', Http::STATUS_FORBIDDEN);
        return new DataResponse($this->shares->listing());
    }

    #[NoAdminRequired]
    public function createLink(): DataResponse {
        if (!$this->access->canManage()) return $this->fail('Zugriff verweigert.', Http::STATUS_FORBIDDEN);
        try {
            $input = $this->payload();
            $label = $input['label'] ?? null;
            $scope = $input['scope'] ?? null;
            $days = $input['days'] ?? null;
            $password = $input['password'] ?? null;
            $revision = $input['revision'] ?? null;
            if (!is_string($label) || !is_string($scope) || !is_int($days) || !is_string($password) || !is_string($revision)) {
                throw new \InvalidArgumentException('Eingabe ungültig.');
            }
            $actor = $this->session->getUser()->getUID();
            $link = $this->shares->create($label, $scope, $days, $password, $actor, $revision);
            $this->logger->info('Gefahrstoffkataster: Externer Leselink erstellt.',
                ['actor' => $actor, 'link_id' => substr($link['id'], 0, 12), 'scope' => $scope]);
            return new DataResponse([
                'url' => $this->urls->linkToRouteAbsolute('gefahrstoffkataster.external_share.view', ['token' => $link['token']]),
                'expires_at' => $link['expires_at'], 'links' => $this->shares->listing(),
            ], Http::STATUS_CREATED);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), Http::STATUS_CONFLICT);
        } catch (\InvalidArgumentException|\JsonException|\TypeError $e) {
            return $this->fail($e->getMessage());
        }
    }

    #[NoAdminRequired]
    public function revokeLink(): DataResponse {
        if (!$this->access->canManage()) return $this->fail('Zugriff verweigert.', Http::STATUS_FORBIDDEN);
        try {
            $input = $this->payload();
            if (!is_string($input['id'] ?? null) || !is_string($input['revision'] ?? null)) {
                throw new \InvalidArgumentException('Eingabe ungültig.');
            }
            $this->shares->revoke($input['id'], $input['revision']);
            $this->logger->info('Gefahrstoffkataster: Externer Leselink widerrufen.',
                ['actor' => $this->session->getUser()->getUID(), 'link_id' => substr($input['id'], 0, 12)]);
            return new DataResponse($this->shares->listing());
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), Http::STATUS_CONFLICT);
        } catch (\InvalidArgumentException|\JsonException|\TypeError $e) {
            return $this->fail($e->getMessage());
        }
    }

    #[PublicPage]
    #[NoCSRFRequired]
    public function view(string $token): Response {
        if ($this->shares->active($token) === null) return $this->fail('Freigabe nicht verfügbar.', Http::STATUS_NOT_FOUND);
        Util::addStyle('gefahrstoffkataster', 'external-share');
        Util::addScript('gefahrstoffkataster', 'external-share');
        $response = new PublicTemplateResponse('gefahrstoffkataster', 'external-share', [
            'apiUrl' => $this->urls->linkToRoute('gefahrstoffkataster.external_share.read', ['token' => $token]),
            'filePattern' => $this->urls->linkToRoute('gefahrstoffkataster.external_share.file', ['token' => $token, 'id' => '__FILE__']),
        ]);
        // Nextcloud's public template otherwise adds a sign-up marketing footer.
        // Our share page supplies its own compact header and footer.
        $response->setHeaderTitle('Lager & Gefahrstoffe');
        $response->setFooterVisible(false);
        return $this->noStore($response);
    }

    #[PublicPage]
    #[NoCSRFRequired]
    #[BruteForceProtection(action: 'gsk-external-password')]
    public function read(string $token): DataResponse {
        $entry = $this->shares->authenticate($token, $this->password());
        if ($entry === null) {
            $this->logDeniedShare($token, 'data');
            return $this->deniedPublic();
        }
        try {
            $projection = ExternalShareProjection::build(
                $this->rows('gsk_product'),
                $this->rows('gsk_location'),
                $this->rows('gsk_stock'),
                $this->rows('gsk_file'),
                $entry['scope']
            );
            $products = $projection['products'];
            $locations = $projection['locations'];
            $this->logger->info('Gefahrstoffkataster: Externer Leselink verwendet.',
                ['link_id' => substr(hash('sha256', $token), 0, 12), 'scope' => $entry['scope']]);
            return $this->noStore(new DataResponse([
                'scope' => $entry['scope'], 'label' => $entry['label'], 'expires_at' => $entry['expires_at'],
                'products' => $products, 'locations' => $locations,
            ]));
        } catch (\Throwable $e) {
            $this->logger->error('Gefahrstoffkataster: Externe Leseansicht fehlgeschlagen.', ['type' => get_class($e)]);
            return $this->fail('Freigabe kann derzeit nicht geladen werden.', Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    #[PublicPage]
    #[NoCSRFRequired]
    #[BruteForceProtection(action: 'gsk-external-password')]
    public function file(string $token, int $id): Response {
        $entry = $this->shares->authenticate($token, $this->password());
        if ($entry === null) {
            $this->logDeniedShare($token, 'data');
            return $this->deniedPublic();
        }
        $file = $this->one('gsk_file', $id);
        if (!$file || !in_array($file['kind'], ['photo','sds'], true)) {
            return $this->fail('Datei nicht verfügbar.', Http::STATUS_NOT_FOUND);
        }
        $product = $this->one('gsk_product', (int)$file['product_id']);
        if (!$product) return $this->fail('Datei nicht verfügbar.', Http::STATUS_NOT_FOUND);
        $p = json_decode($product['details'], true);
        if (!is_array($p) || !ExternalShareProjection::allowsFile(
            $file, $p, $this->rows('gsk_file'), $entry['scope']
        )) {
            return $this->fail('Datei nicht verfügbar.', Http::STATUS_NOT_FOUND);
        }
        // Allow only image formats already accepted by the internal upload API.
        $photoMimes = ['image/jpeg','image/png','image/webp','image/heic','image/heif'];
        $mime = $file['kind'] === 'sds' ? 'application/pdf' : (string)$file['mime'];
        if ($file['kind'] === 'photo' && !in_array($mime, $photoMimes, true)) {
            return $this->fail('Datei nicht verfügbar.', Http::STATUS_NOT_FOUND);
        }
        try {
            $folder = $this->appData->getFolder('documents');
            $content = $folder->getFile($file['storage_name'])->getContent();
            if (!is_string($content)) throw new \RuntimeException('Missing document');
            $response = new DataDownloadResponse($content, (string)$file['filename'], $mime);
            $response->addHeader('X-Content-Type-Options', 'nosniff');
            if ($file['kind'] === 'sds') {
                $response->addHeader('Content-Security-Policy', "default-src 'none'; sandbox");
            }
            return $this->noStore($response);
        } catch (\Throwable $e) {
            return $this->fail('Datei nicht verfügbar.', Http::STATUS_NOT_FOUND);
        }
    }

    /** Record a non-sensitive reason so admins can diagnose public-link failures. */
    private function logDeniedShare(string $token, string $endpoint): void {
        $reason = $this->shares->active($token) === null ? 'unknown_or_expired' : 'password_mismatch';
        $this->logger->notice('Gefahrstoffkataster: Externe Freigabe abgewiesen.', [
            'link_id' => substr(hash('sha256', $token), 0, 12),
            'endpoint' => $endpoint,
            'reason' => $reason,
        ]);
    }

    private function password(): string {
        $password = $this->request->getParam('password', '');
        return is_string($password) && strlen($password) <= 1024 ? $password : '';
    }

    private function rows(string $table): array {
        return $this->db->getQueryBuilder()->select('*')->from($table)->executeQuery()->fetchAllAssociative();
    }

    private function one(string $table, int $id): ?array {
        $qb = $this->db->getQueryBuilder();
        $row = $qb->select('*')->from($table)
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
            ->executeQuery()->fetchAssociative();
        return $row ?: null;
    }

    private function deniedPublic(): DataResponse {
        $response = $this->fail('Freigabe nicht verfügbar oder Passwort ungültig.', Http::STATUS_NOT_FOUND);
        $response->throttle();
        return $this->noStore($response);
    }

    private function noStore(Response $response): Response {
        $response->addHeader('Cache-Control', 'no-store, private');
        $response->addHeader('Referrer-Policy', 'no-referrer');
        $response->addHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->addHeader('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}
