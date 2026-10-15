<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Service;

use OCP\Http\Client\IClientService;
use OCP\Security\IRemoteHostValidator;

class SdsDownloader {
    public function __construct(private IClientService $clients, private IRemoteHostValidator $hosts) {}

    public static function resolve(string $base, string $location): string {
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $location)) return $location;
        if (str_starts_with($location, '//')) return 'https:' . $location;
        $parts = parse_url($base);
        $origin = 'https://' . $parts['host'];
        if (str_starts_with($location, '/')) return $origin . $location;
        $path = $parts['path'] ?? '/';
        if (str_starts_with($location, '?')) return $origin . $path . $location;
        return $origin . substr($path, 0, (int)strrpos($path, '/') + 1) . $location;
    }

    public function download(string $url): string {
        $client = $this->clients->newClient();
        for ($redirect = 0; $redirect <= 3; $redirect++) {
            $parts = parse_url($url);
            if (strlen($url) > 2000 || !filter_var($url, FILTER_VALIDATE_URL)
                || preg_match('/[\x00-\x20\\\\]/', $url) || ($parts['scheme'] ?? '') !== 'https'
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
                || !$this->hosts->isValid($parts['host'] ?? '')) {
                throw new \InvalidArgumentException('SDB-Link muss eine öffentliche HTTPS-Adresse ohne Zugangsdaten oder eigenen Port sein.');
            }
            try {
                $response = $client->get($url, ['timeout' => 20, 'connect_timeout' => 5, 'read_timeout' => 15,
                    'stream' => true, 'allow_redirects' => false, 'http_errors' => false,
                    'headers' => ['Accept' => 'application/pdf', 'Accept-Encoding' => 'identity']]);
                $body = $response->getBody();
                try {
                    $status = $response->getStatusCode();
                    if (in_array($status, [301,302,303,307,308], true)) {
                        $location = trim($response->getHeader('Location'));
                        if ($location === '' || $redirect === 3) throw new \RuntimeException('SDB-Link enthält zu viele oder ungültige Weiterleitungen.');
                        $url = self::resolve($url, $location);
                        continue;
                    }
                    if ($status !== 200) throw new \RuntimeException('SDB konnte nicht geladen werden (HTTP ' . $status . '). Bitte Link und Zugriff beim Hersteller prüfen.');
                    $max = 15 * 1024 * 1024;
                    if ((int)$response->getHeader('Content-Length') > $max) throw new \RuntimeException('SDB-PDF darf maximal 15 MB groß sein.');
                    if (!is_resource($body)) throw new \RuntimeException('SDB konnte nicht als Datenstrom gelesen werden.');
                    stream_set_timeout($body, 15);
                    $bytes = stream_get_contents($body, $max + 1);
                    if (!is_string($bytes) || strlen($bytes) > $max) throw new \RuntimeException('SDB-PDF darf maximal 15 MB groß sein.');
                    if (!feof($body) || !str_starts_with($bytes, '%PDF-') || (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== 'application/pdf') {
                        throw new \RuntimeException('Der SDB-Link liefert kein vollständiges PDF. Bitte den direkten PDF-Link verwenden oder das SDB als Datei hochladen.');
                    }
                    return $bytes;
                } finally { if (is_resource($body)) fclose($body); }
            } catch (\InvalidArgumentException|\RuntimeException $e) { throw $e; }
            catch (\Throwable $e) { throw new \RuntimeException('SDB-Link nicht erreichbar. Bitte direkten PDF-Link prüfen oder PDF hochladen.'); }
        }
        throw new \RuntimeException('SDB-Link konnte nicht geladen werden.');
    }
}
