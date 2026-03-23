<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa

class IotArmingStates extends IotBaseRepository
{
    public function getByCustomer(int $customerId): ?IotArmingState {
        $st=$this->pdo->prepare('SELECT * FROM arming_state WHERE customer_id=? LIMIT 1');
        $st->execute([$customerId]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        return $row? new IotArmingState($row):null;
    }

    public function setMode(int $customerId, string $mode, ?int $updatedBy): void {
        if (!in_array($mode,['disarmed','home','away','night'],true)) throw new InvalidArgumentException('mode non valido');
        $curr=$this->getByCustomer($customerId);
        if ($curr){
            $st=$this->pdo->prepare('UPDATE arming_state SET mode=?, updated_by=?, updated_at=CURRENT_TIMESTAMP WHERE customer_id=?');
            $st->execute([$mode,$updatedBy,$customerId]);
        } else {
            $st=$this->pdo->prepare('INSERT INTO arming_state(customer_id,mode,updated_by) VALUES (?,?,?)');
            $st->execute([$customerId,$mode,$updatedBy]);
        }
    }
}
