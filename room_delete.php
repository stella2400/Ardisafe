<?php
// /Ardisafe2.0/room_delete.php
require_once __DIR__.'/config.php';
require_once __DIR__.'/autoloader.php';

if (function_exists('require_auth')) require_auth();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// CSRF (in caso non sia già stato impostato)
if (empty($_SESSION['csrf'])) {
  try { $_SESSION['csrf'] = bin2hex(random_bytes(16)); } catch (Throwable $e) { $_SESSION['csrf'] = sha1(uniqid('', true)); }
}
$csrf = $_SESSION['csrf'];

// Permessi
$viewer  = $_SESSION['user'] ?? ['ruolo'=>'operator'];
$isSuper = ($viewer['ruolo'] ?? 'operator') === 'superuser';
if (!$isSuper) { header('Location: /Ardisafe2.0/rooms.php?err=perm'); exit; }

// Helper: customer corrente
function current_customer_id_delete(): int {
  if (!empty($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
  $email = $_SESSION['user']['email'] ?? null;
  if (!$email) return 0;

  $pdo = $GLOBALS['pdo'] ?? null;
  if (!$pdo instanceof PDO) {
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $name = defined('DB_NAME') ? DB_NAME : 'ardisafe';
    $user = defined('DB_USER') ? DB_USER : 'root';
    $pass = defined('DB_PASS') ? DB_PASS : '';
    $dsn  = defined('DB_DSN')  ? DB_DSN  : "mysql:host={$host};dbname={$name};charset=utf8mb4";
    $pdo  = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  }
  $st = $pdo->prepare('SELECT id FROM customer WHERE email = ? LIMIT 1');
  $st->execute([$email]);
  $row = $st->fetch();
  return $row ? (int)$row['id'] : 0;
}

$customerId = current_customer_id_delete();
if ($customerId <= 0) { header('Location: /Ardisafe2.0/login.php'); exit; }

$repo = new IotRooms();

// --- POST: conferma cancellazione ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
    header('Location: /Ardisafe2.0/rooms.php?err=csrf'); exit;
  }
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) { header('Location: /Ardisafe2.0/rooms.php?err=badid'); exit; }

  $room = $repo->findById($id);
  if (!$room || $room->getCustomerId() !== $customerId) {
    header('Location: /Ardisafe2.0/rooms.php?err=notfound'); exit;
  }

  // Rimuovi immagine su disco (se nel path previsto)
  $img = $room->getImage();
  if ($img && strncmp($img, '/Ardisafe2.0/image/room/', 24) === 0) {
    $oldFs = __DIR__ . '/image/room/' . basename($img);
    if (is_file($oldFs)) @unlink($oldFs);
  }

  // Cancella da DB (vincoli cascata gestiscono record correlati)
  $repo->delete($id, $customerId);

  header('Location: /Ardisafe2.0/rooms.php?ok=del');
  exit;
}

// --- GET: pagina di conferma (id via GET) ---
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /Ardisafe2.0/rooms.php?err=badid'); exit; }

$room = $repo->findById($id);
if (!$room || $room->getCustomerId() !== $customerId) {
  header('Location: /Ardisafe2.0/rooms.php?err=notfound'); exit;
}

// Dati stanza per UI
$name  = htmlspecialchars($room->getName(), ENT_QUOTES, 'UTF-8');
$floor = htmlspecialchars($room->getFloorLabel() ?? '—', ENT_QUOTES, 'UTF-8');
$img   = $room->getImage() ? htmlspecialchars($room->getImage(), ENT_QUOTES, 'UTF-8') : '';
$bg    = $img ? "background-image:url('{$img}')" : "background-image:linear-gradient(135deg,#eef2ff,#e0e7ff)";

// Back link
$back = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/Ardisafe2.0/rooms.php';

// UI: pagina di conferma
echo app()->open('Elimina stanza');

// Card conferma con immagine + testo + bottoni
$confirmText = '<div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">'
  .'<div style="flex:1 1 280px">'
    .'<div style="height:160px;border-radius:12px;border:1px solid #e5e7eb;background-size:cover;background-position:center;'.$bg.'"></div>'
  .'</div>'
  .'<div style="flex:2 1 340px">'
    .'<h2 style="margin:0 0 6px;font-size:18px;line-height:1.2">Confermi l’eliminazione della stanza?</h2>'
    .'<div style="color:#6b7280;margin-bottom:10px">'
      .'Stanza: <strong>'.$name.'</strong><br>'
      .'Piano/Livello: <strong>'.$floor.'</strong><br>'
      .'<br>Questa azione è <strong>irreversibile</strong>. Verranno rimossi anche dati e collegamenti correlati (allarmi, associazioni dispositivi, ecc.).'
    .'</div>'
    .'<div style="display:flex;gap:8px;flex-wrap:wrap">'
      // pulsante elimina (POST + CSRF)
      .(new CLForm())
        ->start('/Ardisafe2.0/room_delete.php', 'POST', ['class'=>'clform', 'style'=>'display:inline-block'])
        ->hidden('csrf', $csrf)
        ->hidden('id', (string)$id)
        ->submit('Elimina definitivamente', [
          'style'=>'background:#dc2626;color:#fff;border-color:#ef4444;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer'
        ])
        ->render()
      // pulsante annulla (link)
      .(new CLButton())
        ->link('Annulla', $back, ['variant'=>'secondary'])
    .'</div>'
  .'</div>'
.'</div>';

$card = (new CLCard())->start()
  ->header('Conferma eliminazione', 'Questa operazione non può essere annullata')
  ->body($confirmText, true);

// Layout semplice
echo (new CLGrid())->start()->container('xl')
  ->row(['g'=>20,'align'=>'start'])
    ->colRaw(12, [], $card->render())
  ->endRow()
  ->render();

echo app()->close();
