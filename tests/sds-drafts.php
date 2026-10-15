<?php
declare(strict_types=1);
namespace OCP\Files { interface IAppData {} class NotFoundException extends \Exception {} }
namespace {
    require __DIR__ . '/../lib/Service/SdsDraftStore.php';
    class DraftFile {
        public string $content = '';
        public function __construct(private DraftFolder $folder, private string $name) {}
        public function getName(): string { return $this->name; }
        public function putContent(string $bytes): void { $this->content = $bytes; }
        public function getContent(): string { return $this->content; }
        public function delete(): void { unset($this->folder->files[$this->name]); }
    }
    class DraftFolder {
        public array $files = [];
        public function newFile(string $name): DraftFile { return $this->files[$name] = new DraftFile($this, $name); }
        public function getFile(string $name): DraftFile { return $this->files[$name] ?? throw new \OCP\Files\NotFoundException(); }
        public function getDirectoryListing(): array { return array_values($this->files); }
    }
    $data = new class implements \OCP\Files\IAppData {
        public array $folders = [];
        public function newFolder(string $name): DraftFolder { return $this->folders[$name] = new DraftFolder(); }
        public function getFolder(string $name): DraftFolder { return $this->folders[$name] ?? throw new \OCP\Files\NotFoundException(); }
    };
    $store = new \OCA\Gefahrstoffkataster\Service\SdsDraftStore($data);
    $token = $store->stage('alice', '%PDF-test');
    if ($store->read('alice', $token) !== '%PDF-test') throw new \RuntimeException('Draft bytes changed');
    foreach ([$token, '../documents/secret', (time()-90000).'-'.str_repeat('a',32)] as $candidate) {
        try { $store->read('bob', $candidate); } catch (\InvalidArgumentException $e) { continue; }
        throw new \RuntimeException('Foreign or invalid draft accepted');
    }
    $store->remove('alice', $token);
    try { $store->read('alice', $token); throw new \RuntimeException('Consumed draft still present'); }
    catch (\InvalidArgumentException $e) {}
    for ($i=0; $i<5; $i++) $store->stage('alice', '%PDF-test');
    if (count($data->getFolder('sds-draft-'.hash('sha256','alice'))->files) !== 3) throw new \RuntimeException('Draft count unbounded');
    echo "SDB draft ownership, expiry, consumption and cache bound cases passed\n";
}
