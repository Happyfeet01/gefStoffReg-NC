<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Service;

use OCP\Files\IAppData;
use OCP\Files\NotFoundException;

/** Short-lived private drafts; never put another user's token in a shared folder. */
class SdsDraftStore {
    public function __construct(private IAppData $appData) {}

    private function folder(string $uid) {
        $name = 'sds-draft-' . hash('sha256', $uid);
        try { return $this->appData->getFolder($name); }
        catch (NotFoundException $e) { return $this->appData->newFolder($name); }
    }

    public function stage(string $uid, string $bytes): string {
        $folder = $this->folder($uid);
        $files = $folder->getDirectoryListing();
        usort($files, static fn($a, $b) => strcmp($a->getName(), $b->getName()));
        foreach ($files as $file) {
            if ((int)substr($file->getName(), 0, 10) < time() - 86400 || count($files) >= 3) {
                $file->delete(); array_shift($files);
            }
        }
        $token = time() . '-' . bin2hex(random_bytes(16));
        $folder->newFile($token)->putContent($bytes);
        return $token;
    }

    public function read(string $uid, string $token): string {
        if (!preg_match('/^\d{10}-[a-f0-9]{32}$/D', $token) || (int)substr($token, 0, 10) < time() - 86400) {
            throw new \InvalidArgumentException('SDB-Vorschau abgelaufen. Bitte erneut vom Link laden.');
        }
        try { return $this->folder($uid)->getFile($token)->getContent(); }
        catch (NotFoundException $e) { throw new \InvalidArgumentException('SDB-Vorschau nicht mehr vorhanden. Bitte erneut vom Link laden.'); }
    }

    public function remove(string $uid, string $token): void {
        $this->read($uid, $token);
        $this->folder($uid)->getFile($token)->delete();
    }
}
