<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa
class IotEvents extends IotBaseRepository
{
    public function create(IotEvent $e): int {
        $err=$e->validate(); if ($err) throw new InvalidArgumentException(implode('; ',$err));
        $a=$e->toDb();
        $sql='INSERT INTO `event`(device_id,ts,event_type,payload) VALUES (:device_id,:ts,:event_type,:payload)';
        $this->pdo->prepare($sql)->execute($a);
        return (int)$this->pdo->lastInsertId();
    }

    public function listByDevice(int $deviceId, int $limit=200): array {
        $st=$this->pdo->prepare('SELECT * FROM `event` WHERE device_id=? ORDER BY ts DESC LIMIT '.$limit);
        $st->execute([$deviceId]);
        return array_map(fn($r)=>new IotEvent($r), $st->fetchAll(PDO::FETCH_ASSOC));
    }
}
