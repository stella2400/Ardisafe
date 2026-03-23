<?php
declare(strict_types=1);

/**
 * Repository stanze.
 * Dipende da un PDO esposto da IotBaseRepository (già presente nel tuo progetto).
 */
class IotRooms extends IotBaseRepository
{
    public function __construct(?PDO $pdo = null)
    {
        parent::__construct($pdo); // usa il costruttore del base che inizializza $this->pdo
    }

    /** @return IotRoom[] */
    public function listByCustomer(
        int $customerId,
        string $q = '',
        int $limit = 500,
        int $offset = 0,
        string $orderBy = 'name ASC'
    ): array {
        // Sanitize numerici per uso diretto in SQL (niente param binding su LIMIT/OFFSET)
        $limit  = max(1, (int)$limit);
        $offset = max(0, (int)$offset);

        // Whitelist semplice per ORDER BY
        $orderWhitelist = '(name|floor_label|created_at|updated_at|id)';
        if (!preg_match('/^' . $orderWhitelist . '\s+(ASC|DESC)$/i', $orderBy)) {
            $orderBy = 'name ASC';
        }

        $sql = 'SELECT * FROM room WHERE customer_id = ?';
        $params = [$customerId];

        if ($q !== '') {
            $sql .= ' AND (name LIKE ? OR floor_label LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }

        // Inseriamo LIMIT/OFFSET come letterali numerici
        $sql .= " ORDER BY {$orderBy} LIMIT {$limit} OFFSET {$offset}";

        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        $out = [];
        while ($row = $st->fetch()) {
            $out[] = IotRoom::fromRow($row);
        }
        return $out;
    }


    public function findById(int $id): ?IotRoom
    {
        $st = $this->pdo->prepare('SELECT * FROM room WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ? IotRoom::fromRow($row) : null;
    }

    /** Crea e ritorna l’id */
    public function create(IotRoom $room): int
    {
        $errors = $room->validate();
        if ($errors) {
            throw new InvalidArgumentException('Validazione stanza fallita: '.implode(' | ', $errors));
        }

        $st = $this->pdo->prepare(
            'INSERT INTO room (customer_id, name, floor_label, image, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $st->execute([
            $room->getCustomerId(),
            $room->getName(),
            $room->getFloorLabel(),
            $room->getImage(),
        ]);

        $id = (int)$this->pdo->lastInsertId();
        $room->setId($id);
        return $id;
    }

    /** Aggiorna; true se ha toccato righe */
    public function update(IotRoom $room): bool
    {
        if ($room->getId() === null) {
            throw new InvalidArgumentException('ID stanza mancante per update.');
        }
        $errors = $room->validate();
        if ($errors) {
            throw new InvalidArgumentException('Validazione stanza fallita: '.implode(' | ', $errors));
        }

        $st = $this->pdo->prepare(
            'UPDATE room
               SET name = ?, floor_label = ?, image = ?, updated_at = NOW()
             WHERE id = ? AND customer_id = ?'
        );
        $st->execute([
            $room->getName(),
            $room->getFloorLabel(),
            $room->getImage(),
            $room->getId(),
            $room->getCustomerId(),
        ]);

        return $st->rowCount() > 0;
    }

    public function delete(int $id, int $customerId): bool
    {
        $st = $this->pdo->prepare('DELETE FROM room WHERE id = ? AND customer_id = ?');
        $st->execute([$id, $customerId]);
        return $st->rowCount() > 0;
    }
}
