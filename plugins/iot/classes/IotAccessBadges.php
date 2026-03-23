<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa

class IotAccessBadges extends IotBaseRepository
{
    public function findByCode(string $code): ?IotAccessBadge {
        $st=$this->pdo->prepare('SELECT * FROM access_badge WHERE badge_code=? LIMIT 1');
        $st->execute([$code]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        return $row? new IotAccessBadge($row) : null;
    }

    /** Ritorna il badge ATTIVO per un customer (se esiste) */
    public function findActiveByCustomer(int $customerId): ?IotAccessBadge {
        $st=$this->pdo->prepare('SELECT * FROM access_badge WHERE customer_id=? AND status="active" ORDER BY id DESC LIMIT 1');
        $st->execute([$customerId]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        return $row? new IotAccessBadge($row) : null;
    }

    public function create(IotAccessBadge $b): int {
        $err=$b->validate(); if ($err) throw new InvalidArgumentException(implode('; ',$err));
        $a=$b->toDb();
        $sql='INSERT INTO access_badge(customer_id,badge_code,pin_hash,status,last_used)
              VALUES (:customer_id,:badge_code,:pin_hash,:status,:last_used)';
        $this->pdo->prepare($sql)->execute($a);
        return (int)$this->pdo->lastInsertId();
    }

    /** Crea o aggiorna il badge attivo per il customer; gestisce univocità del badge_code */
    public function upsertForCustomer(int $customerId, string $badgeCode, ?string $pinPlain): int {
        $badgeCode = trim($badgeCode);
        if ($badgeCode==='') throw new InvalidArgumentException('badge_code obbligatorio');

        // univocità codice
        $st=$this->pdo->prepare('SELECT id, customer_id FROM access_badge WHERE badge_code=? LIMIT 1');
        $st->execute([$badgeCode]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        if ($row && (int)$row['customer_id'] !== $customerId) {
            throw new InvalidArgumentException('Questo badge è già associato ad un altro utente.');
        }

        $curr = $this->findActiveByCustomer($customerId);
        if ($curr) {
            $params = ['badge_code'=>$badgeCode, 'id'=>$curr->getId()];
            $sql = 'UPDATE access_badge SET badge_code=:badge_code';
            if ($pinPlain !== null && $pinPlain !== '') {
                $params['pin_hash'] = password_hash($pinPlain, PASSWORD_DEFAULT);
                $sql .= ', pin_hash=:pin_hash';
            }
            $sql .= ' WHERE id=:id';
            $this->pdo->prepare($sql)->execute($params);
            return (int)$curr->getId();
        } else {
            $pinHash = ($pinPlain!==null && $pinPlain!=='') ? password_hash($pinPlain, PASSWORD_DEFAULT) : '';
            if ($pinHash==='') throw new InvalidArgumentException('PIN obbligatorio per nuovo badge.');
            $this->pdo->prepare(
                'INSERT INTO access_badge (customer_id,badge_code,pin_hash,status,last_used) VALUES (?,?,?,?,NULL)'
            )->execute([$customerId,$badgeCode,$pinHash,'active']);
            return (int)$this->pdo->lastInsertId();
        }
    }

    /** Revoca l’eventuale badge attivo del customer */
    public function revokeForCustomer(int $customerId): void {
        $this->pdo->prepare('UPDATE access_badge SET status="revoked" WHERE customer_id=? AND status="active"')
                  ->execute([$customerId]);
    }

    public function revoke(int $id): void {
        $this->pdo->prepare('UPDATE access_badge SET status="revoked" WHERE id=?')->execute([$id]);
    }

    public function touchUse(int $id): void {
        $this->pdo->prepare('UPDATE access_badge SET last_used=CURRENT_TIMESTAMP WHERE id=?')->execute([$id]);
    }


}
