<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/autoloader.php';

/**
 * Ardisafe2.0/register.php — Inserimento diretto su tabella `customer`
 * Campi: nome, cognome, data_nascita (DATE), ruolo (superuser|operator), email, password(hash)
 */

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Se già autenticato → home
if (function_exists('is_authenticated') && is_authenticated()) {
  header('Location: /Ardisafe2.0/homepage.php'); exit;
}

/** Ritorna un PDO (usa config esistente se presente) */
function getPDO(): PDO {
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

/** Normalizza date: accetta AAAA-MM-GG o GG/MM/AAAA → AAAA-MM-GG */
function normalize_date(string $s): ?string {
  $s = trim($s);
  if ($s === '') return null;
  // formato HTML5 date (YYYY-MM-DD)
  $dt = DateTime::createFromFormat('Y-m-d', $s);
  if ($dt && $dt->format('Y-m-d') === $s) return $s;
  // formato italiano (DD/MM/YYYY)
  $dt = DateTime::createFromFormat('d/m/Y', $s);
  if ($dt) return $dt->format('Y-m-d');
  return null;
}

// CSRF token
if (empty($_SESSION['csrf'])) {
  try { $_SESSION['csrf'] = bin2hex(random_bytes(32)); } catch (Throwable $e) { $_SESSION['csrf'] = sha1(uniqid('', true)); }
}
$csrf = $_SESSION['csrf'];

$errors = [];
$old = [
  'nome'         => '',
  'cognome'      => '',
  'data_nascita' => '',
  'ruolo'        => 'operator',
  'email'        => '',
  'terms'        => '',
];

// POST: registrazione diretta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF
  if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
    $errors[] = 'Sessione scaduta o token non valido. Ricarica la pagina.';
  }

  // Valori
  $old['nome']         = trim((string)($_POST['nome'] ?? ''));
  $old['cognome']      = trim((string)($_POST['cognome'] ?? ''));
  $old['data_nascita'] = trim((string)($_POST['data_nascita'] ?? ''));
  $old['ruolo']        = trim((string)($_POST['ruolo'] ?? 'operator'));
  $old['email']        = trim((string)($_POST['email'] ?? ''));
  $pwd                 = (string)($_POST['password'] ?? '');
  $pwd2                = (string)($_POST['password2'] ?? '');
  $old['terms']        = isset($_POST['terms']) ? '1' : '';

  // Validazione
  if (mb_strlen($old['nome']) < 2)    $errors[] = 'Inserisci il nome.';
  if (mb_strlen($old['cognome']) < 2) $errors[] = 'Inserisci il cognome.';

  $dateISO = normalize_date($old['data_nascita']);
  if ($dateISO === null) $errors[] = 'Data di nascita non valida.';

  $allowedRoles = ['superuser','operator'];
  if (!in_array($old['ruolo'], $allowedRoles, true)) $errors[] = 'Ruolo non valido.';

  if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email non valida.';
  if (strlen($pwd) < 8) $errors[] = 'La password deve avere almeno 8 caratteri.';
  if (!preg_match('/[A-Za-z]/', $pwd) || !preg_match('/[0-9]/', $pwd)) $errors[] = 'La password deve contenere lettere e numeri.';
  if ($pwd !== $pwd2) $errors[] = 'Le password non coincidono.';
  if ($old['terms'] !== '1') $errors[] = 'Devi accettare i termini per procedere.';

  if (empty($errors)) {
    try {
      $pdo = getPDO();

      // Unicità email
      $stmt = $pdo->prepare('SELECT 1 FROM customer WHERE email = ? LIMIT 1');
      $stmt->execute([strtolower($old['email'])]);
      if ($stmt->fetchColumn()) {
        $errors[] = 'Esiste già un account con questa email.';
      } else {
        // Inserimento
        $hash = password_hash($pwd, PASSWORD_DEFAULT);
        $sql  = 'INSERT INTO customer (nome, cognome, data_nascita, ruolo, email, password, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())';
        $ins  = $pdo->prepare($sql);
        $ins->execute([
          $old['nome'],
          $old['cognome'],
          $dateISO,
          $old['ruolo'],
          strtolower($old['email']),
          $hash
        ]);

        unset($_SESSION['csrf']);
        header('Location: /Ardisafe2.0/login.php?registered=1'); exit;
      }
    } catch (PDOException $e) {
      if ($e->getCode() === '23000') {
        $errors[] = 'Esiste già un account con questa email.';
      } else {
        $errors[] = 'Errore di sistema durante la creazione dell’account.';
      }
    } catch (Throwable $e) {
      $errors[] = 'Errore imprevisto. Riprova più tardi.';
    }
  }
}

// ==== UI (tabs + form) ====
// Tabs
$tabs = (new CLButton())
  ->startGroup(['merge'=>true])
    ->link  ('Login',  '/Ardisafe2.0/login.php', ['variant'=>'ghost'])
    ->button('Signup', ['variant'=>'primary', 'attrs'=>['aria-current'=>'page']])
  ->render();

$ruoloDefault = (!empty($old['ruolo'])) ? $old['ruolo'] : 'operator';
$birthVal = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['data_nascita'] ?? '') 
  ? $old['data_nascita'] 
  : (normalize_date($old['data_nascita'] ?? '') ?? ''));

// Form (NB: uso ->text anche per data/ruolo per massima compatibilità con la tua CLForm)
$form = (new CLForm())
  ->start('/Ardisafe2.0/register.php','POST',['id'=>'register-form'])
  ->csrf('csrf', $csrf)
  ->text('nome','Nome', [
      'required'=>true,
      'placeholder'=>'Mario',
      'value'=>$old['nome']
  ])
  ->text('cognome','Cognome', [
      'required'=>true,
      'placeholder'=>'Rossi',
      'value'=>$old['cognome']
  ])
  // Se la tua CLForm ha ->date(), usa ->date('data_nascita','Data di nascita', [...]) al posto di ->text
  ->date('data_nascita','Data di nascita', [
      'required' => true,
      'value'    => $birthVal,          // formato YYYY-MM-DD
      'min'      => '1900-01-01',
      'max'      => date('Y-m-d'),
  ])
  // Se la tua CLForm ha ->select(), puoi sostituire con ->select('ruolo','Ruolo', ['options'=>['operator'=>'Operatore','superuser'=>'Superuser'], 'value'=>$old['ruolo'], 'required'=>true])
  ->select(
      'ruolo',
      'Ruolo',
      [                 // 3º argomento = elenco opzioni (value => label)
        'operator'  => 'Operatore',
        'superuser' => 'Superuser',
      ],
      [                 // 4º argomento = attributi
        'required' => true,
        'value'    => $ruoloDefault,   // o 'selected' => $ruoloDefault se la tua CLForm usa quel nome
      ]
  )

  ->email('email','Email aziendale', [
      'required'=>true,
      'placeholder'=>'nome.cognome@azienda.it',
      'value'=>$old['email']
  ])
  ->password('password','Password', [
      'required'=>true,
      'placeholder'=>'Min. 8 caratteri, lettere e numeri'
  ])
  ->password('password2','Conferma password', [
      'required'=>true,
      'placeholder'=>'Ripeti la password'
  ])
  ->checkbox('terms','Accetto i termini e la privacy')
  ->submit('Crea account', ['variant'=>'primary','full'=>true]);

// Messaggi
$msgHtml = '';
if (!empty($_GET['err']) && $_GET['err'] === 'csrf') {
  $msgHtml = '<div class="alert alert-danger">Sessione scaduta. Riprova.</div>';
}
if (!empty($errors)) {
  $msgHtml .= '<div class="alert alert-danger"><strong>Controlla i dati:</strong><ul style="margin:.4rem 0 0 .9rem;">';
  foreach ($errors as $e) $msgHtml .= '<li>'.htmlspecialchars($e, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</li>';
  $msgHtml .= '</ul></div>';
}

// Card
$card = (new CLCard())
  ->start(['class'=>'auth-card'])
  ->header('Crea un account', 'Accesso area IT / IoT')
  ->body(
    '<div class="tabbar">'.$tabs.'</div>'.
    ($msgHtml ? $msgHtml : '').
    $form->render().
    '<div class="meta">'.(new CLButton())->link('Hai già un account? Accedi', '/Ardisafe2.0/login.php', ['variant'=>'ghost']).'</div>'
  , true);

?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Registrazione</title>
<style>
/* ====== Page theme (IT/IoT) ====== */
:root{
  --login-bg: url('/assets/img/login-iot.jpg');
  --grad-a: #2845d9;
  --grad-b: #4848ec;
  --panel-bg:  #ffffff;
  --panel-bg-2:#fbfcff;
  --panel-b:   #e9eef5;
  --text:  #0f172a;
  --muted: #6b7280;
  --shadow: 0 30px 60px rgba(17,24,39,.28), 0 6px 18px rgba(17,24,39,.12);
  --ring:   0 0 0 3px rgba(72,72,236,.20);
}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;color:var(--text);}

/* Background fullscreen */
.bg{
  min-height:100dvh;
  background-image:
    linear-gradient(180deg, rgba(9,12,28,.45), rgba(9,12,28,.45)),
    radial-gradient(1200px 600px at 80% 5%,  rgba(72,88,236,.35), transparent 60%),
    radial-gradient(900px 500px  at 10% 90%, rgba(40,99,217,.35), transparent 55%),
    linear-gradient(135deg, rgba(40,61,217,.55), rgba(72,113,236,.55)),
    var(--login-bg);
  background-size: cover; background-position: center;
  display:grid; place-items:center; padding:24px;
}

/* Card */
.auth-card{
  width:min(96vw, 460px);
  background: linear-gradient(180deg, var(--panel-bg), var(--panel-bg-2));
  border:1px solid var(--panel-b);
  border-radius:18px; box-shadow:var(--shadow); overflow:hidden;
}
.auth-card .clcard__header{ padding:20px 22px; border-bottom:1px solid #eef2f7; }
.auth-card .clcard__title{ font-size:24px; font-weight:800; letter-spacing:.2px; }
.auth-card .clcard__subtitle{ color:var(--muted); margin-top:4px; }
.auth-card .clcard__body{ padding:20px 22px; }
.auth-card .tabbar{ margin-bottom:12px; }

/* Tabs/segment */
.auth-card .tabbar .clbutton-group{
  display:flex; width:100%; padding:4px; border-radius:12px;
  background:#f3f5fb; border:1px solid #e6ebf5;
}
.auth-card .tabbar .clbutton{ flex:1; border-radius:8px !important; }
.auth-card .tabbar [aria-current="page"], 
.auth-card .tabbar .clbutton--primary{
  background-image: linear-gradient(90deg, var(--grad-a), var(--grad-b)) !important;
  color:#fff !important; border-color:transparent !important;
}
.auth-card .tabbar .clbutton--ghost{ color:#4f46e5 !important; }

/* Alert */
.alert{padding:10px 12px;border-radius:10px;font-size:14px;margin-bottom:12px;}
.alert-danger{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca;}
.alert-success{background:#e8fbef;color:#166534;border:1px solid #bbf7d0;}

/* Form */
.auth-card .clform label{ font-weight:600; color:#1f2937; }
.auth-card .clform input[type=text],
.auth-card .clform input[type=email],
.auth-card .clform input[type=password]{
  background:#fff; border:1px solid #e5e7eb; border-radius:12px;
  padding:.72rem .9rem; line-height:1.25;
  transition:border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
  box-shadow: inset 0 1px 0 rgba(17,24,39,.03);
}
.auth-card .clform input::placeholder{ color:#9aa4b2; }
.auth-card .clform input:focus{ outline:none; border-color:#6366f1; box-shadow: var(--ring); }
.auth-card .clform input[type=checkbox]{ accent-color:#4f46e5; width:18px; height:18px; }

/* Submit */
.auth-card .clform button[type=submit],
.auth-card .clbutton-group .clbutton--primary{
  background-image: linear-gradient(90deg, var(--grad-a), var(--grad-b));
  color:#fff; border:0; border-radius:12px; padding:.78rem 1rem; font-weight:700;
  box-shadow: 0 8px 18px rgba(72,72,236,.28);
}
.auth-card .clform button[type=submit]:hover{ filter:brightness(1.05); }
.auth-card .meta{ margin-top:10px; }

/* Responsive */
@media (max-width: 420px){
  .auth-card{ border-radius:16px; }
  .auth-card .clcard__body{ padding:16px; }
  .auth-card .clcard__header{ padding:16px; }
}
@media (max-height: 640px){
  .bg{ padding:16px; }
  .auth-card{ width:min(98vw, 480px); }
}
@media (prefers-reduced-motion: reduce){
  .bg{ scroll-behavior:auto }
  .auth-card *{ transition:none !important; }
}
</style>
</head>
<body>
  <div class="bg">
    <?= $card->render() ?>
  </div>
</body>
</html>
