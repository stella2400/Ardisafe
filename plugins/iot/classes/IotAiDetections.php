<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa

class IotAiDetections extends IotBaseRepository
{
    public function create(IotAiDetection $d): int {
        $err=$d->validate(); if ($err) throw new InvalidArgumentException(implode('; ',$err));
        $a=$d->toDb();
        $sql='INSERT INTO ai_detection(frame_id,label,confidence,bbox) VALUES (:frame_id,:label,:confidence,:bbox)';
        $this->pdo->prepare($sql)->execute($a);
        return (int)$this->pdo->lastInsertId();
    }

    public function listByFrame(int $frameId): array {
        $st=$this->pdo->prepare('SELECT * FROM ai_detection WHERE frame_id=? ORDER BY id ASC');
        $st->execute([$frameId]);
        return array_map(fn($r)=>new IotAiDetection($r), $st->fetchAll(PDO::FETCH_ASSOC));
    }
}
