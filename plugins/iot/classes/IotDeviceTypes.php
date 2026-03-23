<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa
class IotDeviceTypes extends IotBaseRepository
{
    public function findById(int $id): ?IotDeviceType {
        $st=$this->pdo->prepare('SELECT * FROM device_type WHERE id=?'); $st->execute([$id]);
        $row=$st->fetch(PDO::FETCH_ASSOC); return $row? new IotDeviceType($row):null;
    }

    public function findBySignature(string $kind, ?string $vendor, ?string $model): ?IotDeviceType {
        $sql='SELECT * FROM device_type WHERE kind=? AND ';
        $par=[$kind];
        if ($vendor===null){ $sql.='vendor IS NULL AND '; } else { $sql.='vendor=? AND '; $par[]=$vendor; }
        if ($model===null){ $sql.='model IS NULL'; } else { $sql.='model=?'; $par[]=$model; }
        $st=$this->pdo->prepare($sql); $st->execute($par);
        $row=$st->fetch(PDO::FETCH_ASSOC); return $row? new IotDeviceType($row):null;
    }

    public function listAll(): array {
        $st=$this->pdo->query('SELECT * FROM device_type ORDER BY kind, vendor, model');
        return array_map(fn($r)=>new IotDeviceType($r), $st->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create(IotDeviceType $t): int {
        $err=$t->validate(); if ($err) throw new InvalidArgumentException(implode('; ',$err));
        $a=$t->toDb();
        $sql='INSERT INTO device_type(kind,vendor,model,capabilities) VALUES (:kind,:vendor,:model,:capabilities)';
        $this->pdo->prepare($sql)->execute($a);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(IotDeviceType $t): void {
        if (!$t->getId()) throw new InvalidArgumentException('ID mancante');
        $err=$t->validate(); if ($err) throw new InvalidArgumentException(implode('; ',$err));
        $a=$t->toDb(); $a['id']=$t->getId();
        $sql='UPDATE device_type SET kind=:kind,vendor=:vendor,model=:model,capabilities=:capabilities WHERE id=:id';
        $this->pdo->prepare($sql)->execute($a);
    }

    public function delete(int $id): void {
        $this->pdo->prepare('DELETE FROM device_type WHERE id=?')->execute([$id]);
    }
}
