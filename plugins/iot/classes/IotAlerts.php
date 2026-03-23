<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa

class IotAlerts extends IotBaseRepository
{
    public function create(IotAlert $a): int {
        $err=$a->validate(); if ($err) throw new InvalidArgumentException(implode('; ',$err));
        $arr=$a->toDb();
        $sql='INSERT INTO alert(customer_id,severity,status,title,message) VALUES (:customer_id,:severity,:status,:title,:message)';
        $this->pdo->prepare($sql)->execute($arr);
        return (int)$this->pdo->lastInsertId();
    }

    public function listOpenByCustomer(int $customerId, int $limit=200): array {
        $st=$this->pdo->prepare('SELECT * FROM alert WHERE customer_id=? AND status IN ("open","ack") ORDER BY created_at DESC LIMIT '.$limit);
        $st->execute([$customerId]);
        return array_map(fn($r)=>new IotAlert($r), $st->fetchAll(PDO::FETCH_ASSOC));
    }

    public function ack(int $id): void {
        $this->pdo->prepare('UPDATE alert SET status="ack" WHERE id=? AND status="open"')->execute([$id]);
    }

    public function close(int $id): void {
        $this->pdo->prepare('UPDATE alert SET status="closed", resolved_at=CURRENT_TIMESTAMP WHERE id=? AND status<>"closed"')->execute([$id]);
    }
}
