<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa

class IotCameraFrames extends IotBaseRepository
{
    public function create(IotCameraFrame $f): int {
        $err=$f->validate(); if ($err) throw new InvalidArgumentException(implode('; ',$err));
        $a=$f->toDb();
        $sql='INSERT INTO camera_frame(device_id,ts,path,thumb_path) VALUES (:device_id,:ts,:path,:thumb_path)';
        $this->pdo->prepare($sql)->execute($a);
        return (int)$this->pdo->lastInsertId();
    }

    public function listByDevice(int $deviceId, int $limit=100): array {
        $st=$this->pdo->prepare('SELECT * FROM camera_frame WHERE device_id=? ORDER BY ts DESC LIMIT '.$limit);
        $st->execute([$deviceId]);
        return array_map(fn($r)=>new IotCameraFrame($r), $st->fetchAll(PDO::FETCH_ASSOC));
    }
}
