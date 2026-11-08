<?php
declare(strict_types=1);

// Lightweight guard against accidentally exposing mutating endpoints to public links.
// Full authorization and response-shape tests must still run inside Nextcloud.
$routes = require __DIR__ . '/../appinfo/routes.php';
$external = [];
foreach ($routes['routes'] ?? [] as $route) {
    if (str_starts_with($route['name'], 'external_share#')) $external[$route['name']] = $route;
}
$expected = [
    'external_share#listLinks' => ['GET', '/api/external-links'],
    'external_share#createLink' => ['POST', '/api/external-links'],
    'external_share#revokeLink' => ['POST', '/api/external-links/revoke'],
    'external_share#view' => ['GET', '/shared/{token}'],
    'external_share#read' => ['POST', '/shared/{token}/data'],
    'external_share#stock' => ['POST', '/shared/{token}/stock'],
    'external_share#file' => ['POST', '/shared/{token}/file/{id}'],
];
foreach ($expected as $name => [$method, $path]) {
    if (($external[$name]['verb'] ?? '') !== $method || ($external[$name]['url'] ?? '') !== $path) {
        throw new \RuntimeException('Unexpected public route: ' . $name);
    }
}
if (count($external) !== count($expected)) throw new \RuntimeException('Unexpected extra public route');
$controller = file_get_contents(__DIR__ . '/../lib/Controller/ExternalShareController.php');
$service = file_get_contents(__DIR__ . '/../lib/Service/ExternalShareService.php');
if (!is_string($controller) || !is_string($service)) throw new \RuntimeException('Source missing');
foreach (['view','read','file','stock'] as $method) {
    if (!preg_match('/#\\[PublicPage\\][\\s\\S]*?public function ' . $method . '\\(/', $controller)) {
        throw new \RuntimeException('Missing public declaration on ' . $method);
    }
}
foreach (['listLinks','createLink','revokeLink'] as $method) {
    if (!preg_match('/public function ' . $method . '\\(\\): DataResponse \\{\\s*if \\(\\!\\$this->access->canManage\\(\\)\\)/', $controller)) {
        throw new \RuntimeException('Missing server authorization on ' . $method);
    }
}
if (!str_contains($controller, "#[BruteForceProtection(action: 'gsk-external-password')]")
    || !str_contains($controller, '$response->throttle()')
    || !str_contains($service, "hash('sha256', $token)")
    || !str_contains($service, 'password_verify($password, $hash)')
    || !str_contains($controller, "getHeader('X-GSK-Stock-Action')")
    || !str_contains($controller, "'stock_editor'")
    || !str_contains($controller, 'ExternalStockBookingService')) {
    throw new \RuntimeException('Security guard missing');
}
echo "Public route methods and authorization declarations passed\n";
