<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * The only public mutation permitted by external stock-editor grants.
 * Does not accept product metadata, safety classifications or arbitrary DB fields.
 */
final class ExternalStockBookingService {
    public function __construct(private IDBConnection $db) {}

    public static function normalize(array $input): array {
        $pid = filter_var($input['product_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $lid = filter_var($input['location_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $action = $input['action'] ?? null;
        $value = $input['packs'] ?? null;
        $note = $input['note'] ?? '';
        $expected = $input['expected_milli'] ?? null;
        if (!$pid || !$lid) throw new \InvalidArgumentException('Produkt oder Lagerort ungültig.');
        if (!is_string($action) || !in_array($action, ['add','remove','set'], true)) {
            throw new \InvalidArgumentException('Ungültige Buchungsart.');
        }
        if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0 || (float)$value > 1000000) {
            throw new \InvalidArgumentException('Ungültige Gebindeanzahl.');
        }
        $milli = (int)round((float)$value * 1000);
        if ($action !== 'set' && $milli <= 0) throw new \InvalidArgumentException('Mindestmenge: 0,001 Gebinde.');
        if (!is_string($note) || mb_strlen($note) > 200) throw new \InvalidArgumentException('Notiz zu lang.');
        if (filter_var($expected, FILTER_VALIDATE_INT) === false || (int)$expected < 0 || (int)$expected > 1000000000) {
            throw new \InvalidArgumentException('Bitte den aktuellen Bestand neu laden.');
        }
        return [
            'product_id'=>(int)$pid, 'location_id'=>(int)$lid,
            'action'=>$action,'milli'=>$milli,'note'=>trim($note),
            'expected_milli'=>(int)$expected,
        ];
    }

    public static function allowsProduct(array $product, string $scope): bool {
        return ExternalShareProjection::allowsProduct($product, $scope);
    }

    private function one(string $table, int $id): ?array {
        $qb=$this->db->getQueryBuilder();
        $result=$qb->select('*')->from($table)
            ->where($qb->expr()->eq('id',$qb->createNamedParameter($id,IQueryBuilder::PARAM_INT)))
            ->executeQuery()->fetchAssociative();
        return $result ?: null;
    }

    private function insert(string $table,array $row): void {
        $qb=$this->db->getQueryBuilder();
        $qb->insert($table);
        foreach($row as $name=>$value){
            $qb->setValue($name,$qb->createNamedParameter($value,is_int($value)?IQueryBuilder::PARAM_INT:IQueryBuilder::PARAM_STR));
        }
        $qb->executeStatement();
    }

    /**
     * Book one validated stock movement. Uses the existing gsk_event audit table.
     * A request must include expected_milli from its most recent read, so a
     * concurrent booking cannot silently overwrite another person's change.
     */
    public function book(array $input, string $scope, string $actor): array {
        $p=self::normalize($input);
        if (!preg_match('/^external:[a-f0-9]{16}$/D',$actor)) {
            throw new \InvalidArgumentException('Ungültige Zugangskennung.');
        }
        try {
            $this->db->beginTransaction();
            $productRow=$this->one('gsk_product',$p['product_id']);
            if ($productRow===null || !$this->one('gsk_location',$p['location_id'])) {
                throw new \InvalidArgumentException('Produkt oder Lagerort unbekannt.');
            }
            $details=json_decode((string)$productRow['details'],true);
            if (!is_array($details) || !self::allowsProduct($details,$scope)) {
                throw new \DomainException('Produkt gehört nicht zur Freigabe.');
            }
            $qb=$this->db->getQueryBuilder();
            $current=$qb->select('*')->from('gsk_stock')
                ->where($qb->expr()->eq('product_id',$qb->createNamedParameter($p['product_id'],IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->eq('location_id',$qb->createNamedParameter($p['location_id'],IQueryBuilder::PARAM_INT)))
                ->executeQuery()->fetchAssociative();
            $before=$current?(int)$current['milli']:0;
            if ($before!==$p['expected_milli']) {
                throw new \RuntimeException('Bestand wurde zwischenzeitlich geändert. Bitte neu laden.');
            }
            $after=match($p['action']) {
                'set'=>$p['milli'],
                'add'=>$before+$p['milli'],
                'remove'=>$before-$p['milli'],
            };
            if ($after < 0 || $after > 1000000000) {
                throw new \InvalidArgumentException('Buchung würde einen ungültigen Bestand erzeugen.');
            }
            if ($current) {
                $update=$this->db->getQueryBuilder();
                $changed=$update->update('gsk_stock')
                    ->set('milli',$update->createNamedParameter($after,IQueryBuilder::PARAM_INT))
                    ->where($update->expr()->eq('id',$update->createNamedParameter((int)$current['id'],IQueryBuilder::PARAM_INT)))
                    ->andWhere($update->expr()->eq('milli',$update->createNamedParameter($before,IQueryBuilder::PARAM_INT)))
                    ->executeStatement();
                if ($changed!==1) throw new \RuntimeException('Bestand gleichzeitig geändert. Bitte neu laden.');
            } else {
                // Same insert semantics as the internal inventory controller.
                // Actual DB uniqueness/locking must be checked during integration.
                $this->insert('gsk_stock',[
                    'product_id'=>$p['product_id'],'location_id'=>$p['location_id'],'milli'=>$after,
                ]);
            }
            $this->insert('gsk_event',[
                'product_id'=>$p['product_id'], 'location_id'=>$p['location_id'],
                'before_milli'=>$before,'after_milli'=>$after,'action'=>$p['action'],
                'note'=>$p['note'],'actor'=>$actor,'created_at'=>gmdate('c'),
            ]);
            $this->db->commit();
            return ['before'=>$before/1000,'after'=>$after/1000,'actor'=>$actor];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
