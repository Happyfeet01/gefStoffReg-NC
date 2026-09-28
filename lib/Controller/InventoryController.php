<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

class InventoryController extends Controller {
    private const GROUP = 'freibad-gefahrstoffe';
    private const TEXT = [
        'name' => 160, 'manufacturer' => 120, 'article' => 100, 'ean' => 50,
        'ufi' => 80, 'category' => 80, 'use_area' => 100, 'unit' => 20,
        'classification' => 600, 'ghs' => 120, 'signal' => 40,
        'h_statements' => 1500, 'storage_note' => 800,
    ];
    private const HEADERS = ['Produkt','Hersteller','Artikelnummer','EAN','UFI',
        'Einsatzbereich','Kategorie','Lagerort','Gebindeanzahl','Gebindegröße',
        'Einheit','Gesamtmenge','Gefahrstoff','Einstufung','GHS','Signalwort',
        'H-Sätze','SDB-Datum','SDB-Datei','Geprüft am','Hinweise'];

    public function __construct(
        string $appName,
        IRequest $request,
        private IDBConnection $db,
        private IAppData $appData,
        private IUserSession $session,
        private IGroupManager $groups,
        private IURLGenerator $urls,
    ) {
        parent::__construct($appName, $request);
    }

    private function permitted(): bool {
        $user = $this->session->getUser();
        return $user !== null && ($this->groups->isAdmin($user->getUID()) || $this->groups->isInGroup($user->getUID(), self::GROUP));
    }

    private function denied(): DataResponse {
        return new DataResponse(['error' => 'Zugriff nur für Mitglieder von ' . self::GROUP . '.'], Http::STATUS_FORBIDDEN);
    }

    private function fail(string $message, int $status = Http::STATUS_BAD_REQUEST): DataResponse {
        return new DataResponse(['error' => $message], $status);
    }

    private function rows(string $table): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')->from($table)->executeQuery()->fetchAllAssociative();
    }

    private function one(string $table, int $id): ?array {
        $qb = $this->db->getQueryBuilder();
        $row = $qb->select('*')->from($table)
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
            ->executeQuery()->fetchAssociative();
        return $row ?: null;
    }

    private function payload(): array {
        $raw = $this->request->getParam('payload');
        if (!is_string($raw) || strlen($raw) > 20000) {
            throw new \InvalidArgumentException('Ungültige Eingabe.');
        }
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_is_list($data)) {
            throw new \InvalidArgumentException('JSON-Objekt erwartet.');
        }
        return $data;
    }

    private function validateProduct(array $input): array {
        $data = [];
        foreach (self::TEXT as $field => $max) {
            $value = $input[$field] ?? '';
            if (!is_string($value) || mb_strlen($value) > $max) {
                throw new \InvalidArgumentException($field . ': Text zu lang oder ungültig.');
            }
            $data[$field] = trim($value);
        }
        if ($data['name'] === '' || $data['unit'] === '') {
            throw new \InvalidArgumentException('Produktname und Einheit sind erforderlich.');
        }
        $size = $input['pack_size'] ?? null;
        if (!is_numeric($size) || !is_finite((float)$size) || (float)$size <= 0 || (float)$size > 1000000) {
            throw new \InvalidArgumentException('Gebindegröße ungültig.');
        }
        $data['pack_size'] = (float)$size;
        if (!is_bool($input['hazardous'] ?? null)) {
            throw new \InvalidArgumentException('Gefahrstoff muss Ja oder Nein sein.');
        }
        $data['hazardous'] = $input['hazardous'];
        foreach (['sds_date','checked_at'] as $field) {
            $value = $input[$field] ?? '';
            if (!is_string($value) || ($value !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || !checkdate((int)substr($value,5,2), (int)substr($value,8,2), (int)substr($value,0,4))))) {
                throw new \InvalidArgumentException('Datum ungültig.');
            }
            $data[$field] = $value;
        }
        return $data;
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): Response {
        if (!$this->permitted()) {
            return $this->denied();
        }
        Util::addStyle('gefahrstoffkataster', 'style');
        Util::addScript('gefahrstoffkataster', 'app');
        return new TemplateResponse('gefahrstoffkataster', 'main');
    }

    #[NoAdminRequired]
    public function bootstrap(): DataResponse {
        if (!$this->permitted()) return $this->denied();
        try {
            if (!$this->rows('gsk_location')) {
                foreach (['Chemieraum','Putzraum','Werkstatt','Technikkeller NSB','Technikkeller SB'] as $name) {
                    try { $this->db->insert('gsk_location', ['name' => $name]); } catch (\Throwable $e) { /* simultaneous initialisation */ }
                }
            }
            $products = [];
            foreach ($this->rows('gsk_product') as $row) {
                $data = json_decode($row['details'], true);
                $data['id'] = (int)$row['id'];
                $data['stock'] = [];
                $data['photos'] = [];
                $data['sds_id'] = null;
                $products[$row['id']] = $data;
            }
            foreach ($this->rows('gsk_stock') as $row) {
                if (isset($products[$row['product_id']])) {
                    $products[$row['product_id']]['stock'][] = ['location_id' => (int)$row['location_id'], 'packs' => (int)$row['milli'] / 1000];
                }
            }
            foreach ($this->rows('gsk_file') as $row) {
                if (!isset($products[$row['product_id']])) continue;
                if ($row['kind'] === 'photo') $products[$row['product_id']]['photos'][] = ['id' => (int)$row['id'], 'filename' => $row['filename']];
                if ($row['kind'] === 'sds') $products[$row['product_id']]['sds_id'] = (int)$row['id'];
            }
            $locations = array_map(static fn(array $row): array => ['id' => (int)$row['id'], 'name' => $row['name']], $this->rows('gsk_location'));
            return new DataResponse(['products' => array_values($products), 'locations' => $locations]);
        } catch (\Throwable $e) {
            return $this->fail('Daten konnten nicht geladen werden.', 500);
        }
    }

    #[NoAdminRequired]
    public function addLocation(): DataResponse {
        if (!$this->permitted()) return $this->denied();
        try {
            $name = $this->payload()['name'] ?? null;
            if (!is_string($name) || mb_strlen(trim($name)) < 1 || mb_strlen(trim($name)) > 80) throw new \InvalidArgumentException('Lagerort ungültig.');
            $this->db->insert('gsk_location', ['name' => trim($name)]);
            return new DataResponse(['id' => (int)$this->db->lastInsertId('*PREFIX*gsk_location')], 201);
        } catch (\Throwable $e) {
            return $this->fail('Lagerort ungültig oder bereits vorhanden.');
        }
    }

    #[NoAdminRequired]
    public function saveProduct(): DataResponse {
        if (!$this->permitted()) return $this->denied();
        try {
            $data = $this->validateProduct($this->payload());
            $time = gmdate('c');
            $this->db->insert('gsk_product', ['details' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'created_at' => $time, 'updated_at' => $time]);
            return new DataResponse(['id' => (int)$this->db->lastInsertId('*PREFIX*gsk_product')], 201);
        } catch (\InvalidArgumentException|\JsonException $e) {
            return $this->fail($e->getMessage());
        }
    }

    #[NoAdminRequired]
    public function updateProduct(int $id): DataResponse {
        if (!$this->permitted()) return $this->denied();
        if (!$this->one('gsk_product', $id)) return $this->fail('Produkt nicht gefunden.', 404);
        try {
            $data = $this->validateProduct($this->payload());
            $this->db->update('gsk_product', ['details' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'updated_at' => gmdate('c')], ['id' => $id]);
            return new DataResponse(['id' => $id]);
        } catch (\InvalidArgumentException|\JsonException $e) {
            return $this->fail($e->getMessage());
        }
    }

    #[NoAdminRequired]
    public function adjustStock(): DataResponse {
        if (!$this->permitted()) return $this->denied();
        try {
            $input = $this->payload();
            $pid = filter_var($input['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $lid = filter_var($input['location_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $action = $input['action'] ?? null;
            $packs = $input['packs'] ?? null;
            $note = $input['note'] ?? '';
            if (!$pid || !$lid || !$this->one('gsk_product', $pid) || !$this->one('gsk_location', $lid)) throw new \InvalidArgumentException('Produkt oder Lagerort unbekannt.');
            if (!in_array($action, ['add','remove','set'], true) || !is_numeric($packs) || !is_finite((float)$packs) || (float)$packs < 0 || (float)$packs > 1000000 || ($action !== 'set' && (float)$packs <= 0)) throw new \InvalidArgumentException('Gebindeanzahl ungültig.');
            if (!is_string($note) || mb_strlen($note) > 200) throw new \InvalidArgumentException('Notiz zu lang.');
            $amount = (int)round((float)$packs * 1000);
            if ($action !== 'set' && $amount === 0) throw new \InvalidArgumentException('Mindestmenge: 0,001 Gebinde.');
            $this->db->beginTransaction();
            $qb = $this->db->getQueryBuilder();
            $record = $qb->select('*')->from('gsk_stock')
                ->where($qb->expr()->eq('product_id', $qb->createNamedParameter($pid)))
                ->andWhere($qb->expr()->eq('location_id', $qb->createNamedParameter($lid)))
                ->executeQuery()->fetchAssociative();
            $before = $record ? (int)$record['milli'] : 0;
            $after = $action === 'set' ? $amount : $before + ($action === 'add' ? $amount : -$amount);
            if ($after < 0) throw new \InvalidArgumentException('Entnahme übersteigt den Bestand.');
            if ($record) {
                $q = $this->db->getQueryBuilder();
                $changed = $q->update('gsk_stock')->set('milli', $q->createNamedParameter($after))
                    ->where($q->expr()->eq('id', $q->createNamedParameter((int)$record['id'])))
                    ->andWhere($q->expr()->eq('milli', $q->createNamedParameter($before)))
                    ->executeStatement();
                if ($changed !== 1) throw new \RuntimeException('Bestand wurde gleichzeitig geändert. Bitte erneut laden.');
            } else {
                $this->db->insert('gsk_stock', ['product_id' => $pid, 'location_id' => $lid, 'milli' => $after]);
            }
            $this->db->insert('gsk_event', ['product_id' => $pid, 'location_id' => $lid, 'before_milli' => $before,
                'after_milli' => $after, 'action' => $action, 'note' => trim($note),
                'actor' => $this->session->getUser()->getUID(), 'created_at' => gmdate('c')]);
            $this->db->commit();
            return new DataResponse(['before' => $before / 1000, 'after' => $after / 1000]);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->fail($e instanceof \InvalidArgumentException ? $e->getMessage() : 'Bestand gleichzeitig geändert oder Buchung fehlgeschlagen. Bitte neu laden.', $e instanceof \InvalidArgumentException ? 400 : 409);
        }
    }

    private function documentFolder() {
        try { return $this->appData->getFolder('documents'); }
        catch (NotFoundException $e) { return $this->appData->newFolder('documents'); }
    }

    #[NoAdminRequired]
    public function upload(int $id): DataResponse {
        if (!$this->permitted()) return $this->denied();
        if (!$this->one('gsk_product', $id)) return $this->fail('Produkt nicht gefunden.', 404);
        $upload = $this->request->getUploadedFile('file');
        $kind = $this->request->getParam('kind');
        if (!is_array($upload) || !in_array($kind, ['photo','sds'], true) || (int)($upload['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($upload['tmp_name'] ?? ''))) return $this->fail('Upload fehlgeschlagen. PHP-Uploadgrenze prüfen.');
        $path = $upload['tmp_name'];
        $size = filesize($path);
        if (!$size || $size > ($kind === 'photo' ? 8 : 15) * 1024 * 1024) return $this->fail('Datei zu groß (Foto 8 MB, SDB 15 MB).');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $allowed = $kind === 'sds' ? ['application/pdf'] : ['image/jpeg','image/png','image/webp','image/heic','image/heif'];
        if (!in_array($mime, $allowed, true)) return $this->fail('Dateityp nicht erlaubt.');
        $bytes = file_get_contents($path);
        if ($bytes === false || ($kind === 'sds' && !str_starts_with($bytes, '%PDF-'))) return $this->fail('Datei konnte nicht geprüft werden.');
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename((string)$upload['name']));
        $filename = substr($filename ?: 'Datei', 0, 180);
        $storageName = bin2hex(random_bytes(16));
        try {
            $folder = $this->documentFolder();
            $folder->newFile($storageName)->putContent($bytes);
            $this->db->insert('gsk_file', ['product_id' => $id, 'kind' => $kind,
                'filename' => $filename, 'mime' => $mime, 'storage_name' => $storageName,
                'created_at' => gmdate('c')]);
            return new DataResponse(['id' => (int)$this->db->lastInsertId('*PREFIX*gsk_file')], 201);
        } catch (\Throwable $e) {
            try { $folder->getFile($storageName)->delete(); } catch (\Throwable $ignored) {}
            return $this->fail('Datei konnte nicht gespeichert werden.', 500);
        }
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function download(int $id): Response {
        if (!$this->permitted()) return $this->denied();
        $record = $this->one('gsk_file', $id);
        if (!$record) return $this->fail('Datei nicht gefunden.', 404);
        try {
            $data = $this->documentFolder()->getFile($record['storage_name'])->getContent();
            $response = new DataDownloadResponse($data, $record['filename'], $record['mime']);
            $response->addHeader('X-Content-Type-Options', 'nosniff');
            $response->addHeader('Cache-Control', 'no-store');
            return $response;
        } catch (\Throwable $e) {
            return $this->fail('Datei nicht verfügbar.', 404);
        }
    }

    private static function excelSafe($value): string {
        $text = (string)$value;
        return preg_match('/^\s*[=+@\-]/u', $text) ? "'" . $text : $text;
    }

    private function exportRows(bool $hazardOnly): array {
        $locations = [];
        foreach ($this->rows('gsk_location') as $location) $locations[$location['id']] = $location['name'];
        $stock = [];
        foreach ($this->rows('gsk_stock') as $entry) $stock[$entry['product_id']][] = $entry;
        $files = [];
        foreach ($this->rows('gsk_file') as $entry) if ($entry['kind'] === 'sds') $files[$entry['product_id']] = $entry['id'];
        $result = [self::HEADERS];
        foreach ($this->rows('gsk_product') as $entry) {
            $p = json_decode($entry['details'], true);
            if ($hazardOnly && empty($p['hazardous'])) continue;
            foreach (($stock[$entry['id']] ?? [null]) as $s) {
                $count = $s ? (int)$s['milli'] / 1000 : 0;
                $fileUrl = isset($files[$entry['id']]) ? $this->urls->linkToRouteAbsolute('gefahrstoffkataster.inventory.download', ['id' => $files[$entry['id']]]) : '';
                $result[] = [$p['name'], $p['manufacturer'], $p['article'], $p['ean'], $p['ufi'],
                    $p['use_area'], $p['category'], $s ? ($locations[$s['location_id']] ?? '') : '',
                    $count, $p['pack_size'], $p['unit'], round($count * $p['pack_size'], 6),
                    !empty($p['hazardous']) ? 'Ja' : 'Nein', $p['classification'], $p['ghs'],
                    $p['signal'], $p['h_statements'], $p['sds_date'], $fileUrl,
                    $p['checked_at'], $p['storage_note']];
            }
        }
        return $result;
    }

    private function sheet(array $rows): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $index => $row) {
            $xml .= '<row r="' . ($index + 1) . '">';
            foreach ($row as $column => $value) {
                $name = ''; $i = $column + 1;
                while ($i > 0) { $mod = ($i - 1) % 26; $name = chr(65 + $mod) . $name; $i = intdiv($i - 1, 26); }
                $ref = $name . ($index + 1);
                if (is_int($value) || is_float($value)) $xml .= '<c r="' . $ref . '"><v>' . $value . '</v></c>';
                else {
                    $safe = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', self::excelSafe($value));
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . htmlspecialchars($safe, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData><autoFilter ref="A1:U' . count($rows) . '"/></worksheet>';
    }

    private function makeXlsx(): string {
        if (!class_exists(\ZipArchive::class)) throw new \RuntimeException('PHP-Erweiterung zip fehlt.');
        $temp = tempnam(sys_get_temp_dir(), 'gsk');
        if ($temp === false) throw new \RuntimeException('Temporäre Datei nicht verfügbar.');
        try {
            $z = new \ZipArchive();
            if ($z->open($temp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('ZIP konnte nicht erstellt werden.');
            $z->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $z->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>');
            $z->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Gesamtbestand" sheetId="1" r:id="rId1"/><sheet name="Gefahrstoffverzeichnis" sheetId="2" r:id="rId2"/></sheets></workbook>');
            $z->addFromString('xl/worksheets/sheet1.xml', $this->sheet($this->exportRows(false)));
            $z->addFromString('xl/worksheets/sheet2.xml', $this->sheet($this->exportRows(true)));
            $z->close();
            $data = file_get_contents($temp);
            if ($data === false) throw new \RuntimeException('Export konnte nicht gelesen werden.');
            return $data;
        } finally { @unlink($temp); }
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function export(string $format): Response {
        if (!$this->permitted()) return $this->denied();
        try {
            if ($format === 'xlsx') return new DataDownloadResponse($this->makeXlsx(), 'Gefahrstoffkataster.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            if ($format !== 'csv') return $this->fail('Format unbekannt.', 404);
            $stream = fopen('php://temp', 'w+');
            foreach ($this->exportRows(false) as $row) fputcsv($stream, array_map(self::excelSafe(...), $row), ';');
            rewind($stream);
            $csv = stream_get_contents($stream);
            fclose($stream);
            return new DataDownloadResponse("\xEF\xBB\xBF" . $csv, 'Gefahrstoffkataster.csv', 'text/csv; charset=utf-8');
        } catch (\Throwable $e) {
            return $this->fail('Export fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }
}
