<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/autoloader.php';

// Avvia sessione se non è già attiva (per CSRF)
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Se sei già autenticato vai in home
if (function_exists('is_authenticated') && is_authenticated()) {
    header('Location: /Ardisafe2.0/homepage.php'); exit;
}elseif (isset($_GET['timeout'])) {
    $msgHtml = '<div class="alert alert-danger">Sessione scaduta per inattività (15 minuti). Accedi nuovamente.</div>';
}


/** PDO come in register.php (riusa config se esiste) */
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

// POST: login (autenticazione diretta su tabella customer)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        header('Location: /Ardisafe2.0/login.php?err=csrf'); exit;
    }
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    try {
        $pdo = getPDO();
        $st = $pdo->prepare('SELECT id, nome, cognome, email, ruolo, password FROM customer WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $u = $st->fetch();

        if ($u && password_verify($password, $u['password'])) {
            // opzionale: rehahsing se necessario
            if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
                $upd = $pdo->prepare('UPDATE customer SET password = ? WHERE id = ?');
                $upd->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
            }
            // salva dati utente essenziali in sessione
            $_SESSION['user'] = [
                'id'      => $u['id'],
                'nome'    => $u['nome'],
                'cognome' => $u['cognome'],
                'email'   => $u['email'],
                'ruolo'   => $u['ruolo'],
            ];
            unset($_SESSION['csrf']);
            header('Location: /Ardisafe2.0/homepage.php'); exit;
        } else {
            header('Location: /Ardisafe2.0/login.php?err=creds'); exit;
        }
    } catch (Throwable $e) {
        header('Location: /Ardisafe2.0/login.php?err=creds'); exit;
    }
}

// GET: token CSRF
if (empty($_SESSION['csrf'])) {
    try { $_SESSION['csrf'] = bin2hex(random_bytes(32)); } catch (Throwable $e) { $_SESSION['csrf'] = sha1(uniqid('', true)); }
}
$csrf = $_SESSION['csrf'];

// Messaggi
$err = $_GET['err'] ?? null;
$msgHtml = '';
if ($err === 'csrf') {
    $msgHtml = '<div class="alert alert-danger">Sessione scaduta o token non valido. Riprova.</div>';
} elseif ($err === 'creds') {
    $msgHtml = '<div class="alert alert-danger">Email o password errate.</div>';
} elseif (isset($_GET['loggedout'])) {
    $msgHtml = '<div class="alert alert-success">Disconnessione avvenuta correttamente.</div>';
} elseif (isset($_GET['registered'])) {
    $msgHtml = '<div class="alert alert-success">Registrazione completata! Ora puoi accedere.</div>';
}

// ====== UI (Card + Form) ======
$form = (new CLForm())
    ->start('/Ardisafe2.0/login.php','POST',['id'=>'login-form'])
    ->csrf('csrf', $csrf)
    ->email('email','Email aziendale', [
        'required'=>true,
        'placeholder'=>'nome.cognome@azienda.it'
    ])
    ->password('password','Password', [
        'required'=>true,
        'placeholder'=>'••••••••'
    ])
    ->checkbox('remember','Resta connesso')
    ->submit('Accedi', ['variant'=>'primary','full'=>true]);

$tabs = (new CLButton())
    ->startGroup(['merge' => true])
      ->button('Login',  ['variant'=>'primary', 'attrs'=>['aria-current'=>'page']])
      ->link  ('Signup', '/Ardisafe2.0/register.php', ['variant'=>'ghost'])
    ->render();

$forgot = (new CLButton())->link('Password dimenticata?', '/password-reset.php', ['variant'=>'ghost']);

// Card contenente il form
$card = (new CLCard())
    ->start(['class'=>'auth-card'])
    ->header('Login')
    ->body(
        ($msgHtml ? $msgHtml : '') .
        '<div class="tabbar">'.$tabs.'</div>' .
        $form->render() .
        '<div class="meta">'.$forgot.'</div>'
    , true);

// ====== OUTPUT ====== ?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login</title>
<style>
/* ====== Page theme (IT/IoT) ====== */
:root{
  --login-bg: url('/assets/img/login-iot.jpg');
  --grad-a: #2845d9;
  --grad-b: #4848ec;
  --panel-bg: #ffffff;
  --panel-b: #e5e7eb;
  --text: #111827;
  --muted: #6b7280;
  --shadow: 0 30px 60px rgba(17,24,39,.35);
}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;color:var(--text);}

/* Fullscreen background with gradient overlay */
.bg{
  min-height:100%;
  background-image:
    radial-gradient(1200px 600px at 80% 5%, rgba(72, 88, 236, 0.35), transparent 60%),
    radial-gradient(900px 500px at 10% 90%, rgba(40, 99, 217, 0.35), transparent 55%),
    linear-gradient(135deg, rgba(40, 61, 217, 0.65), rgba(72, 113, 236, 0.65)),
    var(--login-bg);
  background-size: cover;
  background-position: center;
  display:grid; place-items:center;
  padding:24px;
}

/* Center card */
.auth-card{ width:min(96vw, 420px); background:var(--panel-bg); border:1px solid var(--panel-b);
  border-radius:18px; box-shadow:var(--shadow); overflow:hidden; }

/* CLCard header/body/footer (integra le classi della card) */
.auth-card .clcard__header{ padding:18px 20px; border-bottom:1px solid #f1f5f9;}
.auth-card .clcard__title{ font-size:22px; font-weight:800; letter-spacing:.2px;}
.auth-card .clcard__subtitle{ color:var(--muted); margin-top:2px;}

.auth-card .clcard__body{ padding:18px 20px; }
.auth-card .tabbar{ margin-bottom:12px; }

/* Alert */
.alert{padding:10px 12px;border-radius:10px;font-size:14px;margin-bottom:10px;}
.alert-danger{background:#fee2e2;color:#991b1b;border:1px solid #2e3ea8ff;}
.alert-success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0;}

/* Spazio “forgot password?” */
.auth-card .meta{margin-top:8px;}

/* Form tweaks: rendiamo il submit gradient (senza toccare la classe base) */
.auth-card .clform button[type=submit],
.auth-card .clbutton-group .clbutton--primary{
  background-image: linear-gradient(90deg, var(--grad-a), var(--grad-b));
  color:#fff; border-color:transparent;
}
.auth-card .clform button[type=submit]:hover,
.auth-card .clbutton-group .clbutton--primary:hover{
  filter:brightness(1.05);
}

/* Piccoli tocchi “tech” su input */
.auth-card .clform input[type=email],
.auth-card .clform input[type=password]{
  background:#fff;
  border:1px solid #e5e7eb; border-radius:10px;
  box-shadow: inset 0 1px 0 rgba(17,24,39,.03);
}

/* Responsive: quando lo schermo è basso, riduco padding della card */
@media (max-height: 640px){
  .auth-card .clcard__body{ padding:12px 16px; }
  .auth-card .clcard__header{ padding:14px 16px; }
}

/* Preferenze ridotte di movimento */
@media (prefers-reduced-motion: reduce){
  .bg{scroll-behavior:auto}
}
</style>
</head>
<body>
  <div class="bg">
    <?= $card->render() ?>
  </div>
</body>
</html>
