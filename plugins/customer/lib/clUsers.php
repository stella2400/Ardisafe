<?php
declare(strict_types=1);

/**
 * CLUsers — Repository/manager per la tabella `customer`
 * - CRUD (create, findById, findByEmail, update, delete)
 * - listAll (con ricerca e paginazione semplice)
 * - renderTable() che usa CLTable + CLButton per la UI
 */
class CLUsers
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? self::getPDO();
    }

    /** Connessione PDO (fallback compatibile con progetto) */
    public static function getPDO(): PDO
    {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) return $GLOBALS['pdo'];
        if (function_exists('db')) { $maybe = db(); if ($maybe instanceof PDO) return $maybe; }
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $name = defined('DB_NAME') ? DB_NAME : 'ardisafe';
        $user = defined('DB_USER') ? DB_USER : 'root';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $dsn  = defined('DB_DSN')  ? DB_DSN  : "mysql:host={$host};dbname={$name};charset=utf8mb4";
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /* ====== Query ====== */

    public function findById(int $id): ?CLUser
    {
        $st = $this->pdo->prepare('SELECT * FROM customer WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ? CLUser::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?CLUser
    {
        $st = $this->pdo->prepare('SELECT * FROM customer WHERE email = ? LIMIT 1');
        $st->execute([strtolower(trim($email))]);
        $row = $st->fetch();
        return $row ? CLUser::fromRow($row) : null;
    }

    /** @return array{data:CLUser[], total:int} */
    public function listAll(string $q = '', int $limit = 100, int $offset = 0, string $order = 'id ASC'): array
    {
        $params = [];
        $sql = 'FROM customer';
        if ($q !== '') {
            $sql .= ' WHERE (nome LIKE :q OR cognome LIKE :q OR email LIKE :q)';
            $params[':q'] = '%'.$q.'%';
        }

        $st = $this->pdo->prepare('SELECT COUNT(*) '.$sql);
        $st->execute($params);
        $total = (int)$st->fetchColumn();

        $st = $this->pdo->prepare('SELECT * '.$sql.' ORDER BY '.$order.' LIMIT :lim OFFSET :off');
        foreach ($params as $k=>$v) $st->bindValue($k, $v, PDO::PARAM_STR);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();

        $rows = $st->fetchAll();
        $data = array_map(static fn($r)=>CLUser::fromRow($r), $rows);

        return ['data'=>$data, 'total'=>$total];
    }

    /** Inserisce e ritorna l’ID */
    public function create(CLUser $u): int
    {
        $errors = $u->validate(true);
        if ($errors) throw new InvalidArgumentException('Dati non validi: '.implode(' ', $errors));

        $sql = 'INSERT INTO customer (nome, cognome, data_nascita, ruolo, email, password, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())';
        $row = $u->toDbArray();
        $st  = $this->pdo->prepare($sql);
        $st->execute([$row['nome'], $row['cognome'], $row['data_nascita'], $row['ruolo'], $row['email'], $row['password']]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Aggiorna i campi principali (se passwordHash è non vuoto, aggiorna anche la password) */
    public function update(CLUser $u): bool
    {
        if ($u->getId() === null) throw new InvalidArgumentException('ID mancante per update.');
        $errors = $u->validate(false);
        if ($errors) throw new InvalidArgumentException('Dati non validi: '.implode(' ', $errors));

        $row = $u->toDbArray();

        if ($row['password'] !== '') {
            $sql = 'UPDATE customer SET nome=?, cognome=?, data_nascita=?, ruolo=?, email=?, password=?, updated_at=NOW() WHERE id=?';
            $params = [$row['nome'],$row['cognome'],$row['data_nascita'],$row['ruolo'],$row['email'],$row['password'],$row['id']];
        } else {
            $sql = 'UPDATE customer SET nome=?, cognome=?, data_nascita=?, ruolo=?, email=?, updated_at=NOW() WHERE id=?';
            $params = [$row['nome'],$row['cognome'],$row['data_nascita'],$row['ruolo'],$row['email'],$row['id']];
        }
        $st = $this->pdo->prepare($sql);
        return $st->execute($params);
    }

    public function delete(int $id): bool
    {
        $st = $this->pdo->prepare('DELETE FROM customer WHERE id=?');
        return $st->execute([$id]);
    }

    public function count(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM customer')->fetchColumn();
    }

    /* ====== UI Helpers ====== */

    /**
     * Rende la tabella utenti pronta da inserire in pagina.
     * Usa CLTable + CLButton, con badge ruolo e pulsanti azione.
     */
    public function renderTable(string $q = '', int $limit = 500, int $offset = 0): string
    {
        $res   = $this->listAll($q, $limit, $offset, 'id ASC'); // ordinamento come richiesto
        $users = $res['data'];

        $t = (new CLTable())
            ->start()
            ->theme('boxed')
            ->rawCols([0, 4]); // 0 = Action, 4 = Ruolo (non escapati)

        $t->header(['Action','Nome','Cognome','Data di nascita','Ruolo','Email','Creato','Aggiornato']);

        foreach ($users as $u) {
            /** @var CLUser $u */
            $id    = (int)$u->getId();
            $nome  = htmlspecialchars($u->getNome(),    ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
            $cogn  = htmlspecialchars($u->getCognome(), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
            $dob   = $u->getDataNascita() ? date('d/m/Y', strtotime($u->getDataNascita())) : '';
            $ruolo = htmlspecialchars($u->getRuolo(),   ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
            $email = htmlspecialchars($u->getEmail(),   ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
            $creat = $u->getCreatedAt() ? date('d/m/Y H:i', strtotime($u->getCreatedAt())) : '';
            $updat = $u->getUpdatedAt() ? date('d/m/Y H:i', strtotime($u->getUpdatedAt())) : '';

            $badge = '<span class="role role--'.($ruolo === 'superuser' ? 'super' : 'op').'">'.$ruolo.'</span>';

            $actions = (new CLButton())
                ->startGroup(['merge'=>true, 'class'=>'table-actions'])
                    ->link('🗂️', "/Ardisafe2.0/user_view.php?id={$id}",  ['variant'=>'secondary','attrs'=>['title'=>'Scheda tecnica','aria-label'=>'Scheda']])
                    ->link('✏️',  "/Ardisafe2.0/user_edit.php?id={$id}", ['variant'=>'secondary','attrs'=>['title'=>'Modifica','aria-label'=>'Modifica']])
                ->render();

            $t->row([
                $actions,            // col 0 (raw)
                $nome,
                $cogn,
                $dob,
                $badge,              // col 4 (raw)
                $email,
                $creat,
                $updat
            ], ['data-id' => (string)$id]);
        }

        // piccoli stili locali
        $css = '<style>
        .role{display:inline-block;padding:.2rem .5rem;border-radius:999px;font-size:.75rem;font-weight:700}
        .role--super{background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe}
        .role--op{background:#ecfeff;color:#155e75;border:1px solid #a5f3fc}
        .table-actions{white-space:nowrap}
        </style>';

        return $css.$t->render();
    }

}
