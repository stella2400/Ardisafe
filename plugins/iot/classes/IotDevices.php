<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa
class IotDevices extends IotBaseRepository
{
    public function findById(int $id): ?IotDevice {
        $st=$this->pdo->prepare('SELECT * FROM device WHERE id=?');
        $st->execute([$id]);
        $r=$st->fetch(PDO::FETCH_ASSOC);
        return $r? new IotDevice($r) : null;
    }

    public function listAll(int $customerId, string $q = '', int $limit=200, int $offset=0, string $orderBy='id DESC'): array {
        $sql="SELECT d.* FROM device d WHERE d.customer_id=?";
        $par=[$customerId];
        if ($q!==''){ $sql.=" AND (d.name LIKE ? OR d.ident LIKE ?)"; $par[]='%'.$q.'%'; $par[]='%'.$q.'%'; }
        $sql.=" ORDER BY {$orderBy} LIMIT {$limit} OFFSET {$offset}";
        $st=$this->pdo->prepare($sql); $st->execute($par);
        $rows=$st->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r)=>new IotDevice($r), $rows);
    }

    public function create(IotDevice $d): int {
        $err=$d->validate(); if ($err) throw new InvalidArgumentException(implode('; ',$err));
        $a=$d->toDb();
        $sql="INSERT INTO device (customer_id,room_id,type_id,name,ident,status,last_seen,meta)
              VALUES (:customer_id,:room_id,:type_id,:name,:ident,:status,:last_seen,:meta)";
        $this->pdo->prepare($sql)->execute($a);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(IotDevice $d): void {
        if (!$d->getId()) throw new InvalidArgumentException('ID mancante');
        $err=$d->validate(); if ($err) throw new InvalidArgumentException(implode('; ',$err));
        $a=$d->toDb(); $a['id']=$d->getId();
        $sql="UPDATE device SET customer_id=:customer_id, room_id=:room_id, type_id=:type_id, name=:name,
              ident=:ident, status=:status, last_seen=:last_seen, meta=:meta
              WHERE id=:id";
        $this->pdo->prepare($sql)->execute($a);
    }

    public function delete(int $id): void {
        $this->pdo->prepare('DELETE FROM device WHERE id=?')->execute([$id]);
    }
    public function updatePosition(int $deviceId, float $x, float $y, int $customerId, int $roomId): bool {
        $st = $this->pdo->prepare('UPDATE iot_device SET x_pct=?, y_pct=? WHERE id=? AND room_id=? AND customer_id=?');
        return $st->execute([$x, $y, $deviceId, $roomId, $customerId]);
    }

}
