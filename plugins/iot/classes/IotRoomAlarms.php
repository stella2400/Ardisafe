<?php
require_once __DIR__ . '/IotBaseRepository.php';

/**
 * Gestione stato allarme delle stanze.
 * Stati: disarmed | armed | triggered
 * NB: la tabella room_alarm ha (id PK), UNIQUE(room_id), FK(customer_id), FK(room_id)
 * -> qui usiamo SEMPRE anche customer_id per coerenza multi-tenant.
 */
class IotRoomAlarms extends IotBaseRepository
{
    /** @var string[] */
    private array $ALLOWED = ['disarmed','armed','triggered'];

    /**
     * Ritorna una mappa room_id => state per un insieme di stanze del dato customer.
     * @param int[] $roomIds
     * @return array<int,string>
     */
    public function getStatesByRoomIds(array $roomIds, int $customerId): array
    {
        if (empty($roomIds)) return [];

        $in = implode(',', array_fill(0, count($roomIds), '?'));
        $sql = "SELECT room_id, state
                  FROM room_alarm
                 WHERE customer_id = ? AND room_id IN ($in)";

        $st = $this->pdo->prepare($sql);
        $st->bindValue(1, (int)$customerId, \PDO::PARAM_INT);
        $i = 2;
        foreach ($roomIds as $rid) {
            $st->bindValue($i++, (int)$rid, \PDO::PARAM_INT);
        }
        $st->execute();

        $out = [];
        foreach ($st as $r) {
            $out[(int)$r['room_id']] = (string)$r['state'];
        }
        return $out;
    }

    /**
     * Stato corrente per una stanza del dato customer.
     * Se non esiste, crea record con state='disarmed' e lo ritorna.
     */
    public function getState(int $roomId, int $customerId): string
    {
        $st = $this->pdo->prepare(
            'SELECT state FROM room_alarm WHERE customer_id = ? AND room_id = ? LIMIT 1'
        );
        $st->execute([(int)$customerId, (int)$roomId]);
        $s = $st->fetchColumn();

        if ($s === false) {
            // inizializza il record
            $ins = $this->pdo->prepare(
                'INSERT INTO room_alarm (customer_id, room_id, state) VALUES (?, ?, ?)'
            );
            $ins->execute([(int)$customerId, (int)$roomId, 'disarmed']);
            return 'disarmed';
        }
        return (string)$s;
    }

    /**
     * Imposta lo stato della stanza per il dato customer (upsert compatibile 5.7).
     */
    public function setState(int $roomId, int $customerId, string $to): void
    {
        if (!in_array($to, $this->ALLOWED, true)) {
            $to = 'disarmed';
        }

        $upd = $this->pdo->prepare(
            'UPDATE room_alarm
                SET state = ?, updated_at = NOW()
              WHERE customer_id = ? AND room_id = ?'
        );
        $upd->execute([$to, (int)$customerId, (int)$roomId]);

        if ($upd->rowCount() === 0) {
            $ins = $this->pdo->prepare(
                'INSERT INTO room_alarm (customer_id, room_id, state) VALUES (?, ?, ?)'
            );
            $ins->execute([(int)$customerId, (int)$roomId, $to]);
        }
    }
}
