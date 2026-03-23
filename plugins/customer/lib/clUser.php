<?php
declare(strict_types=1);

/**
 * CLUser — Entity + UI per la tabella `customer`
 * Campi DB: id, nome, cognome, data_nascita (YYYY-MM-DD), ruolo, email, password(hash), created_at, updated_at
 *
 * Dipendenze UI: CLCard, CLGrid, CLTable, CLForm, CLButton
 */
class CLUser
{
    private ?int $id = null;
    private string $nome = '';
    private string $cognome = '';
    private ?string $dataNascita = null;   // formato ISO: YYYY-MM-DD
    private string $ruolo = 'operator';    // 'operator' | 'superuser'
    private string $email = '';
    private string $passwordHash = '';     // sempre hash
    private ?string $createdAt = null;
    private ?string $updatedAt = null;

    public function __construct(array $data = [])
    {
        if ($data) $this->assegnaDati($data);
    }

    /** Popola i campi da un array (row DB o POST). Ignora chiavi sconosciute. */
    public function assegnaDati(array $a): void
    {
        if (isset($a['id']))             $this->id = (int)$a['id'];
        if (isset($a['nome']))           $this->nome = (string)$a['nome'];
        if (isset($a['cognome']))        $this->cognome = (string)$a['cognome'];

        if (isset($a['data_nascita']))   $this->setDataNascita((string)$a['data_nascita']);
        if (isset($a['dataNascita']))    $this->setDataNascita((string)$a['dataNascita']); // alias

        if (isset($a['ruolo']))          $this->ruolo = (string)$a['ruolo'];
        if (isset($a['email']))          $this->email = strtolower(trim((string)$a['email']));

        if (isset($a['password_plain'])) {
            $this->setPassword((string)$a['password_plain']);
        } elseif (isset($a['password'])) {
            $p = (string)$a['password'];
            if (strlen($p) >= 55 && preg_match('/^\$2y\$|\$argon2id\$/', $p)) $this->passwordHash = $p;
            else $this->setPassword($p);
        } elseif (isset($a['password_hash'])) {
            $this->passwordHash = (string)$a['password_hash'];
        }

        if (isset($a['created_at']))     $this->createdAt = (string)$a['created_at'];
        if (isset($a['updated_at']))     $this->updatedAt = (string)$a['updated_at'];
    }

    /* ==== Getter / Setter ==== */
    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): self { $this->id = $id; return $this; }

    public function getNome(): string { return $this->nome; }
    public function setNome(string $v): self { $this->nome = $v; return $this; }

    public function getCognome(): string { return $this->cognome; }
    public function setCognome(string $v): self { $this->cognome = $v; return $this; }

    public function getDataNascita(): ?string { return $this->dataNascita; }
    /** Accetta YYYY-MM-DD o DD/MM/YYYY, salva sempre YYYY-MM-DD */
    public function setDataNascita(?string $v): self {
        if ($v === null || $v === '') { $this->dataNascita = null; return $this; }
        $n = self::normalizeDate($v);
        if ($n !== null) $this->dataNascita = $n;
        return $this;
    }

    public function getRuolo(): string { return $this->ruolo; }
    public function setRuolo(string $v): self { $this->ruolo = $v; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $v): self { $this->email = strtolower(trim($v)); return $this; }

    public function getPasswordHash(): string { return $this->passwordHash; }
    public function setPassword(string $plain): self { $this->passwordHash = password_hash($plain, PASSWORD_DEFAULT); return $this; }
    public function verifyPassword(string $plain): bool { return $this->passwordHash !== '' && password_verify($plain, $this->passwordHash); }

    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }

    /* ==== Utility ==== */
    public function fullName(): string { return trim($this->nome . ' ' . $this->cognome); }

    public function age(): ?int {
        if (!$this->dataNascita) return null;
        $b = DateTime::createFromFormat('Y-m-d', $this->dataNascita);
        if (!$b) return null;
        return (int)$b->diff(new DateTime('today'))->y;
    }

    public function validate(bool $onCreate = false): array
    {
        $err = [];
        if (mb_strlen($this->nome) < 2) $err[] = 'Nome troppo corto.';
        if (mb_strlen($this->cognome) < 2) $err[] = 'Cognome troppo corto.';
        if (!in_array($this->ruolo, ['operator','superuser'], true)) $err[] = 'Ruolo non valido.';
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) $err[] = 'Email non valida.';
        if ($this->dataNascita === null) $err[] = 'Data di nascita non valida.';
        if ($onCreate && $this->passwordHash === '') $err[] = 'Password obbligatoria.';
        return $err;
    }

    public function toDbArray(): array
    {
        return [
            'id'            => $this->id,
            'nome'          => $this->nome,
            'cognome'       => $this->cognome,
            'data_nascita'  => $this->dataNascita,
            'ruolo'         => $this->ruolo,
            'email'         => $this->email,
            'password'      => $this->passwordHash,
            'created_at'    => $this->createdAt,
            'updated_at'    => $this->updatedAt,
        ];
    }

    public static function fromRow(array $row): self { return new self($row); }

    private static function normalizeDate(string $s): ?string
    {
        $s = trim($s);
        if ($s === '') return null;
        $dt = DateTime::createFromFormat('Y-m-d', $s);
        if ($dt && $dt->format('Y-m-d') === $s) return $s;
        $dt = DateTime::createFromFormat('d/m/Y', $s);
        if ($dt) return $dt->format('Y-m-d');
        return null;
    }

    /* ============================================================
     * ======================  UI: VIEW  ===========================
     * ============================================================ */

    /**
     * Render scheda tecnica (HTML di contenuto).
     * @param array $opts backUrl, editUrl, canEdit(bool), wrapMax(px), showEmailButton(bool)
     */
   public function renderView(array $opts = []): string
{
    $backUrl   = $opts['backUrl']   ?? ($_SERVER['HTTP_REFERER'] ?? '/Ardisafe2.0/users.php');
    $canEdit   = (bool)($opts['canEdit'] ?? false);
    $showEmail = (bool)($opts['showEmailButton'] ?? true);
    // Card opzionale da mostrare nella colonna destra della 2ª riga (es. Badge & PIN)
    $rightAsideHtml = $opts['rightAsideHtml'] ?? '';

    // ===== Header (avatar + nome + ruolo + azioni) =====
    $initials  = strtoupper(mb_substr(($this->getNome()[0] ?? '?'),0,1) . mb_substr(($this->getCognome()[0] ?? ''),0,1));
    $rolePill  = '<span class="role role--'.($this->getRuolo()==='superuser'?'super':'op').'">'
               . htmlspecialchars($this->getRuolo(),ENT_QUOTES,'UTF-8').'</span>';

    $btns = (new CLButton())->startGroup(['merge'=>true])
        ->link('↩︎ Indietro', $backUrl, ['variant'=>'ghost'])
        ->link('✏️ Modifica', '/Ardisafe2.0/user_edit.php?id='.$this->getId(), ['variant'=>'secondary'])
        ->link('✉️ Email', 'mailto:'.rawurlencode($this->getEmail()), ['variant'=>'secondary'])
        ->render();

    $headerBody = '
      <div style="display:flex;gap:16px;align-items:center">
        <div style="width:64px;height:64px;border-radius:50%;background:#111827;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800">'
        . htmlspecialchars($initials,ENT_QUOTES,'UTF-8') . '</div>
        <div style="min-width:0">
          <div style="font-size:20px;font-weight:800">' . htmlspecialchars($this->getNome().' '.$this->getCognome(),ENT_QUOTES,'UTF-8') . '</div>
          <div style="margin:4px 0">' . $rolePill . '</div>
          <div><a href="mailto:' . htmlspecialchars($this->getEmail(),ENT_QUOTES,'UTF-8') . '">'
            . htmlspecialchars($this->getEmail(),ENT_QUOTES,'UTF-8') . '</a></div>
        </div>
      </div>
      <div style="margin-top:12px">'.$btns.'</div>
    ';

    $cardHeader = (new CLCard())->start()
        ->header('Scheda utente','Dettagli tecnici')
        ->body($headerBody, true);

    // ===== Dati account (colonna destra 1ª riga) =====
    $tAcc = (new CLTable())->start()->theme('boxed');
    // $tAcc->header(['Campo','Valore']);
    $tAcc->row(['ID', (string)$this->getId()]);
    $tAcc->row(['Creato',    $this->getCreatedAt()? date('d/m/Y H:i', strtotime($this->getCreatedAt())) : '-']);
    $tAcc->row(['Aggiornato',$this->getUpdatedAt()? date('d/m/Y H:i', strtotime($this->getUpdatedAt())) : '-']);
    $cardAccount = (new CLCard())->start()->header('Dati account')->body($tAcc->render(), true);

    // ===== Dati anagrafici (colonna sinistra 2ª riga) =====
    $tLeft = (new CLTable())->start()->theme('boxed');
    // $tLeft->header(['Campo','Valore']);
    $tLeft->row(['Nome',     htmlspecialchars($this->getNome(),ENT_QUOTES,'UTF-8')]);
    $tLeft->row(['Cognome',  htmlspecialchars($this->getCognome(),ENT_QUOTES,'UTF-8')]);
    $tLeft->row(['Data di nascita', $this->getDataNascita()? date('d/m/Y', strtotime($this->getDataNascita())).' ('.self::age($this->getDataNascita()).' anni)' : '—']);

    // FIX: Ruolo ed Email come HTML "raw"
    $tLeft->row([
        'Ruolo',
        ['text'=>'<span class="role role--'.($this->getRuolo()==='superuser'?'super':'op').'">'.
                 htmlspecialchars($this->getRuolo(),ENT_QUOTES,'UTF-8').'</span>',
         'raw'=>true]
    ]);
    $tLeft->row([
        'Email',
        ['text'=>'<a href="mailto:'.htmlspecialchars($this->getEmail(),ENT_QUOTES,'UTF-8').'">'.
                 htmlspecialchars($this->getEmail(),ENT_QUOTES,'UTF-8').'</a>',
         'raw'=>true]
    ]);

    $cardLeft = (new CLCard())->start()->header('Dati anagrafici')->body($tLeft->render(), true);

    // ===== CSS pill ruolo + spaziatura =====
    $css = '<style>
      .role{display:inline-block;padding:.25rem .6rem;border-radius:999px;font-size:.75rem;font-weight:700;background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe}
      .role--op{background:#ecfeff;color:#155e75;border-color:#a5f3fc}
    </style>';

    // SPACER tra le due righe (distacco visibile)
    $spacer = '<div style="height:14px"></div>';

    // ===== GRID: 2 righe, 8/4 colonne =====
    $gTop = (new CLGrid())->start()->container('xl')
        ->row(['g'=>20,'align'=>'start'])
          ->colRaw(12, ['lg'=>8], $cardHeader->render())
          ->colRaw(12, ['lg'=>4], $cardAccount->render())
        ->endRow()
        ->render();

    $gBottom = (new CLGrid())->start()->container('xl')
        ->row(['g'=>20,'align'=>'start'])
          ->colRaw(12, ['lg'=>8], $cardLeft->render())
          ->colRaw(12, ['lg'=>4], (string)$rightAsideHtml)
        ->endRow()
        ->render();

    return $css . $gTop . $spacer . $gBottom;
}



    /* ============================================================
     * ======================  UI: EDIT  ===========================
     * ============================================================ */

    /** Restituisce l’URL precedente (stesso host), altrimenti fallback a history.back() */
    private static function previousUrl(?string $fallback = null): string
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref !== '') {
            $refParts = parse_url($ref);
            $hostNow  = $_SERVER['HTTP_HOST'] ?? '';
            // usa solo se è lo stesso host (evita open redirect)
            if (!empty($refParts['host']) && $refParts['host'] === $hostNow) {
                $path  = $refParts['path']  ?? '/';
                $query = isset($refParts['query']) ? ('?'.$refParts['query']) : '';
                return $path.$query;
            }
        }
        // fallback: JS history back
        return $fallback ?? 'javascript:history.back()';
    }

    /**
     * Valida la POST di edit, aggiorna l'entità e salva via repo.
     * @return array{ok:bool,errors:array,openPwd:bool,old:array,redirect:?string}
     */
    public static function processEditPost(CLUsers $repo, CLUser $user, array $post, string $sessionCsrf, bool $isSuper): array
    {
        $errors = [];
        $old = [
            'nome'         => trim((string)($post['nome'] ?? '')),
            'cognome'      => trim((string)($post['cognome'] ?? '')),
            'data_nascita' => (string)($post['data_nascita'] ?? ''),
            'ruolo'        => $isSuper ? (string)($post['ruolo'] ?? $user->getRuolo()) : $user->getRuolo(),
            'email'        => strtolower(trim((string)($post['email'] ?? ''))),
        ];

        // CSRF
        if (empty($post['csrf']) || !hash_equals($sessionCsrf, (string)$post['csrf'])) {
            return ['ok'=>false,'errors'=>['Sessione scaduta o token non valido.'],'openPwd'=>false,'old'=>$old,'redirect'=>null];
        }

        // Password
        $openPwd     = (isset($post['change_pwd']) && $post['change_pwd'] === '1');
        $oldPwdInput = (string)($post['old_password'] ?? '');
        $newPwd      = (string)($post['password'] ?? '');
        $newPwd2     = (string)($post['password2'] ?? '');

        // Validazioni base
        if (mb_strlen($old['nome']) < 2)     $errors[] = 'Nome troppo corto.';
        if (mb_strlen($old['cognome']) < 2)  $errors[] = 'Cognome troppo corto.';
        if ($old['data_nascita'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$|^\d{2}\/\d{2}\/\d{4}$/', $old['data_nascita'])) {
            $errors[] = 'Data di nascita non valida.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email non valida.';
        if ($isSuper && !in_array($old['ruolo'], ['operator','superuser'], true)) $errors[] = 'Ruolo non valido.';

        // Unicità email
        if (!$errors) {
            $other = $repo->findByEmail($old['email']);
            if ($other && $other->getId() !== $user->getId()) {
                $errors[] = 'Esiste già un account con questa email.';
            }
        }

        // Password se richiesta
        if ($openPwd) {
            if ($oldPwdInput === '') $errors[] = 'Inserisci la password attuale.';
            if ($newPwd === '' || $newPwd2 === '') $errors[] = 'Inserisci e conferma la nuova password.';
            if ($newPwd !== $newPwd2) $errors[] = 'Le nuove password non coincidono.';
            if (strlen($newPwd) < 8 || !preg_match('/[A-Za-z]/', $newPwd) || !preg_match('/[0-9]/', $newPwd)) {
                $errors[] = 'La nuova password deve avere almeno 8 caratteri e contenere lettere e numeri.';
            }
            if (!$errors && !$user->verifyPassword($oldPwdInput)) {
                $errors[] = 'La password attuale non è corretta.';
            }
        }

        if ($errors) {
            return ['ok'=>false,'errors'=>$errors,'openPwd'=>$openPwd,'old'=>$old,'redirect'=>null];
        }

        // Applica e salva
        $user->setNome($old['nome'])
             ->setCognome($old['cognome'])
             ->setDataNascita($old['data_nascita'])
             ->setEmail($old['email']);
        if ($isSuper) $user->setRuolo($old['ruolo']);
        if ($openPwd) $user->setPassword($newPwd);

        $repo->update($user);

        return ['ok'=>true,'errors'=>[],'openPwd'=>false,'old'=>$old,'redirect'=>'/Ardisafe2.0/user_view.php?id='.$user->getId().'&updated=1'];
    }

    /**
     * Render del form di modifica (contenuto completo con stile e JS).
     * @param array $opts actionUrl, csrf, isSuper(bool), old(array), errors(array), openPwd(bool)
     */
    public function renderEdit(array $opts): string
    {
        $actionUrl = $opts['actionUrl'] ?? ('/Ardisafe2.0/user_edit.php?id='.$this->id);
        $csrf      = (string)($opts['csrf'] ?? '');
        $isSuper   = (bool)($opts['isSuper'] ?? false);
        $old       = $opts['old'] ?? [
            'nome'=>$this->nome,'cognome'=>$this->cognome,'data_nascita'=>$this->dataNascita,'ruolo'=>$this->ruolo,'email'=>$this->email
        ];
        $errors    = $opts['errors'] ?? [];
        $openPwd   = (bool)($opts['openPwd'] ?? false);

        // stile inline
        $style = '<style>
:root{--app-wrap-max: 900px}
.role{display:inline-block;padding:.2rem .5rem;border-radius:999px;font-size:.75rem;font-weight:700}
.role--super{background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe}
.role--op{background:#ecfeff;color:#155e75;border:1px solid #a5f3fc}
.alert{padding:10px 12px;border-radius:10px;font-size:14px;margin-bottom:10px}
.alert-danger{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
.form-note{font-size:12px;color:#6b7280;margin-top:10px}
.meta-name{font-size:24px;font-weight:800;line-height:1.2;margin:4px 0 10px}
.pwd-toggle{margin:0}
</style>';

        $fullName = htmlspecialchars($this->fullName(), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');

        // messaggi
        $msgHtml = '';
        if (!empty($errors)) {
            $msgHtml .= '<div class="alert alert-danger"><strong>Controlla i dati:</strong><ul style="margin:.4rem 0 0 .9rem;">';
            foreach ($errors as $e) $msgHtml .= '<li>'.htmlspecialchars($e, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</li>';
            $msgHtml .= '</ul></div>';
        }

        // 3 bottoni in fila
        $actionsTop = (new CLButton())
            ->startGroup(['merge'=>true])
              ->link('↩︎ Indietro', '/Ardisafe2.0/users.php', ['variant'=>'ghost'])
              ->link('👁️ Vedi scheda', '/Ardisafe2.0/user_view.php?id='.$this->id, ['variant'=>'ghost'])
              ->button('🔑 Modifica password', ['variant'=>'secondary','attrs'=>['type'=>'button','id'=>'btn-toggle-pwd','class'=>'pwd-toggle']])
            ->render();

        // form
        $form = new CLForm();
        $form->start($actionUrl, 'POST', ['id'=>'user-edit']);
        $form->csrf('csrf', $csrf);

        // Nome grande
        $metaHtml = '<div class="meta-name">'.$fullName.'</div>';

        // Ruolo
        $roleOpts = ['operator'=>'Operatore','superuser'=>'Superuser'];
        if ($isSuper) {
            $form->select('ruolo','Ruolo', $roleOpts, ['value'=>$old['ruolo'] ?? 'operator', 'required'=>true]);
        } else {
            $form->select('ruolo','Ruolo', $roleOpts, ['value'=>$old['ruolo'] ?? 'operator', 'required'=>true, 'attrs'=>['disabled'=>true]]);
            $form->hidden('ruolo', $old['ruolo'] ?? 'operator');
        }

        // Anagrafica
        $form->text('nome','Nome', ['required'=>true,'value'=>$old['nome'] ?? '','placeholder'=>'Mario']);
        $form->text('cognome','Cognome', ['required'=>true,'value'=>$old['cognome'] ?? '','placeholder'=>'Rossi']);

        if (method_exists($form, 'date')) {
            $form->date('data_nascita','Data di nascita', ['required'=>true,'value'=>$old['data_nascita'] ?? '','max'=>date('Y-m-d')]);
        } else {
            $form->text('data_nascita','Data di nascita', ['required'=>true,'value'=>$old['data_nascita'] ?? '','attrs'=>['type'=>'date','max'=>date('Y-m-d')] ]);
        }

        $form->email('email','Email', ['required'=>true,'value'=>$old['email'] ?? '','placeholder'=>'nome.cognome@azienda.it']);

        // blocco password
        $form->hidden('change_pwd', $openPwd ? '1' : '0');
        $form->password('old_password','Password attuale', ['placeholder'=>'Inserisci la password attuale','attrs'=>['autocomplete'=>'current-password','data-pwd'=>'1']]);
        $form->password('password','Nuova password', ['placeholder'=>'Min. 8 caratteri, lettere e numeri','attrs'=>['autocomplete'=>'new-password','data-pwd'=>'1']]);
        $form->password('password2','Conferma nuova password', ['placeholder'=>'Ripeti la nuova password','attrs'=>['autocomplete'=>'new-password','data-pwd'=>'1']]);

        $form->submit('Salva modifiche', ['variant'=>'primary','full'=>true]);

        // toggle iniziale corretto
        $isOpenJs = $openPwd ? 'true' : 'false';
        $js = "<script>
(function(){
  var btn = document.getElementById('btn-toggle-pwd');
  var form = document.getElementById('user-edit');
  if (!btn || !form) return;
  var isOpen = {$isOpenJs};

  function pwdGroups(){
    var ins = form.querySelectorAll('[data-pwd=\"1\"]');
    var groups = [];
    ins.forEach(function(el){
      var g = el.closest('.clform__group') || el.closest('.clform-group') || el.parentElement;
      if (g && !groups.includes(g)) groups.push(g);
    });
    return groups;
  }

  function setOpen(open){
    isOpen = open;
    pwdGroups().forEach(function(g){ g.style.display = open ? '' : 'none'; });
    var hid = form.querySelector('input[name=\"change_pwd\"]');
    if (hid) hid.value = open ? '1' : '0';
    btn.textContent = open ? '🔒 Annulla modifica password' : '🔑 Modifica password';
  }

  btn.addEventListener('click', function(e){ e.preventDefault(); setOpen(!isOpen); });
  setOpen(isOpen);
})();
</script>";

        $card = (new CLCard())->start()
            ->header('Modifica utente','Dettagli profilo')
            ->body($msgHtml.$metaHtml.$actionsTop.$form->render().'<p class="form-note">Nota: se non apri “Modifica password”, la password non verrà toccata.</p>'.$js, true);

        $grid = (new CLGrid())->start()->container('xl')->row()->colRaw(12, [], $card->render())->endRow()->render();

        return $style.$grid;
    }
}
