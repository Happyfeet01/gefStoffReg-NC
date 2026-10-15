<?php
declare(strict_types=1);
namespace OCP\Http\Client { interface IClientService {} }
namespace OCP\Security { interface IRemoteHostValidator {} }
namespace {
    require __DIR__ . '/../lib/Service/SdsDownloader.php';
    use OCA\Gefahrstoffkataster\Service\SdsDownloader;
    class ClientFixture implements \OCP\Http\Client\IClientService {
        public array $responses = [];
        public array $calls = [];
        public function newClient(): self { return $this; }
        public function get(string $url, array $options): object {
            if ($options['allow_redirects'] !== false || !$options['stream']) throw new \LogicException('Unsafe request options');
            $this->calls[] = $url;
            $fixture = array_shift($this->responses);
            if (!$fixture) throw new \LogicException('Unexpected request');
            return new class($fixture) {
                public function __construct(private array $fixture) {}
                public function getStatusCode(): int { return $this->fixture['status'] ?? 200; }
                public function getHeader(string $key): string { return $this->fixture[$key] ?? ''; }
                public function getBody() { $stream = fopen('php://temp', 'w+'); fwrite($stream, $this->fixture['body'] ?? ''); rewind($stream); return $stream; }
            };
        }
    }
    $hosts = new class implements \OCP\Security\IRemoteHostValidator {
        public function isValid(string $host): bool { return in_array($host, ['manufacturer.example','cdn.example'], true); }
    };
    $client = new ClientFixture(); $loader = new SdsDownloader($client, $hosts);
    $pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n";
    $client->responses = [['status'=>302,'Location'=>'/download/sdb.pdf'],['body'=>$pdf]];
    if ($loader->download('https://manufacturer.example/product') !== $pdf || $client->calls[1] !== 'https://manufacturer.example/download/sdb.pdf') throw new \RuntimeException('PDF redirect failed');
    foreach (['http://manufacturer.example/a','https://127.0.0.1/a','https://user:pass@manufacturer.example/a','https://manufacturer.example:8080/a','https://manufacturer.example/\\evil'] as $url) {
        try { $loader->download($url); } catch (\InvalidArgumentException $e) { continue; }
        throw new \RuntimeException('Unsafe URL accepted');
    }
    foreach ([
        [['body'=>'<html>Login required</html>']],
        [['status'=>404]],
        [['Content-Length'=>(string)(16*1024*1024)]],
        [['body'=>str_repeat('x',15*1024*1024+1)]],
        array_fill(0,4,['status'=>302,'Location'=>'/loop']),
        [['status'=>302,'Location'=>'http://manufacturer.example/insecure']],
        [['status'=>302,'Location'=>'https://127.0.0.1/private']],
    ] as $responses) {
        $client->responses=$responses;
        try { $loader->download('https://manufacturer.example/sds.pdf'); }
        catch (\RuntimeException|\InvalidArgumentException $e) { continue; }
        throw new \RuntimeException('Invalid response accepted');
    }
    if (SdsDownloader::resolve('https://manufacturer.example/a/b.pdf','?id=2') !== 'https://manufacturer.example/a/b.pdf?id=2') throw new \RuntimeException('Query redirect wrong');
    echo "SDB download, redirects, private-address blocking, size limits and non-PDF rejection passed\n";
}
